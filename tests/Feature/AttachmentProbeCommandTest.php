<?php

namespace Tests\Feature;

use App\Ai\Agents\RoleplayAgent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Prompts\AgentPrompt;
use Tests\TestCase;

class AttachmentProbeCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_probe_attachment_command_sends_storage_document_as_attachment(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('probes/transcript.txt', 'hello');

        RoleplayAgent::fake(['見えています。']);

        $this->artisan('ai:probe-attachment', [
            'path' => 'probes/transcript.txt',
        ])->expectsOutput('Disk: local')
            ->expectsOutput('Path: probes/transcript.txt')
            ->expectsOutput('Mode: document')
            ->expectsOutput('Prompt succeeded.')
            ->expectsOutput('見えています。')
            ->assertExitCode(0);

        RoleplayAgent::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->attachments->count() === 1);
    }

    public function test_probe_attachment_command_fails_when_file_is_missing(): void
    {
        Storage::fake('local');

        $this->artisan('ai:probe-attachment', [
            'path' => 'probes/missing.txt',
        ])->expectsOutput('File not found on disk [local]: probes/missing.txt')
            ->assertExitCode(1);
    }
}
