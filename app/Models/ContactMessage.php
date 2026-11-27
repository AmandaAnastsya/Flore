<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Pesan dari halaman Contact Us (Use Case hal. 34). */
class ContactMessage extends Model
{
    public const TOPICS = [
        'order' => 'Pesanan & pengiriman',
        'payment' => 'Pembayaran',
        'custom' => 'Pesanan khusus / custom bouquet',
        'complaint' => 'Keluhan & klaim garansi',
        'other' => 'Lainnya',
    ];

    public const STATUSES = [
        'new' => ['Baru', 'danger'],
        'replied' => ['Sudah dibalas', 'success'],
        'closed' => ['Selesai', 'secondary'],
    ];

    protected $fillable = ['user_id', 'name', 'email', 'phone', 'topic', 'order_number', 'message'];

    protected $casts = ['handled_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Pesanan yang dirujuk, hanya bila memang milik pengirim (atau pengirim tamu dengan email yang sama). */
    public function order(): ?Order
    {
        if (! $this->order_number) {
            return null;
        }

        return Order::where('order_number', $this->order_number)
            ->whereHas('user', fn ($u) => $u->where('email', $this->email)->orWhere('id', $this->user_id))
            ->first();
    }
}
