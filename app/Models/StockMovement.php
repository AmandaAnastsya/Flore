<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    public const REASONS = [
        'restock' => 'Restock / barang masuk',
        'adjustment' => 'Koreksi stok',
        'waste' => 'Dibuang (layu/rusak)',
    ];

    public const ITEM_TYPES = ['flower' => 'Bunga per tangkai', 'supply' => 'Supplies', 'bouquet' => 'Bouquet'];

    protected $fillable = ['item_type', 'item_id', 'item_name', 'reason', 'change', 'stock_after', 'unit_cost', 'note', 'user_id'];

    protected $casts = ['unit_cost' => 'decimal:2'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Nilai pergerakan (qty × HPP) bila HPP tercatat. */
    public function value(): ?float
    {
        return $this->unit_cost === null ? null : abs($this->change) * (float) $this->unit_cost;
    }
}
