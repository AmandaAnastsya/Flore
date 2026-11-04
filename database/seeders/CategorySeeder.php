<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'Hand Bouquet'    => 'Buket tangan untuk berbagai momen spesial.',
            'Bloom Box'       => 'Rangkaian bunga dalam box elegan.',
            'Flower Basket'   => 'Rangkaian bunga dalam keranjang.',
            'Wedding Flowers' => 'Bunga untuk pernikahan dan acara resmi.',
        ];

        foreach ($categories as $name => $description) {
            Category::updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'description' => $description, 'is_active' => true]
            );
        }
    }
}