<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Builder;

class Bouquet extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'category_id', 'name', 'slug', 'description', 'price', 'stock',
        'low_stock_threshold', 'image', 'occasion', 'color', 'style',
        'size', 'is_same_day', 'is_active', 'cost_price',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'is_same_day' => 'boolean',
        'is_active' => 'boolean',
    ];

    /** Harga modal hanya untuk admin — jangan pernah ikut terserialisasi ke JSON/halaman customer. */
    protected $hidden = ['cost_price'];

    /** Batas margin (% dari harga jual): sehat ≥ 40, tipis 25–39, rendah < 25. */
    public const MARGIN_HEALTHY = 40;
    public const MARGIN_THIN = 25;

    public function marginAmount(): ?float
    {
        return $this->cost_price === null ? null : (float) $this->price - (float) $this->cost_price;
    }

    public function marginPercent(): ?float
    {
        return $this->cost_price === null || (float) $this->price <= 0
            ? null : round($this->marginAmount() / (float) $this->price * 100, 1);
    }

    /** @return array{0:string,1:string}|null [warna badge, label] */
    public function marginBadge(): ?array
    {
        $m = $this->marginPercent();

        return match (true) {
            $m === null => null,
            $m >= self::MARGIN_HEALTHY => ['success', 'Sehat'],
            $m >= self::MARGIN_THIN => ['warning', 'Tipis'],
            default => ['danger', $m < 0 ? 'Rugi' : 'Rendah'],
        };
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function isLowStock(): bool
    {
        return $this->stock <= $this->low_stock_threshold;
    }
    public function orderItems(): HasMany
{
    return $this->hasMany(OrderItem::class);
}
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->whereHas('category', fn ($q) => $q->where('is_active', true));
    }
protected function imageUrl(): Attribute
{
    return Attribute::get(fn () => $this->image
        ? asset('storage/' . $this->image)
        : asset('images/placeholder-bouquet.svg'));
}

// satu sumber nilai, dipakai form admin dan nanti Recommendation Wizard
public const OCCASIONS = ['anniversary', 'birthday', 'graduation', 'apology', 'grand-opening', 'wedding'];
public const COLORS = ['pink', 'white', 'lavender', 'peach', 'red'];
public const STYLES = ['soft-romantic', 'modern', 'classic', 'luxury'];
public const SIZES = ['mini', 'small', 'medium', 'large'];

// out = habis, low = menipis, ok = aman
protected function stockStatus(): Attribute
{
    return Attribute::get(fn () => match (true) {
        $this->stock <= 0 => 'out',
        $this->stock <= $this->low_stock_threshold => 'low',
        default => 'ok',
    });
}

public function scopeLowStock(Builder $query): Builder
{
    return $query->where('stock', '>', 0)->whereColumn('stock', '<=', 'low_stock_threshold');
}

public function scopeOutOfStock(Builder $query): Builder
{
    return $query->where('stock', '<=', 0);
}
}