<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Concerns\HasInventoryLevel;

class Flower extends Model
{
    use SoftDeletes, HasInventoryLevel;

    public const MAX_TYPES = 20;   // batas jenis bunga per tangkai

    protected $fillable = [
        'name', 'slug', 'species', 'color', 'origin', 'description', 'image',
        'price_per_stem', 'bundle_size', 'bundle_price', 'stock', 'reorder_level', 'is_active',
        'cost_per_stem', 'shelf_life_days',
    ];

    protected $casts = [
        'price_per_stem' => 'decimal:2',
        'bundle_price' => 'decimal:2',
        'cost_per_stem' => 'decimal:2',
        'is_active' => 'boolean',
        'last_restocked_at' => 'datetime',
    ];

    /** HPP hanya untuk admin. */
    protected $hidden = ['cost_per_stem'];

    public function unitCost(): ?float
    {
        return $this->cost_per_stem === null ? null : (float) $this->cost_per_stem;
    }

    public function shelfLife(): int
    {
        return (int) ($this->shelf_life_days ?: config('flore.inventory.shelf_life_days', 7));
    }

    /** Umur bunga sejak tiba (hari); null bila tanggal tiba belum tercatat. */
    public function ageDays(): ?int
    {
        return $this->last_restocked_at ? (int) $this->last_restocked_at->copy()->startOfDay()->diffInDays(today()) : null;
    }

    public function remainingDays(): ?int
    {
        return $this->ageDays() === null ? null : $this->shelfLife() - $this->ageDays();
    }

    /** Kesegaran 0–100% (sisa umur segar dibanding umur segar penuh). */
    public function freshnessPercent(): ?int
    {
        $left = $this->remainingDays();

        return $left === null ? null : (int) max(0, min(100, round($left / $this->shelfLife() * 100)));
    }

    /** fresh | aging | expired | unknown */
    public function freshnessState(): string
    {
        $left = $this->remainingDays();

        return match (true) {
            $left === null => 'unknown',
            $left <= 0 => 'expired',
            $left <= max(1, intdiv($this->shelfLife(), 3)) => 'aging',
            default => 'fresh',
        };
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    protected function imageUrl(): Attribute
    {
        return Attribute::get(fn () => $this->image
            ? asset('storage/' . $this->image)
            : asset('images/placeholder-bouquet.svg'));
    }

    // out = habis, low = menipis, ok = aman
    protected function stockStatus(): Attribute
    {
        return Attribute::get(fn () => match (true) {
            $this->stock <= 0 => 'out',
            $this->stock <= $this->reorder_level => 'low',
            default => 'ok',
        });
    }

    /** Harga untuk sekian tangkai: paket penuh dihitung harga paket, sisanya per tangkai. */
    public function priceFor(int $stems): float
    {
        if ($stems <= 0) {
            return 0.0;
        }

        $perStem = (float) $this->price_per_stem;
        $plain = $stems * $perStem;

        if (! $this->bundle_size || $this->bundle_size < 2 || $this->bundle_price === null) {
            return round($plain, 2);
        }

        $bundled = intdiv($stems, $this->bundle_size) * (float) $this->bundle_price
            + ($stems % $this->bundle_size) * $perStem;

        return round(min($plain, $bundled), 2);   // paket tidak pernah lebih mahal dari satuan
    }

    /** Hemat berapa untuk satu paket penuh dibanding beli satuan. */
    public function bundleSavings(): float
    {
        if (! $this->bundle_size || $this->bundle_price === null) {
            return 0.0;
        }

        return max(0.0, round($this->bundle_size * (float) $this->price_per_stem - (float) $this->bundle_price, 2));
    }
}