<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reminder extends Model
{
    protected $fillable = [
        'user_id', 'order_id', 'type', 'title', 'occasion_date', 'remind_at',
        'channel', 'status', 'sent_at', 'error_message',
    ];

    protected $casts = [
        'occasion_date' => 'date',
        'remind_at' => 'date',
        'sent_at' => 'datetime',
    ];

    public function scopeDue(Builder $query): Builder
    {
        return $query->where('status', 'pending')->whereDate('remind_at', '<=', today());
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}