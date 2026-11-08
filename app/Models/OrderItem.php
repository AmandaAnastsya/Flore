<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id', 'bouquet_id', 'flower_id', 'bouquet_name', 'flower_name',
        'price', 'quantity', 'subtotal',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function bouquet(): BelongsTo
    {
        return $this->belongsTo(Bouquet::class)->withTrashed();
    }

    public function flower(): BelongsTo
    {
        return $this->belongsTo(Flower::class)->withTrashed();
    }

    public function isStem(): bool
    {
        return $this->flower_id !== null;
    }

    /** Nama snapshot saat pesanan dibuat. */
    protected function name(): Attribute
    {
        return Attribute::get(fn () => $this->bouquet_name ?? $this->flower_name);
    }
}