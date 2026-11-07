<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            AdminSeeder::class,
            CategorySeeder::class,   // harus sebelum BouquetSeeder
            BouquetSeeder::class,
            RecommendationRuleSeeder::class,
            FlowerSeeder::class,
        ]);
    }
}