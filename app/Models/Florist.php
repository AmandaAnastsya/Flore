<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Florist extends Model
{
    protected $fillable = ['name', 'phone', 'specialty', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    /** Default sama dengan kolom database, supaya model yang baru dibuat langsung dianggap aktif. */
    protected $attributes = ['is_active' => true];

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
