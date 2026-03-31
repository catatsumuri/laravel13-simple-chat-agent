<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScenarioAttachment extends Model
{
    protected $fillable = [
        'scenario_id',
        'path',
        'name',
        'mime_type',
        'size',
        'ai_provider',
        'ai_file_id',
    ];

    /** @return BelongsTo<Scenario, $this> */
    public function scenario(): BelongsTo
    {
        return $this->belongsTo(Scenario::class);
    }
}
