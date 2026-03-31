<?php

use App\Ai\Agents\RoleplayAgent;
use Illuminate\Http\Client\RequestException;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Files\Document;
use Laravel\Ai\Files\Image;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('ai:probe-attachment {path} {--disk=local} {--image} {--provider=} {--model=}', function () {
    $path = (string) $this->argument('path');
    $disk = (string) $this->option('disk');
    $provider = $this->option('provider') ?: null;
    $model = $this->option('model') ?: null;
    $isImage = (bool) $this->option('image');

    if (Storage::disk($disk)->missing($path)) {
        $this->error("File not found on disk [{$disk}]: {$path}");

        return self::FAILURE;
    }

    $attachment = $isImage
        ? Image::fromStorage($path, disk: $disk)
        : Document::fromStorage($path, disk: $disk);

    $this->line("Disk: {$disk}");
    $this->line("Path: {$path}");
    $this->line('Mode: '.($isImage ? 'image' : 'document'));

    try {
        $response = (new RoleplayAgent)->prompt(
            '添付ファイルが見えているかだけを一文で答えてください。',
            attachments: [$attachment],
            provider: $provider,
            model: $model,
        );

        $this->info('Prompt succeeded.');
        $this->line($response->text);

        return self::SUCCESS;
    } catch (RequestException $e) {
        $this->error('Prompt failed.');
        $this->line('HTTP '.$e->response->status());
        $this->line($e->response->body());

        return self::FAILURE;
    }
})->purpose('Probe direct AI attachment handling against the configured provider');
