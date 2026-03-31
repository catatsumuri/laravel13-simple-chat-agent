<?php

namespace App\Http\Controllers;

use App\Models\Scenario;
use Inertia\Inertia;
use Inertia\Response;

class ScenarioController extends Controller
{
    public function edit(Scenario $scenario): Response
    {
        return Inertia::render('scenarios/edit', [
            'scenario' => $scenario->only(['id', 'title', 'company_name', 'industry', 'difficulty']),
            'attachments' => $scenario->attachments()->orderBy('created_at')->get(['id', 'name', 'mime_type', 'size']),
        ]);
    }
}
