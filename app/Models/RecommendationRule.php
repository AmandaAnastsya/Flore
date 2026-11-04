<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecommendationRule extends Model
{
    protected $fillable = ['criteria', 'value', 'weight', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];
}