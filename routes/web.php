<?php

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
});

require __DIR__.'/settings.php';
