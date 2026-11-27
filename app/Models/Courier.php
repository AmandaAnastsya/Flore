<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Courier extends Model
{
    protected $fillable = ['name', 'phone', 'vehicle', 'plate_number', 'notes', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public const VEHICLES = [
        'motor' => 'Motor (Dedicated Bike)',
        'mobil' => 'Mobil (Climate Car)',
        'instan' => 'Kurir Instan (Gojek/Grab)',
    ];

    /** Pilihan estimasi waktu tempuh saat menugaskan kurir (menit). */
    public const TRAVEL_OPTIONS = [20, 30, 45, 60, 90, 120];

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function vehicleLabel(): string
    {
        return self::VEHICLES[$this->vehicle] ?? $this->vehicle;
    }

    /** Nomor WhatsApp internasional: 0812… → 62812… */
    public function whatsappNumber(): string
    {
        $digits = preg_replace('/\D/', '', $this->phone);

        return str_starts_with($digits, '0') ? '62' . substr($digits, 1) : $digits;
    }

    public function whatsappUrl(?string $message = null): string
    {
        return 'https://wa.me/' . $this->whatsappNumber() . ($message ? '?text=' . rawurlencode($message) : '');
    }
}
