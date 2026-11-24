<?php

namespace Database\Seeders;

use App\Models\Bouquet;
use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Katalog bouquet final Floré.
 * Aman dijalankan ulang: nama, harga, deskripsi & atribut disinkronkan, tetapi STOK hanya
 * diisi saat bouquet pertama kali dibuat — stok yang sudah berjalan tidak pernah ditimpa.
 * Foto tidak diatur di sini (php artisan bouquets:import-photos).
 */
class BouquetSeeder extends Seeder
{
    /** nama, kategori, isi bunga, harga, stok awal, occasion, warna, style, ukuran, same-day */
    public const CATALOG = [
        ['The Sweet Blush Romance',       'Hand Bouquet',    'Pink Spray Roses & Peonies',          1150000, 10, 'anniversary',   'pink',     'soft-romantic', 'large',  true],
        ['Soft Lavender Whispers',        'Hand Bouquet',    'Hydrangeas & Eustoma',                 850000, 10, 'birthday',      'lavender', 'soft-romantic', 'medium', true],
        ['Graceful Pure Cashmere',        'Hand Bouquet',    'White Stargazer & Tulips',            1200000,  8, 'anniversary',   'white',    'classic',       'large',  false],
        ['Sunlit Peach Bloom Box',        'Bloom Box',       'Peach Roses & Chamomile',              680000, 12, 'birthday',      'peach',    'modern',        'medium', true],
        ['Velvet Rose & Ribbon Box',      'Bloom Box',       'Crimson Ecuadorian Roses',            1750000,  8, 'anniversary',   'red',      'luxury',        'large',  false],
        ['Blush Satin Hand Bouquet',      'Hand Bouquet',    "Pastel Pink Tulips & Baby's Breath",   750000, 10, 'graduation',    'pink',     'soft-romantic', 'medium', true],
        ['Mini Daisy Delight',            'Hand Bouquet',    "Daisies & Baby's Breath",              320000, 15, 'apology',       'white',    'modern',        'mini',   true],
        ['Petite Peony Posy',             'Hand Bouquet',    'Pink Peonies',                         450000, 10, 'birthday',      'pink',     'soft-romantic', 'small',  true],
        ['Garden Basket Bliss',           'Flower Basket',   'Mixed Seasonal Blooms',                950000,  8, 'grand-opening', 'peach',    'classic',       'large',  false],
        ['Pastel Dream Basket',           'Flower Basket',   'Hydrangeas & Roses',                   600000, 10, 'apology',       'lavender', 'soft-romantic', 'medium', false],
        ['Eternal White Wedding Bouquet', 'Wedding Flowers', 'White Roses & Lisianthus',            1500000,  8, 'wedding',       'white',    'classic',       'large',  false],
        ['Rustic Blush Bridal Bouquet',   'Wedding Flowers', 'Garden Roses & Eucalyptus',           1350000,  8, 'wedding',       'pink',     'modern',        'large',  false],
    ];

    public const LOW_STOCK_THRESHOLD = 5;

    public function run(): void
    {
        $categories = Category::pluck('id', 'name');

        foreach (self::CATALOG as [$name, $category, $desc, $price, $stock, $occasion, $color, $style, $size, $sameDay]) {
            $bouquet = Bouquet::withTrashed()->firstOrNew(['slug' => Str::slug($name)]);

            $bouquet->fill([
                'category_id' => $categories[$category],
                'name' => $name,
                'description' => $desc,
                'price' => $price,
                'occasion' => $occasion,
                'color' => $color,
                'style' => $style,
                'size' => $size,
                'is_same_day' => $sameDay,
                'is_active' => true,
            ]);

            if (! $bouquet->exists) {
                $bouquet->stock = $stock;
                $bouquet->low_stock_threshold = self::LOW_STOCK_THRESHOLD;
            }

            $bouquet->save();
        }
    }
}
