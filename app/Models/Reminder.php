<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reminder extends Model
{
    protected $fillable = [
        'user_id', 'order_id', 'special_moment_id', 'dedupe_key', 'type', 'title', 'occasion_date', 'remind_at',
        'channel', 'status', 'sent_at', 'error_message',
    ];

    public const TYPE_LABELS = [
        'birthday' => 'Ulang Tahun',
        'anniversary' => 'Anniversary',
        'recurring_purchase' => 'Pembelian Berulang',
        'custom' => 'Momen Spesial',
    ];

    public const STATUS_BADGES = ['pending' => 'warning', 'sent' => 'success', 'failed' => 'danger'];

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