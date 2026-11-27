<?php

namespace App\Services;

use App\Models\RecommendationRule;
use Database\Seeders\RecommendationRuleSeeder;
use Illuminate\Support\Facades\DB;

/**
 * Rule Engine Configurator (no-code): bobot per faktor dalam persen, total 100.
 * Disimpan ke tabel recommendation_rules yang sudah dipakai RecommendationEngine.
 */
class WizardWeights
{
    /** kunci => [judul, sub-judul, penjelasan] */
    public const FACTORS = [
        'occasion' => ['Kesesuaian Momen', 'Occasion Alignment', 'Prioritas rekomendasi berdasarkan kecocokan momen acara.'],
        'color' => ['Preferensi Warna & Estetika', 'Palette Match', 'Sensitivitas kecocokan warna yang dipilih customer.'],
        'style' => ['Gaya Rangkaian', 'Style Match', 'Kecocokan gaya: soft romantic, modern, classic, luxury.'],
        'size' => ['Ukuran Bouquet', 'Size Match', 'Kecocokan ukuran: mini sampai large.'],
        'margin' => ['Optimasi Profit Margin', 'Margin Weight', 'Memprioritaskan bouquet dengan margin keuntungan lebih tinggi (hanya yang HPP-nya sudah diisi).'],
        'stock' => ['Ketersediaan Stok Real-time', 'Inventory Safety', 'Mendahulukan bouquet yang stoknya aman, menghindari stok menipis.'],
    ];

    public const MIN = ['occasion' => 5, 'color' => 5, 'style' => 5, 'size' => 5, 'margin' => 0, 'stock' => 0];

    /** Bobot saat ini dalam persen (dinormalisasi ke total 100). */
    public function current(): array
    {
        $raw = [];
        foreach (array_keys(self::FACTORS) as $factor) {
            $raw[$factor] = (int) RecommendationRule::where('criteria', $factor)->where('is_active', true)->max('weight');
        }

        return $this->normalize($raw);
    }

    /** true bila admin pernah menyimpan bobot lewat configurator (bukan bawaan seeder). */
    public function isCustom(): bool
    {
        return RecommendationRule::where(RecommendationEngine::STOCK_RULE)->exists();
    }

    /** @param array<string,int> $percent total harus 100 (divalidasi di controller) */
    public function save(array $percent): void
    {
        DB::transaction(function () use ($percent) {
            foreach (RecommendationEngine::CRITERIA as $criteria) {
                RecommendationRule::where('criteria', $criteria)->update(['weight' => $percent[$criteria]]);
            }

            RecommendationRule::updateOrCreate(RecommendationEngine::STOCK_RULE, ['weight' => $percent['stock'], 'is_active' => true]);
            RecommendationRule::updateOrCreate(RecommendationEngine::MARGIN_RULE, ['weight' => $percent['margin'], 'is_active' => true]);
        });
    }

    /** Kembali ke bobot bawaan seeder (per pilihan) dan matikan faktor stok. */
    public function reset(): void
    {
        DB::transaction(function () {
            RecommendationRule::where(RecommendationEngine::STOCK_RULE)->delete();
            RecommendationRule::where(RecommendationEngine::MARGIN_RULE)->delete();
            app(RecommendationRuleSeeder::class)->run();
        });
    }

    /** Pembulatan "largest remainder" supaya jumlahnya tepat 100. */
    private function normalize(array $raw): array
    {
        $sum = array_sum($raw);
        if ($sum <= 0) {
            return array_map(fn () => 0, $raw);
        }

        $exact = array_map(fn ($v) => $v / $sum * 100, $raw);
        $result = array_map('floor', $exact);
        $left = 100 - array_sum($result);

        $remainders = array_map(fn ($e, $r) => $e - $r, $exact, $result);
        $keys = array_keys($exact);
        arsort($remainders);
        foreach (array_slice(array_keys($remainders), 0, (int) $left) as $i) {
            $result[$keys[$i]]++;
        }

        return array_map('intval', $result);
    }
}
