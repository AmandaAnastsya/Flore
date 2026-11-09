<?php

namespace App\Services;

use App\Models\Bouquet;
use App\Models\RecommendationRule;
use Illuminate\Support\Collection;

class RecommendationEngine
{
    /** Kriteria yang punya bobot di tabel recommendation_rules. */
    public const CRITERIA = ['occasion', 'color', 'style', 'size'];

    /** Rentang harga: min termasuk, max tidak termasuk (null = tanpa batas atas). */
    public const BUDGETS = [
        'under-200' => ['label' => 'Di bawah Rp 200.000',      'min' => 0,      'max' => 200000],
        '200-350'   => ['label' => 'Rp 200.000 – Rp 350.000',  'min' => 200000, 'max' => 350000],
        '350-500'   => ['label' => 'Rp 350.000 – Rp 500.000',  'min' => 350000, 'max' => 500000],
        '500-750'   => ['label' => 'Rp 500.000 – Rp 750.000',  'min' => 500000, 'max' => 750000],
        'over-750'  => ['label' => 'Di atas Rp 750.000',       'min' => 750000, 'max' => null],
    ];

    public const RELAX = 0.2;          // pelonggaran budget saat tidak ada yang cocok
    public const DEFAULT_LIMIT = 6;
    public const MAX_LIMIT = 24;

    private const MESSAGES = [
        'exact' => 'Rekomendasi terbaik untukmu',
        'relaxed_budget' => 'Belum ada yang pas di budget itu. Ini yang paling mendekati.',
        'alternatives' => 'Belum ada yang cocok dengan pilihanmu. Ini bouquet populer di budgetmu.',
        'browse' => 'Bouquet populer untukmu',
        'none' => 'Belum ada bouquet yang tersedia untuk saat ini.',
    ];

    private const LABELS = [
        'occasion' => 'Cocok untuk %s',
        'color' => 'Warna %s',
        'style' => 'Gaya %s',
        'size' => 'Ukuran %s',
    ];

    /**
     * @param  array<string,mixed>  $answers  occasion, color, style, size, budget (semua opsional)
     */
    public function recommend(array $answers, int $limit = self::DEFAULT_LIMIT): RecommendationResult
    {
        $limit = max(1, min(self::MAX_LIMIT, $limit));
        $weights = $this->weights();

        [$criteria, $ignored] = $this->pickCriteria($answers, $weights);
        [$budgetKey, $range] = $this->pickBudget($answers, $ignored);

        // Tangga fallback: mulai dari yang paling ketat
        $attempts = [];
        if ($criteria) {
            $attempts[] = ['exact', $range, true];
            if ($range) {
                $attempts[] = ['relaxed_budget', $this->relax($range), true];
            }
        }
        $attempts[] = [$criteria ? 'alternatives' : 'browse', $range, false];

        foreach ($attempts as [$mode, $searchRange, $requireMatch]) {
            $items = $this->rank($criteria, $weights, $searchRange, $range, $requireMatch);

            if ($items->isNotEmpty()) {
                return new RecommendationResult(
                    $mode, self::MESSAGES[$mode], $items->take($limit)->values(),
                    $items->count(), $criteria, $budgetKey, $ignored
                );
            }
        }

        return new RecommendationResult('none', self::MESSAGES['none'], collect(), 0, $criteria, $budgetKey, $ignored);
    }

    /**
     * Pilihan untuk form wizard, hanya dari rule yang aktif (urut bobot terbesar).
     *
     * @return array<string,array>
     */
    public function options(): array
    {
        $weights = $this->weights();
        $options = [];

        foreach (self::CRITERIA as $criteria) {
            $values = $weights[$criteria] ?? [];
            arsort($values);
            $options[$criteria] = array_keys($values);
        }

        $options['budget'] = array_map(fn ($b) => $b['label'], self::BUDGETS);

        return $options;
    }

    // --------------------------------------------------------------- internal

    /** @return array<string,array<string,int>> [criteria => [value => weight]], hanya rule aktif */
    private function weights(): array
    {
        return RecommendationRule::where('is_active', true)->get()
            ->groupBy('criteria')
            ->map(fn ($rules) => $rules->pluck('weight', 'value')->map(fn ($w) => (int) $w)->all())
            ->all();
    }

