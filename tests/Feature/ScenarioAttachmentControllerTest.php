<?php

namespace Tests\Feature;

use App\Models\Scenario;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ScenarioAttachmentControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_upload_an_attachment(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $scenario = Scenario::factory()->create();
        $file = UploadedFile::fake()->create('brief.pdf', 128, 'application/pdf');

        $response = $this->actingAs($user)->post(route('scenarios.attachments.store', $scenario), [
            'file' => $file,
        ], [
            'Accept' => 'application/json',
        ]);

        $response->assertCreated()
            ->assertJsonPath('name', 'brief.pdf')
            ->assertJsonPath('mime_type', 'application/pdf');

        $this->assertDatabaseHas('scenario_attachments', [
            'scenario_id' => $scenario->id,
            'name' => 'brief.pdf',
            'mime_type' => 'application/pdf',
        ]);

        Storage::disk('local')->assertExists("scenario_attachments/{$scenario->id}/{$file->hashName()}");
    }

    public function test_upload_requires_a_supported_file_type(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $scenario = Scenario::factory()->create();
        $file = UploadedFile::fake()->create('script.exe', 32, 'application/octet-stream');

        $this->actingAs($user)->post(route('scenarios.attachments.store', $scenario), [
            'file' => $file,
        ], [
            'Accept' => 'application/json',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);
    }

    public function test_authenticated_user_can_delete_an_attachment(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $scenario = Scenario::factory()->create();
        $path = "scenario_attachments/{$scenario->id}/brief.pdf";

        Storage::disk('local')->put($path, 'brief');

        $attachment = $scenario->attachments()->create([
            'path' => $path,
            'name' => 'brief.pdf',
            'mime_type' => 'application/pdf',
            'size' => 5,
        ]);

        $this->actingAs($user)->delete(route('scenarios.attachments.destroy', [
            'scenario' => $scenario,
            'attachment' => $attachment,
        ]), [], [
            'Accept' => 'application/json',
        ])->assertNoContent();

        $this->assertDatabaseMissing('scenario_attachments', [
            'id' => $attachment->id,
        ]);

        Storage::disk('local')->assertMissing($path);
    }

    public function test_authenticated_user_can_download_an_attachment(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $scenario = Scenario::factory()->create();
        $path = "scenario_attachments/{$scenario->id}/brief.pdf";

        Storage::disk('local')->put($path, 'brief');

        $attachment = $scenario->attachments()->create([
            'path' => $path,
            'name' => 'brief.pdf',
            'mime_type' => 'application/pdf',
            'size' => 5,
        ]);

        $this->actingAs($user)->get(route('scenarios.attachments.download', [
            'scenario' => $scenario,
            'attachment' => $attachment,
        ]))->assertOk()
            ->assertDownload('brief.pdf');
    }

    public function test_download_preserves_original_utf8_filename(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $scenario = Scenario::factory()->create();
        $path = "scenario_attachments/{$scenario->id}/proposal.pdf";

        Storage::disk('local')->put($path, 'brief');

        $attachment = $scenario->attachments()->create([
            'path' => $path,
            'name' => '提案資料 2026.pdf',
            'mime_type' => 'application/pdf',
            'size' => 5,
        ]);

        $response = $this->actingAs($user)->get(route('scenarios.attachments.download', [
            'scenario' => $scenario,
            'attachment' => $attachment,
        ]));

        $response->assertOk();
        $this->assertStringContainsString(
            "filename*=utf-8''%E6%8F%90%E6%A1%88%E8%B3%87%E6%96%99%202026.pdf",
            $response->headers->get('content-disposition', ''),
        );
    }
}
