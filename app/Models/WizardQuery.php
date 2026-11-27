<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WizardQuery extends Model
{
    protected $fillable = ['user_id', 'occasion', 'color', 'style', 'size', 'budget', 'mode', 'result_count', 'bouquet_ids'];

    protected $casts = ['bouquet_ids' => 'array'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
