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
        // logistik — sengaja TIDAK fillable; hanya diubah lewat App\Services\DeliveryDispatch
        'dispatched_at' => 'datetime',
        'stage_updated_at' => 'datetime',
        'qc_passed_at' => 'datetime',
        'eta_at' => 'datetime',
        'delivered_at' => 'datetime',
        'issue_reported_at' => 'datetime',
    ];

    /** Status pada halaman Logistics & Courier Dispatch: [label, warna badge]. */
    public const DISPATCH_STATES = [
        'not_prepared' => ['Belum dirangkai', 'secondary'],
        'waiting_courier' => ['Menunggu Kurir', 'danger'],
        'ready_pickup' => ['Siap Pick-up', 'plum'],
        'on_the_way' => ['Dalam Perjalanan', 'success'],
        'late' => ['Melewati ETA', 'danger'],
        'delivered' => ['Terkirim', 'success'],
    ];

    public function courier(): BelongsTo
    {
        return $this->belongsTo(Courier::class);
    }

    /** Tahap produksi di studio selama status "Sedang disiapkan" (mockup hal. 48). */
    public const STAGES = [
        'arranging' => 'Potong & Rangkai',
        'qc' => 'QC & Foto Pembeli',
        'packing' => 'Packing Box',
        'ready' => 'Siap Handover Kurir',
    ];

    public function florist(): BelongsTo
    {
        return $this->belongsTo(Florist::class);
    }

    public function nextStage(): ?string
    {
        if ($this->status !== 'preparing') {
            return null;
        }
        $keys = array_keys(self::STAGES);
        $i = array_search($this->fulfillment_stage, $keys, true);

        return $i === false ? $keys[0] : ($keys[$i + 1] ?? null);
    }

    public function stageLabel(): ?string
    {
        return self::STAGES[$this->fulfillment_stage] ?? null;
    }

    public function dispatchState(): ?string
    {
        return match ($this->status) {
            'payment_confirmed' => 'not_prepared',
            'preparing' => $this->courier_id ? 'ready_pickup' : 'waiting_courier',
            'out_for_delivery' => $this->isLate() ? 'late' : 'on_the_way',
            'delivered' => 'delivered',
            default => null,
        };
    }

    /** Sedang dikirim dan sudah melewati perkiraan waktu tiba. */
    public function isLate(): bool
    {
        return $this->status === 'out_for_delivery' && $this->eta_at && now()->greaterThan($this->eta_at);
    }

    public function hasOpenIssue(): bool
    {
        return $this->delivery_issue !== null && $this->status === 'out_for_delivery';
    }

    /** Batas akhir slot pengiriman yang dijanjikan ke customer (slot 3 jam). */
    public function slotEndsAt(): ?\Illuminate\Support\Carbon
    {
        $start = \App\Services\DeliverySchedule::SLOTS[$this->delivery_slot]['start'] ?? null;

        return $start === null || ! $this->delivery_date ? null
            : $this->delivery_date->copy()->setTime($start + 3, 0);
    }

    public function deliveredOnTime(): ?bool
    {
        $end = $this->slotEndsAt();

        return $this->delivered_at && $end ? $this->delivered_at->lessThanOrEqualTo($end) : null;
    }

    /**
     * Perkiraan progres perjalanan 0..1 dari jam berangkat & ETA.
     * Ini estimasi waktu, BUKAN posisi GPS (pelacakan real-time di luar scope proposal §1.3).
     */
    public function estimatedProgress(): float
    {
        if ($this->status === 'delivered') {
            return 1.0;
        }
        if ($this->status !== 'out_for_delivery' || ! $this->dispatched_at || ! $this->eta_at) {
            return 0.0;
        }

        $total = max(1, $this->dispatched_at->diffInSeconds($this->eta_at));
        $done = $this->dispatched_at->diffInSeconds(now(), false);

        return max(0.0, min(0.95, $done / $total));   // tidak pernah "sampai" sebelum dikonfirmasi
    }

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

/**
 * Batas waktu pembayaran (State Machine hal. 39: autoCancel [payment timeout]).
 * Untuk bukti yang ditolak, customer mendapat waktu yang sama sejak penolakan.
 */
public function paymentDeadline(): ?\Illuminate\Support\Carbon
{
    if (! $this->canUploadProof() || ! $this->created_at) {
        return null;
    }

    $hours = max(1, (int) config('flore.payment.timeout_hours', 24));
    $base = $this->payment_status === 'rejected' && $this->updated_at ? $this->created_at->max($this->updated_at) : $this->created_at;

    return $base->copy()->addHours($hours);
}

public function isPaymentOverdue(): bool
{
    $deadline = $this->paymentDeadline();

    return $deadline !== null && now()->greaterThanOrEqualTo($deadline);
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

    public function testimonial(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Testimonial::class);
    }

    /** Pendapatan sah: pembayaran dikonfirmasi admin dan pesanan tidak dibatalkan. */
    public function scopePaid(Builder $query): Builder
    {
        return $query->where('payment_status', 'confirmed')->where('status', '!=', 'cancelled');
    }
}