<?php

namespace Tests\Feature;

use App\Ai\Agents\RoleplayAgent;
use App\Ai\Tools\FindScenarioAttachment;
use App\Models\Scenario;
use App\Models\ScenarioAttachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Files;
use Laravel\Ai\Prompts\AgentPrompt;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ScenarioChatControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_send_message(): void
    {
        $scenario = Scenario::factory()->create();

        $this->postJson(route('scenarios.chat.message', $scenario), [
            'message' => 'こんにちは',
        ])->assertUnauthorized();
    }

    public function test_message_is_required(): void
    {
        $user = User::factory()->create();
        $scenario = Scenario::factory()->create();

        RoleplayAgent::fake(['テスト応答']);

        $this->actingAs($user)
            ->postJson(route('scenarios.chat.message', $scenario), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['message']);
    }

    public function test_message_cannot_exceed_2000_characters(): void
    {
        $user = User::factory()->create();
        $scenario = Scenario::factory()->create();

        RoleplayAgent::fake(['テスト応答']);

        $this->actingAs($user)
            ->postJson(route('scenarios.chat.message', $scenario), [
                'message' => str_repeat('あ', 2001),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['message']);
    }

    public function test_authenticated_user_receives_sse_stream(): void
    {
        $user = User::factory()->create();
        $scenario = Scenario::factory()->create();

        RoleplayAgent::fake(['こんにちは！お手伝いします。']);

        $response = $this->actingAs($user)
            ->post(route('scenarios.chat.message', $scenario), [
                'message' => 'こんにちは',
            ]);

        $response->assertOk()
            ->assertHeader('Content-Type', 'text/event-stream; charset=utf-8');

        $events = $this->parseSseEvents($response);

        $text = collect($events)
            ->where('type', 'text_delta')
            ->pluck('delta')
            ->join('');

        $this->assertSame('こんにちは！お手伝いします。', $text);
        $this->assertNotNull(
            collect($events)->firstWhere('type', 'conversation_id')
        );

        RoleplayAgent::assertPrompted('こんにちは');
    }

    public function test_chatbot_receives_attachment_names_and_tool_for_text_attachments(): void
    {
        Storage::fake('local');
        Files::fake();

        $user = User::factory()->create();
        $scenario = Scenario::factory()->create([
            'title' => '提案前ヒアリング',
            'summary' => '現場課題を把握する。',
            'goal' => '次回提案に必要な論点を整理する。',
        ]);

        Storage::disk('local')->put("scenario_attachments/{$scenario->id}/brief.md", '# 課題');

        ScenarioAttachment::query()->create([
            'scenario_id' => $scenario->id,
            'path' => "scenario_attachments/{$scenario->id}/brief.md",
            'name' => '顧客課題メモ.md',
            'mime_type' => 'text/markdown',
            'size' => 10,
        ]);

        RoleplayAgent::fake(['添付を確認しました。']);

        $this->actingAs($user)->post(route('scenarios.chat.message', $scenario), [
            'message' => 'どんな課題がありそうですか？',
        ])->assertOk();

        RoleplayAgent::assertPrompted(function (AgentPrompt $prompt): bool {
            $tools = collect(iterator_to_array($prompt->agent->tools()));

            return $prompt->contains('顧客課題メモ.md')
                && $prompt->contains('提案前ヒアリング')
                && $prompt->contains('どんな課題がありそうですか？')
                && ! $prompt->contains('# 課題')
                && $prompt->attachments->count() === 0
                && $tools->contains(fn (mixed $tool): bool => $tool instanceof FindScenarioAttachment);
        });

        Files::assertNothingStored();
    }

    public function test_chatbot_stores_pdf_attachment_with_provider_file_id(): void
    {
        Storage::fake('local');
        Files::fake();

        $user = User::factory()->create();
        $scenario = Scenario::factory()->create();

        Storage::disk('local')->put("scenario_attachments/{$scenario->id}/product.pdf", 'pdf-content');

        $attachment = ScenarioAttachment::query()->create([
            'scenario_id' => $scenario->id,
            'path' => "scenario_attachments/{$scenario->id}/product.pdf",
            'name' => '製品概要.pdf',
            'mime_type' => 'application/pdf',
            'size' => 11,
        ]);

        RoleplayAgent::fake(['PDFを確認しました。']);

        $this->actingAs($user)->post(route('scenarios.chat.message', $scenario), [
            'message' => '資料の内容を要約してください。',
        ])->assertOk();

        $attachment->refresh();

        $this->assertNotNull($attachment->ai_file_id);
        $this->assertSame(config('ai.default'), $attachment->ai_provider);

        RoleplayAgent::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->attachments->count() === 1);

        Files::assertStored(fn ($file) => $file->name() === '製品概要.pdf');
    }

    public function test_conversation_can_be_continued(): void
    {
        $user = User::factory()->create();
        $scenario = Scenario::factory()->create();

        RoleplayAgent::fake();

        $first = $this->actingAs($user)
            ->post(route('scenarios.chat.message', $scenario), [
                'message' => '最初のメッセージ',
            ]);

        $first->assertOk();

        $conversationIdEvent = collect($this->parseSseEvents($first))
            ->firstWhere('type', 'conversation_id');

        $this->assertNotNull($conversationIdEvent);
        $conversationId = $conversationIdEvent['conversation_id'];

        $second = $this->actingAs($user)
            ->post(route('scenarios.chat.message', $scenario), [
                'message' => '続きのメッセージ',
                'conversation_id' => $conversationId,
            ]);

        $second->assertOk()->assertHeader('Content-Type', 'text/event-stream; charset=utf-8');
    }

    /** @return array<int, array<string, mixed>> */
    private function parseSseEvents(TestResponse $response): array
    {
        $events = [];

        foreach (explode("\n", $response->streamedContent()) as $line) {
            if (! str_starts_with($line, 'data: ')) {
                continue;
            }

            $payload = substr($line, 6);

            if ($payload === '[DONE]') {
                break;
            }

            $events[] = json_decode($payload, true);
        }

        return $events;
    }
}
