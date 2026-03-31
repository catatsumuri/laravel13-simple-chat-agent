<?php

namespace Tests\Feature;

use App\Ai\Tools\FindScenarioAttachment;
use App\Models\Scenario;
use App\Models\ScenarioAttachment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Tools\Request as ToolRequest;
use Tests\TestCase;

class FindScenarioAttachmentToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_matching_text_attachment_excerpt(): void
    {
        Storage::fake('local');

        $scenario = Scenario::factory()->create();

        Storage::disk('local')->put(
            "scenario_attachments/{$scenario->id}/business-card.txt",
            "NextBridge株式会社\n上田 健太\nk.ueda@nextbridge.co.jp\n090-1234-5678"
        );

        ScenarioAttachment::query()->create([
            'scenario_id' => $scenario->id,
            'path' => "scenario_attachments/{$scenario->id}/business-card.txt",
            'name' => '営業名刺.txt',
            'mime_type' => 'text/plain',
            'size' => 64,
        ]);

        $result = (string) (new FindScenarioAttachment($scenario))->handle(new ToolRequest([
            'query' => '上田',
        ]));

        $this->assertStringContainsString('営業名刺.txt', $result);
        $this->assertStringContainsString('本文抜粋:', $result);
        $this->assertStringContainsString('k.ueda@nextbridge.co.jp', $result);
    }

    public function test_it_returns_matching_pdf_by_filename_without_excerpt(): void
    {
        Storage::fake('local');

        $scenario = Scenario::factory()->create();

        Storage::disk('local')->put("scenario_attachments/{$scenario->id}/catalog.pdf", 'pdf-content');

        ScenarioAttachment::query()->create([
            'scenario_id' => $scenario->id,
            'path' => "scenario_attachments/{$scenario->id}/catalog.pdf",
            'name' => 'CloudCore製品カタログ.pdf',
            'mime_type' => 'application/pdf',
            'size' => 11,
        ]);

        $result = (string) (new FindScenarioAttachment($scenario))->handle(new ToolRequest([
            'query' => 'カタログ',
        ]));

        $this->assertStringContainsString('CloudCore製品カタログ.pdf', $result);
        $this->assertStringContainsString('本文抜粋: この資料は本文抜粋を直接返せません。', $result);
    }
}
