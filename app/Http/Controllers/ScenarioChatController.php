<?php

namespace App\Http\Controllers;

use App\Ai\Agents\RoleplayAgent;
use App\Models\Scenario;
use App\Models\ScenarioAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Files\Document;
use Laravel\Ai\Files\Image;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ScenarioChatController extends Controller
{
    public function message(Request $request, Scenario $scenario): StreamedResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'conversation_id' => ['nullable', 'string'],
        ]);

        $agent = new RoleplayAgent($scenario);

        if ($validated['conversation_id'] ?? null) {
            $agent = $agent->continue($validated['conversation_id'], as: $request->user());
        } else {
            $agent = $agent->forUser($request->user());
        }

        $attachments = $this->providerAttachmentsFor($scenario);

        $stream = $agent->stream(
            $this->buildPrompt($scenario, $validated['message']),
            attachments: $attachments,
        );

        return response()->stream(function () use ($stream): void {
            foreach ($stream as $event) {
                echo 'data: '.$event."\n\n";
                ob_flush();
                flush();
            }

            echo 'data: '.json_encode([
                'type' => 'conversation_id',
                'conversation_id' => $stream->conversationId,
            ])."\n\n";

            echo "data: [DONE]\n\n";
            ob_flush();
            flush();
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'X-Accel-Buffering' => 'no',
            'Cache-Control' => 'no-cache',
        ]);
    }

    private function buildPrompt(Scenario $scenario, string $message): string
    {
        $personaName = '田中 恒一';

        $attachments = $scenario->attachments()
            ->orderBy('created_at')
            ->get(['name']);

        $attachmentNames = $attachments->isEmpty()
            ? 'なし'
            : $attachments->pluck('name')->implode('、');

        return <<<TEXT
会話の前提:
- あなたは営業先の相手役です。
- ユーザーは営業担当者です。
- あなたは以下のシナリオの人物本人として自然に受け答えしてください。
- 営業担当者向けの提案アドバイスや要約資料のように振る舞ってはいけません。
- あなたは営業担当者から資料を受け取っています。受け取っている資料のファイル名は「{$attachmentNames}」です。
- これらの資料には、営業担当者の名刺や、営業担当者が紹介したい製品カタログが含まれる場合があります。
- 添付資料は営業担当者が提示・所持している資料です。あなた自身の名刺やプロフィールとは限りません。
- 名刺、製品カタログ、添付資料、連絡先、会社名、役職、製品名などの確認が必要な時だけ、利用可能なツールで関連資料を確認してください。
- 資料が不要な挨拶や通常会話では、毎回ツールを使わず自然に応答してください。
- 一人称で「私の名前」「私の会社」「私の役職」などを聞かれた場合は、必ずシナリオ上の人物として答えてください。添付資料の名前や会社名を自分のものとして名乗ってはいけません。
- 添付資料に営業担当者の名刺が含まれている場合は、「営業担当者から見えている名刺情報」として扱ってください。
- あなた自身の身元は次の情報だけを使ってください:
  名前 = {$personaName}
  会社名 = {$scenario->company_name}
  業界 = {$scenario->industry}
  立場 = {$scenario->customer_persona}
  難易度 = {$scenario->difficulty}
- シナリオには個人名が書かれていないため、この会話では仮名として「{$personaName}」をあなた自身の名前として使ってください。

シナリオ情報:
- タイトル: {$scenario->title}
- 会社名: {$scenario->company_name}
- 業界: {$scenario->industry}
- 想定相手: {$scenario->customer_persona}
- 難易度: {$scenario->difficulty}
- Situation: {$scenario->summary}
- Goal: {$scenario->goal}

応答ルール:
- 添付資料の内容について答える前に、必要ならツールで対象資料を確認してください。
- 回答では、必要に応じて資料内の会社名、氏名、役職、連絡先、製品名などの具体情報をそのまま使ってください。
- ユーザーの質問が特定の資料に向いている場合は、その資料だけを優先して答えてください。たとえば「名刺」について聞かれたら名刺を優先し、製品資料の内容を混ぜないでください。
- 質問と関係のない添付資料の要約や提案は、明示的に求められていない限り出さないでください。

ユーザー発話:
{$message}
TEXT;
    }

    /**
     * @return array<int, \Laravel\Ai\Files\ProviderDocument|\Laravel\Ai\Files\ProviderImage>
     */
    private function providerAttachmentsFor(Scenario $scenario): array
    {
        return $scenario->attachments()
            ->orderBy('created_at')
            ->get(['id', 'path', 'name', 'mime_type', 'ai_provider', 'ai_file_id'])
            ->reject(fn (ScenarioAttachment $attachment) => $this->isPromptReadableText($attachment))
            ->map(fn (ScenarioAttachment $attachment) => $this->providerAttachmentFor($attachment))
            ->filter()
            ->values()
            ->all();
    }

    private function providerAttachmentFor(ScenarioAttachment $attachment): \Laravel\Ai\Files\ProviderDocument|\Laravel\Ai\Files\ProviderImage|null
    {
        if (Storage::disk('local')->missing($attachment->path)) {
            return null;
        }

        $provider = $attachment->ai_provider ?? config('ai.default');
        $providerFileId = $attachment->ai_file_id;

        if ($providerFileId === null || $attachment->ai_provider !== $provider) {
            $stored = $this->storeAttachmentWithProvider($attachment, $provider);
            $providerFileId = $stored->id;

            $attachment->forceFill([
                'ai_provider' => $provider,
                'ai_file_id' => $providerFileId,
            ])->save();
        }

        return str_starts_with($attachment->mime_type, 'image/')
            ? Image::fromId($providerFileId)
            : Document::fromId($providerFileId);
    }

    private function storeAttachmentWithProvider(ScenarioAttachment $attachment, string $provider): \Laravel\Ai\Responses\StoredFileResponse
    {
        return str_starts_with($attachment->mime_type, 'image/')
            ? Image::fromStorage($attachment->path, disk: 'local')->as($attachment->name)->put(provider: $provider)
            : Document::fromStorage($attachment->path, disk: 'local')->as($attachment->name)->put(provider: $provider);
    }

    private function isPromptReadableText(ScenarioAttachment $attachment): bool
    {
        return in_array($attachment->mime_type, [
            'text/plain',
            'text/markdown',
            'text/csv',
            'application/csv',
            'application/vnd.ms-excel',
        ], true);
    }
}
