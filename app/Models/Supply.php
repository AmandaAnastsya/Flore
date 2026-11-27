<?php

namespace App\Models;

use App\Models\Concerns\HasInventoryLevel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Bahan pendukung: pita & wrapping paper, vas & aksesoris. */
class Supply extends Model
{
    use HasInventoryLevel, SoftDeletes;

    public const TYPES = [
        'wrapping' => 'Pita & Wrapping Paper',
        'vase' => 'Vas & Aksesoris',
    ];

    public const UNITS = ['meter', 'lembar', 'roll', 'pcs', 'set'];

    protected $fillable = ['type', 'name', 'variant', 'unit', 'stock', 'reorder_level', 'cost_per_unit', 'is_active'];

    protected $casts = ['cost_per_unit' => 'decimal:2', 'is_active' => 'boolean'];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function unitCost(): ?float
    {
        return $this->cost_per_unit === null ? null : (float) $this->cost_per_unit;
    }
}
