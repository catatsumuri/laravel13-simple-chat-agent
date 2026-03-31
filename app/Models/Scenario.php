<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Scenario extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'company_name',
        'industry',
        'customer_persona',
        'difficulty',
        'sort_order',
        'summary',
        'goal',
    ];

    /** @return HasMany<ScenarioAttachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(ScenarioAttachment::class);
    }
}
