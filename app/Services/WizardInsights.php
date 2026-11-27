<?php

namespace App\Services;

use App\Models\Bouquet;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\WizardQuery;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Smart Wizard & AI Insights (proposal hal. 51).
 * Semua angka dihitung dari data nyata: wizard_queries (pencarian) dan orders (pembelian).
 * "Insight" di sini adalah ringkasan berbasis aturan, bukan machine learning (proposal §2.4).
 */
class WizardInsights
{
    public const PERIODS = [30 => '30 hari', 90 => '90 hari'];

    private Carbon $from;
    private Carbon $prevFrom;

    public function __construct(private int $days = 30)
    {
        $this->days = array_key_exists($days, self::PERIODS) ? $days : 30;
        $this->from = today()->subDays($this->days - 1);
        $this->prevFrom = $this->from->copy()->subDays($this->days);
    }

    public function days(): int
    {
        return $this->days;
    }

    private function queries()
    {
        return WizardQuery::where('created_at', '>=', $this->from);
    }

    public function summary(): array
    {
        $total = $this->queries()->count();
        $exact = $this->queries()->where('mode', 'exact')->count();
        $previous = WizardQuery::whereBetween('created_at', [$this->prevFrom, $this->from])->count();

        return [
            'total' => $total,
            'change' => $previous ? (int) round(($total - $previous) / $previous * 100) : null,
            'users' => $this->queries()->whereNotNull('user_id')->distinct()->count('user_id'),
            'conversion' => app(SalesAnalytics::class)->wizardConversion($this->from),
            'exact_rate' => $total ? (int) round($exact / $total * 100) : null,
            'gaps' => $total - $exact,
        ];
    }

    /** Porsi pilihan untuk satu kriteria wizard, terbesar dulu. */
    public function share(string $field, int $limit = 5): Collection
    {
        if (! in_array($field, ['occasion', 'color', 'style', 'size', 'budget'], true)) {
            throw new \InvalidArgumentException("Kolom tidak dikenal: {$field}");
        }

        $counts = $this->queries()->whereNotNull($field)->pluck($field)->countBy()->sortDesc();
        $sum = max(1, $counts->sum());

        return $counts->take($limit)->map(fn ($n, $value) => [
            'value' => $value, 'total' => $n, 'percent' => (int) round($n / $sum * 100),
        ])->values();
    }

    /**
     * "Kombinasi Bunga Populer": bouquet yang paling sering direkomendasikan wizard,
     * beserta berapa yang akhirnya dipesan oleh customer yang menerima rekomendasi itu.
     */
    public function popularCombos(int $limit = 4): Collection
    {
        $queries = $this->queries()->whereNotNull('bouquet_ids')->get(['user_id', 'bouquet_ids', 'created_at']);

        $counts = $queries->flatMap(fn ($q) => $q->bouquet_ids ?? [])->countBy()->sortDesc()->take($limit);
        if ($counts->isEmpty()) {
            return collect();
        }

        $bouquets = Bouquet::withTrashed()->whereIn('id', $counts->keys())->get()->keyBy('id');

        return $counts->map(function ($n, $id) use ($queries, $bouquets) {
            // customer yang direkomendasikan bouquet ini → kapan pertama kali
            $firstSeen = $queries->filter(fn ($q) => $q->user_id && in_array($id, $q->bouquet_ids ?? [], true))
                ->groupBy('user_id')->map(fn ($g) => $g->min('created_at'));

            $ordered = $firstSeen->isEmpty() ? 0 : OrderItem::where('bouquet_id', $id)
                ->whereHas('order', fn ($o) => $o->whereIn('user_id', $firstSeen->keys())->where('status', '!=', 'cancelled')
                    ->where('created_at', '>=', $this->from))
                ->with('order:id,user_id,created_at')->get()
                ->filter(fn ($item) => $item->order->created_at->gte($firstSeen[$item->order->user_id]))
                ->count();

            $b = $bouquets[$id] ?? null;

            return [
                'name' => $b?->name ?? 'Bouquet dihapus',
                'flowers' => $b?->description,
                'recommended' => $n,
                'ordered' => $ordered,
            ];
        })->values();
    }

