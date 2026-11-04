<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bouquet extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'category_id', 'name', 'slug', 'description', 'price', 'stock',
        'low_stock_threshold', 'image', 'occasion', 'color', 'style',
        'size', 'is_same_day', 'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_same_day' => 'boolean',
        'is_active' => 'boolean',
    ];

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
protected function imageUrl(): Attribute
{
    return Attribute::get(fn () => $this->image
        ? asset('storage/' . $this->image)
        : asset('images/placeholder-bouquet.svg'));
}
}