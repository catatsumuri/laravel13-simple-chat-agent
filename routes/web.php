<?php

use App\Http\Controllers\ScenarioAttachmentController;
use App\Http\Controllers\ScenarioChatController;
use App\Http\Controllers\ScenarioController;
use App\Models\Scenario;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::inertia('/', 'welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('dashboard', [
            'scenarios' => Scenario::query()
                ->with('attachments:id,scenario_id,name,mime_type,size')
                ->orderBy('sort_order')
                ->get([
                    'id',
                    'title',
                    'company_name',
                    'industry',
                    'customer_persona',
                    'difficulty',
                    'summary',
                    'goal',
                ]),
        ]);
    })->name('dashboard');

    Route::get('scenarios/{scenario}/chat', function (Scenario $scenario) {
        return Inertia::render('scenarios/chat', [
            'scenario' => $scenario->only([
                'id',
                'title',
                'company_name',
                'industry',
                'customer_persona',
                'difficulty',
                'summary',
                'goal',
            ]),
        ]);
    })->name('scenarios.chat');

    Route::post('scenarios/{scenario}/chat/messages', [ScenarioChatController::class, 'message'])->name('scenarios.chat.message');

    Route::get('scenarios/{scenario}/edit', [ScenarioController::class, 'edit'])->name('scenarios.edit');
    Route::post('scenarios/{scenario}/attachments', [ScenarioAttachmentController::class, 'store'])->name('scenarios.attachments.store');
    Route::get('scenarios/{scenario}/attachments/{attachment}', [ScenarioAttachmentController::class, 'download'])->name('scenarios.attachments.download');
    Route::delete('scenarios/{scenario}/attachments/{attachment}', [ScenarioAttachmentController::class, 'destroy'])->name('scenarios.attachments.destroy');
});

require __DIR__.'/settings.php';
