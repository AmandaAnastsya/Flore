<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Buku Alamat customer (Site Map hal. 33: Profile → Addresses). */
class Address extends Model
{
    public const MAX_PER_USER = 10;

    protected $fillable = ['label', 'recipient_name', 'recipient_phone', 'city', 'address', 'is_default'];

    protected $casts = ['is_default' => 'boolean'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Data untuk mengisi form checkout. */
    public function toCheckout(): array
    {
        return [
            'recipient_name' => $this->recipient_name,
            'recipient_phone' => $this->recipient_phone,
            'delivery_city' => $this->city,
            'delivery_address' => $this->address,
        ];
    }
}
