<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Order extends Model
{
    protected $fillable = [
        'order_number', 'user_id', 'recipient_name', 'recipient_phone',
        'delivery_city', 'delivery_address', 'delivery_date', 'delivery_slot',
        'occasion', 'card_message', 'sender_name', 'is_anonymous',
        'subtotal', 'delivery_fee', 'discount', 'total_amount',
        'status', 'payment_status', 'payment_proof', 'paid_at', 'cancelled_at',
    ];

    protected $casts = [
        'delivery_date' => 'date',
        'is_anonymous' => 'boolean',
        'subtotal' => 'decimal:2',
        'delivery_fee' => 'decimal:2',
        'discount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Order $order) {
            $order->order_number ??= 'FLR-' . now()->format('ymd') . '-' . strtoupper(Str::random(5));
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(Reminder::class);
    }
}