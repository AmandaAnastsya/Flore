<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Testimonial extends Model
{
    public const STATUS_LABELS = ['pending' => 'Menunggu', 'approved' => 'Disetujui', 'rejected' => 'Ditolak'];
    public const STATUS_BADGES = ['pending' => 'warning', 'approved' => 'success', 'rejected' => 'danger'];

    protected $fillable = ['user_id', 'order_id', 'rating', 'content', 'is_approved', 'approved_at', 'rejected_at'];

    protected $casts = ['is_approved' => 'boolean', 'approved_at' => 'datetime', 'rejected_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function status(): string
    {
        return match (true) {
            $this->is_approved => 'approved',
            $this->rejected_at !== null => 'rejected',
            default => 'pending',
        };
    }

    public function scopeWithStatus(Builder $query, string $status): Builder
    {
        return match ($status) {
            'approved' => $query->where('is_approved', true),
            'rejected' => $query->where('is_approved', false)->whereNotNull('rejected_at'),
            default => $query->where('is_approved', false)->whereNull('rejected_at'),
        };
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('is_approved', true);
    }
}
