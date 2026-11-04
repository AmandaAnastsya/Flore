<?php

namespace Database\Seeders;

use App\Models\Bouquet;
use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BouquetSeeder extends Seeder
{
    public function run(): void
    {
        // nama, kategori, deskripsi (isi bunga), harga, stok, occasion, warna, style, size, same-day
        $bouquets = [
            ['The Sweet Blush Romance',      'Hand Bouquet',    'Pink Spray Roses & Peonies',          1150000, 10, 'anniversary',   'pink',     'soft-romantic', 'large',  true],
            ['Soft Lavender Whispers',       'Hand Bouquet',    'Hydrangeas & Eustoma',                 850000,  8, 'birthday',      'lavender', 'soft-romantic', 'medium', true],
            ['Graceful Pure Cashmere',       'Hand Bouquet',    'White Stargazer & Tulips',            1200000,  6, 'anniversary',   'white',    'classic',       'large',  false],
            ['Sunlit Peach Bloom Box',       'Bloom Box',       'Peach Roses & Chamomile',              680000, 12, 'birthday',      'peach',    'modern',        'medium', true],
            ['Velvet Rose & Ribbon Box',     'Bloom Box',       'Crimson Ecuadorian Roses',            1750000,  4, 'anniversary',   'red',      'luxury',        'large',  false],
            ['Blush Satin Hand Bouquet',     'Hand Bouquet',    "Pastel Pink Tulips & Baby's Breath",   750000,  9, 'graduation',    'pink',     'soft-romantic', 'medium', true],
            ['Mini Daisy Delight',           'Hand Bouquet',    "Daisies & Baby's Breath",              320000, 15, 'apology',       'white',    'modern',        'mini',   true],
            ['Petite Peony Posy',            'Hand Bouquet',    'Pink Peonies',                         450000,  3, 'birthday',      'pink',     'soft-romantic', 'small',  true],  // stok rendah
            ['Garden Basket Bliss',          'Flower Basket',   'Mixed Seasonal Blooms',                950000,  7, 'grand-opening', 'peach',    'classic',       'large',  false],
            ['Pastel Dream Basket',          'Flower Basket',   'Hydrangeas & Roses',                   600000,  0, 'apology',       'lavender', 'soft-romantic', 'medium', false], // stok habis
            ['Eternal White Wedding Bouquet','Wedding Flowers', 'White Roses & Lisianthus',            1500000,  5, 'wedding',       'white',    'classic',       'large',  false],
            ['Rustic Blush Bridal Bouquet',  'Wedding Flowers', 'Garden Roses & Eucalyptus',           1350000,  5, 'wedding',       'pink',     'modern',        'large',  false],
        ];

        foreach ($bouquets as [$name, $category, $desc, $price, $stock, $occasion, $color, $style, $size, $sameDay]) {
            Bouquet::updateOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'category_id' => Category::where('name', $category)->value('id'),
                    'name' => $name,
                    'description' => $desc,
                    'price' => $price,
                    'stock' => $stock,
                    'occasion' => $occasion,
                    'color' => $color,
                    'style' => $style,
                    'size' => $size,
                    'is_same_day' => $sameDay,
                    'is_active' => true,
                ]
            );
        }
    }
}