    /** @return array{0: array<string,string>, 1: array<string,string>} [dipakai, diabaikan] */
    private function pickCriteria(array $answers, array $weights): array
    {
        $criteria = [];
        $ignored = [];

        foreach (self::CRITERIA as $name) {
            $value = $answers[$name] ?? null;

            if ($value === null || $value === '') {
                continue;
            }

            if (is_string($value) && isset($weights[$name][$value])) {
                $criteria[$name] = $value;
            } else {
                $ignored[$name] = is_scalar($value) ? (string) $value : '(tidak valid)';
            }
        }

        return [$criteria, $ignored];
    }

    /** @return array{0: ?string, 1: ?array} [kunci, rentang] */
    private function pickBudget(array $answers, array &$ignored): array
    {
        $value = $answers['budget'] ?? null;

        if ($value === null || $value === '') {
            return [null, null];
        }

        if (is_string($value) && isset(self::BUDGETS[$value])) {
            return [$value, self::BUDGETS[$value]];
        }

        $ignored['budget'] = is_scalar($value) ? (string) $value : '(tidak valid)';

        return [null, null];
    }

    private function relax(array $range): array
    {
        return [
            'min' => (int) floor($range['min'] * (1 - self::RELAX)),
            'max' => $range['max'] === null ? null : (int) ceil($range['max'] * (1 + self::RELAX)),
        ];
    }

    private function inRange(float $price, array $range): bool
    {
        return $price >= $range['min'] && ($range['max'] === null || $price < $range['max']);
    }

    /**
     * @param  ?array  $searchRange  rentang untuk query (bisa sudah dilonggarkan)
     * @param  ?array  $budgetRange  budget asli, untuk menandai within_budget
     */
    private function rank(array $criteria, array $weights, ?array $searchRange, ?array $budgetRange, bool $requireMatch): Collection
    {
        $query = Bouquet::visible()
            ->where('stock', '>', 0)
            ->with('category')
            ->withSum('orderItems as sold_count', 'quantity');

        if ($searchRange) {
            $query->where('price', '>=', $searchRange['min']);

            if ($searchRange['max'] !== null) {
                $query->where('price', '<', $searchRange['max']);
            }
        }

        $items = $query->get()->map(fn ($b) => $this->score($b, $criteria, $weights, $budgetRange));

        if ($requireMatch) {
            $items = $items->filter(fn ($i) => count($i['matched']) > 0);
        }

        // skor ↓, masuk budget ↓, terlaris ↓, harga ↑, id ↑ (urutan selalu deterministik)
        return $items->sort(fn ($x, $y) => [$y['score'], $y['within_budget'], $y['sold'], $x['price'], $x['bouquet']->id]
            <=> [$x['score'], $x['within_budget'], $x['sold'], $y['price'], $y['bouquet']->id])->values();
    }

    private function score(Bouquet $bouquet, array $criteria, array $weights, ?array $budgetRange): array
    {
        $score = 0;
        $max = 0;
        $matched = [];
        $missed = [];

        foreach ($criteria as $name => $value) {
            $weight = $weights[$name][$value];
            $max += $weight;

            if ((string) $bouquet->{$name} === $value) {
                $score += $weight;
                $matched[] = $name;
            } else {
                $missed[] = $name;
            }
        }

        $price = (float) $bouquet->price;
        $withinBudget = $budgetRange === null || $this->inRange($price, $budgetRange);

        return [
            'bouquet' => $bouquet,
            'price' => $price,
            'score' => $score,
            'max_score' => $max,
            'percent' => $max > 0 ? (int) round($score / $max * 100) : 0,
            'matched' => $matched,
            'missed' => $missed,
            'is_perfect' => $criteria !== [] && $missed === [],
            'within_budget' => $withinBudget,
            'sold' => (int) ($bouquet->sold_count ?? 0),
            'reasons' => $this->reasons($criteria, $matched, $budgetRange, $withinBudget),
        ];
    }

    private function reasons(array $criteria, array $matched, ?array $budgetRange, bool $withinBudget): array
    {
        $reasons = [];

        foreach ($matched as $name) {
            $reasons[] = sprintf(self::LABELS[$name], ucfirst(str_replace('-', ' ', $criteria[$name])));
        }

        if ($budgetRange) {
            $reasons[] = $withinBudget ? 'Sesuai budget' : 'Sedikit di luar budget';
        }

        return $reasons;
    }
}