<?php

namespace App\Http\Controllers;

use App\Models\Scenario;
use App\Models\ScenarioAttachment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Ai\Files;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ScenarioAttachmentController extends Controller
{
    public function store(Request $request, Scenario $scenario): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,txt,md,png,jpg,jpeg,gif,webp,csv,docx,xlsx'],
        ]);

        $file = $request->file('file');
        $path = $file->store("scenario_attachments/{$scenario->id}", 'local');

        $attachment = $scenario->attachments()->create([
            'path' => $path,
            'name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
        ]);

        return response()->json($attachment->only(['id', 'name', 'mime_type', 'size']), 201);
    }

    public function download(Scenario $scenario, ScenarioAttachment $attachment): StreamedResponse
    {
        abort_if($attachment->scenario_id !== $scenario->id, 404);
        abort_if(Storage::disk('local')->missing($attachment->path), 404);

        return Storage::disk('local')->download(
            $attachment->path,
            $this->fallbackDownloadName($attachment->name),
            [
                'Content-Disposition' => (new ResponseHeaderBag)->makeDisposition(
                    ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                    $attachment->name,
                    $this->fallbackDownloadName($attachment->name),
                ),
            ],
        );
    }

    public function destroy(Scenario $scenario, ScenarioAttachment $attachment): JsonResponse
    {
        abort_if($attachment->scenario_id !== $scenario->id, 404);

        if ($attachment->ai_file_id !== null) {
            Files::delete($attachment->ai_file_id, provider: $attachment->ai_provider);
        }

        Storage::disk('local')->delete($attachment->path);
        $attachment->delete();

        return response()->json(null, 204);
    }

    private function fallbackDownloadName(string $name): string
    {
        $extension = pathinfo($name, PATHINFO_EXTENSION);
        $baseName = pathinfo($name, PATHINFO_FILENAME);
        $asciiBaseName = Str::of($baseName)->ascii()->slug('_')->value();

        if ($asciiBaseName === '') {
            $asciiBaseName = 'attachment';
        }

        return $extension === ''
            ? $asciiBaseName
            : "{$asciiBaseName}.{$extension}";
    }
}
