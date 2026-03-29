<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Scenario extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
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
}
