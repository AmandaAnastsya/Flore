<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Biaya operasional di luar harga modal bunga (HPP sudah dihitung dari tiap pesanan). */
class Expense extends Model
{
    public const CATEGORIES = [
        'gaji' => 'Gaji & upah',
        'sewa' => 'Sewa tempat',
        'utilitas' => 'Listrik, air & internet',
        'transportasi' => 'Transportasi & bensin',
        'marketing' => 'Marketing & iklan',
        'perlengkapan' => 'Perlengkapan & peralatan',
        'lainnya' => 'Lainnya',
    ];

    protected $fillable = ['spent_on', 'category', 'description', 'amount', 'user_id'];

    protected $casts = ['spent_on' => 'date', 'amount' => 'decimal:2'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
