<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpecialMoment extends Model
{
    public const TYPES = [
        'birthday' => 'Ulang Tahun',
        'anniversary' => 'Anniversary',
        'custom' => 'Momen Lainnya',
    ];

    public const MAX_PER_USER = 20;

    protected $fillable = ['user_id', 'type', 'title', 'date'];

    protected $casts = ['date' => 'date'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
