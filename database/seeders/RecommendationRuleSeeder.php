<?php

namespace Database\Seeders;

use App\Models\RecommendationRule;
use Illuminate\Database\Seeder;

class RecommendationRuleSeeder extends Seeder
{
    public function run(): void
    {
        $rules = [
            'occasion' => ['anniversary' => 30, 'birthday' => 30, 'wedding' => 30, 'graduation' => 25, 'apology' => 25, 'grand-opening' => 20],
            'color'    => ['pink' => 20, 'white' => 20, 'lavender' => 20, 'peach' => 20, 'red' => 20],
            'style'    => ['soft-romantic' => 15, 'modern' => 15, 'classic' => 15, 'luxury' => 15],
            'size'     => ['mini' => 10, 'small' => 10, 'medium' => 10, 'large' => 10],
        ];

        foreach ($rules as $criteria => $values) {
            foreach ($values as $value => $weight) {
                RecommendationRule::updateOrCreate(
                    ['criteria' => $criteria, 'value' => $value],
                    ['weight' => $weight, 'is_active' => true]
                );
            }
        }
    }
}