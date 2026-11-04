<?php

namespace App\Http\Controllers;

use App\Models\Bouquet;
use App\Models\Category;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public const SORTS = [
        'bestseller' => 'Terlaris',
        'newest' => 'Terbaru',
        'price_asc' => 'Harga terendah',
        'price_desc' => 'Harga tertinggi',
        'name' => 'Nama A-Z',
    ];

    // kunci => [label, minimal (>=), maksimal (<)]
    public const PRICES = [
        'lt500' => ['Di bawah Rp 500 rb', null, 500000],
        '500-1000' => ['Rp 500 rb - 1 jt', 500000, 1000000],
        '1000-1500' => ['Rp 1 jt - 1,5 jt', 1000000, 1500000],
        'gt1500' => ['Rp 1,5 jt ke atas', 1500000, null],
    ];

    public function index(Request $request)
    {
        $categories = Category::where('is_active', true)->orderBy('name')->get();
        $filters = $this->filters($request);

        $query = Bouquet::query()
            ->with('category')
            ->where('is_active', true)
            ->whereHas('category', fn ($q) => $q->where('is_active', true))
            ->when($filters['q'], function ($q, $term) {
                $like = '%' . addcslashes($term, '%_\\') . '%';
                $q->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('description', 'like', $like));
            })
            ->when($filters['category'], fn ($q, $v) => $q->where('category_id', $v))
            ->when($filters['occasion'], fn ($q, $v) => $q->where('occasion', $v))
            ->when($filters['style'], fn ($q, $v) => $q->where('style', $v))
            ->when($filters['color'], fn ($q, $v) => $q->where('color', $v))
            ->when($filters['price'], function ($q, $key) {
                [, $min, $max] = self::PRICES[$key];
                $q->when($min !== null, fn ($q) => $q->where('price', '>=', $min))
                  ->when($max !== null, fn ($q) => $q->where('price', '<', $max));
            });

        match ($filters['sort']) {
            'price_asc' => $query->orderBy('price'),
            'price_desc' => $query->orderByDesc('price'),
            'name' => $query->orderBy('name'),
            'newest' => $query->latest('id'),
            default => $query
                ->withSum(['orderItems as sold_count' => fn ($q) => $q->whereHas(
                    'order', fn ($o) => $o->where('status', '!=', 'cancelled')
                )], 'quantity')
                ->orderByDesc('sold_count')
                ->orderBy('name'),
        };

        $label = fn (array $values) => collect($values)
            ->mapWithKeys(fn ($v) => [$v => ucfirst(str_replace('-', ' ', $v))])->all();

        return view('catalog.index', [
            'bouquets' => $query->paginate(9)->withQueryString(),
            'filters' => $filters,
            'sorts' => self::SORTS,
            'options' => [
                'category' => ['Semua kategori', $categories->pluck('name', 'id')->all()],
                'occasion' => ['Semua occasion', $label(Bouquet::OCCASIONS)],
                'style' => ['Semua style', $label(Bouquet::STYLES)],
                'color' => ['Semua warna', $label(Bouquet::COLORS)],
                'price' => ['Semua harga', collect(self::PRICES)->map(fn ($p) => $p[0])->all()],
            ],
        ]);
    }

    /** Nilai filter yang tidak valid diabaikan, bukan menimbulkan error. */
    private function filters(Request $request): array
    {
        $pick = function (string $key, array $allowed) use ($request) {
            $value = $request->query($key);

            return in_array($value, $allowed, true) ? $value : null;
        };

        $q = $request->query('q');

        return [
            'q' => is_string($q) ? mb_substr(trim($q), 0, 100) : '',
            'category' => Category::where('is_active', true)->whereKey($request->integer('category'))->value('id'),
            'occasion' => $pick('occasion', Bouquet::OCCASIONS),
            'style' => $pick('style', Bouquet::STYLES),
            'color' => $pick('color', Bouquet::COLORS),
            'price' => $pick('price', array_keys(self::PRICES)),
            'sort' => $pick('sort', array_keys(self::SORTS)) ?? 'bestseller',
        ];
    }
}