<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Flower extends Model
{
    use SoftDeletes;

    public const MAX_TYPES = 20;   // batas jenis bunga per tangkai

    protected $fillable = [
        'name', 'slug', 'species', 'color', 'origin', 'description', 'image',
        'price_per_stem', 'bundle_size', 'bundle_price', 'stock', 'reorder_level', 'is_active',
    ];

    protected $casts = [
        'price_per_stem' => 'decimal:2',
        'bundle_price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

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