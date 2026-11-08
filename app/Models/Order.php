<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Builder;

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

    public const STATUS_LABELS = [
    'created' => 'Dibuat',
    'pending_payment' => 'Menunggu pembayaran',
    'payment_confirmed' => 'Pembayaran dikonfirmasi',
    'preparing' => 'Sedang disiapkan',
    'out_for_delivery' => 'Dalam pengiriman',
    'delivered' => 'Terkirim',
    'cancelled' => 'Dibatalkan',
];

public const STATUS_BADGES = [
    'created' => 'secondary', 'pending_payment' => 'warning', 'payment_confirmed' => 'info',
    'preparing' => 'primary', 'out_for_delivery' => 'primary', 'delivered' => 'success', 'cancelled' => 'danger',
];

public const PAYMENT_LABELS = [
    'unpaid' => 'Belum dibayar',
    'proof_uploaded' => 'Menunggu verifikasi',
    'confirmed' => 'Lunas',
    'rejected' => 'Bukti ditolak',
];

/** Urutan tahap untuk timeline tracking. */
public const TIMELINE = ['pending_payment', 'payment_confirmed', 'preparing', 'out_for_delivery', 'delivered'];

/** Status berikutnya yang dikerjakan admin. */
public const FLOW = [
    'payment_confirmed' => 'preparing',
    'preparing' => 'out_for_delivery',
    'out_for_delivery' => 'delivered',
];

public function canUploadProof(): bool
{
    return $this->status === 'pending_payment'
        && in_array($this->payment_status, ['unpaid', 'rejected'], true);
}

public function canBeCancelledByCustomer(): bool
{
    return $this->canUploadProof();
}

public function canBeCancelledByAdmin(): bool
{
    return in_array($this->status, ['pending_payment', 'payment_confirmed', 'preparing'], true);
}

public function isAwaitingVerification(): bool
{
    return $this->status === 'pending_payment' && $this->payment_status === 'proof_uploaded';
}

public function nextStatus(): ?string
{
    return self::FLOW[$this->status] ?? null;
}

public function scopeAwaitingVerification(Builder $query): Builder
{
    return $query->where('status', 'pending_payment')->where('payment_status', 'proof_uploaded');
}

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