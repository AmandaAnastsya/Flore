<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = ['order_id', 'bouquet_id', 'bouquet_name', 'price', 'quantity', 'subtotal'];

    protected $casts = ['price' => 'decimal:2', 'subtotal' => 'decimal:2'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function bouquet(): BelongsTo
    {
        return $this->belongsTo(Bouquet::class)->withTrashed();
    }
}