    /**
     * "Rata-rata budget per momen": nilai pesanan lunas per occasion,
     * dibanding periode sebelumnya, plus rentang budget yang paling sering dipilih di wizard.
     */
    public function budgetByOccasion(int $limit = 5): Collection
    {
        $avg = fn (Carbon $from, Carbon $to) => Order::paid()->whereNotNull('occasion')->whereBetween('created_at', [$from, $to])
            ->get(['occasion', 'total_amount'])->groupBy('occasion')
            ->map(fn ($g) => ['avg' => (float) $g->avg('total_amount'), 'orders' => $g->count()]);

        $now = $avg($this->from, now());
        $before = $avg($this->prevFrom, $this->from);

        $wizardBudget = $this->queries()->whereNotNull('occasion')->whereNotNull('budget')->get(['occasion', 'budget'])
            ->groupBy('occasion')->map(fn ($g) => $g->pluck('budget')->countBy()->sortDesc()->keys()->first());

        return $now->sortByDesc('avg')->take($limit)->map(fn ($row, $occasion) => [
            'occasion' => $occasion,
            'avg' => $row['avg'],
            'orders' => $row['orders'],
            'change' => isset($before[$occasion]) && $before[$occasion]['avg'] > 0
                ? (int) round(($row['avg'] - $before[$occasion]['avg']) / $before[$occasion]['avg'] * 100) : null,
            'wizard_budget' => isset($wizardBudget[$occasion]) ? RecommendationEngine::BUDGETS[$wizardBudget[$occasion]]['label'] ?? null : null,
        ])->values();
    }

    /** Kombinasi yang dicari customer tetapi tidak ada bouquet yang cocok persis — peluang produk baru. */
    public function demandGaps(int $limit = 5): Collection
    {
        return $this->queries()->where('mode', '!=', 'exact')->get(['occasion', 'color', 'style', 'size', 'budget'])
            ->map(fn ($q) => collect(['occasion' => $q->occasion, 'color' => $q->color, 'style' => $q->style, 'size' => $q->size])->filter()->all()
                + ($q->budget ? ['budget' => $q->budget] : []))
            ->filter()
            ->countBy(fn ($c) => json_encode($c))->sortDesc()->take($limit)
            ->map(fn ($n, $key) => ['criteria' => json_decode($key, true), 'total' => $n])->values();
    }

    /** Kalimat insight otomatis dari data di atas (rule-based). */
    public function highlights(): array
    {
        $lines = [];
        $label = fn ($v) => ucwords(str_replace('-', ' ', (string) $v));

        if ($color = $this->share('color', 1)->first()) {
            $catalog = Bouquet::visible()->count();
            $match = Bouquet::visible()->where('color', $color['value'])->count();
            $lines[] = "Warna {$label($color['value'])} dipilih di {$color['percent']}% pencarian, sementara katalog baru punya {$match} dari {$catalog} bouquet berwarna itu.";
        }

        if ($occasion = $this->share('occasion', 1)->first()) {
            $lines[] = "Momen yang paling sering dicari: {$label($occasion['value'])} ({$occasion['percent']}% pencarian).";
        }

        $s = $this->summary();
        if ($s['total'] && $s['gaps']) {
            $gap = $this->demandGaps(1)->first();
            $combo = $gap ? collect($gap['criteria'])->except('budget')->map($label)->implode(' + ') : null;
            $lines[] = "{$s['gaps']} dari {$s['total']} pencarian tidak menemukan bouquet yang cocok persis"
                . ($combo ? "; paling sering: {$combo}" : '') . '. Pertimbangkan menambah produk untuk kombinasi ini.';
        }

        if ($s['conversion'] !== null) {
            $lines[] = "{$s['conversion']}% customer yang memakai wizard membuat pesanan setelahnya.";
        }

        return $lines;
    }
}
