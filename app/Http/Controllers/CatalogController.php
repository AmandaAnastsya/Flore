<?php

namespace App\Http\Controllers;

use App\Models\Bouquet;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

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
            ->visible()
            ->when($filters['q'], function ($q, $term) {
                $like = '%' . addcslashes($term, '%_\\') . '%';
                $q->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('description', 'like', $like));
            })
            ->when($filters['category'], fn ($q, $v) => $q->where('category_id', $v))
            ->when($filters['style'], fn ($q, $v) => $q->where('style', $v))
            ->when($filters['occasion'], fn ($q, $v) => $q->whereIn('occasion', $v))
            ->when($filters['color'], fn ($q, $v) => $q->whereIn('color', $v))
            ->when($filters['size'], fn ($q, $v) => $q->whereIn('size', $v))
            ->when($filters['price'], function ($q, $key) {
                [, $min, $max] = self::PRICES[$key];
                $q->when($min !== null, fn ($q) => $q->where('price', '>=', $min))
                  ->when($max !== null, fn ($q) => $q->where('price', '<', $max));
            })
            ->when($filters['price_min'] !== null, fn ($q) => $q->where('price', '>=', $filters['price_min']))
            ->when($filters['price_max'] !== null, fn ($q) => $q->where('price', '<=', $filters['price_max']))
            ->when($filters['same_day'], fn ($q) => $q->where('is_same_day', true))
            ->when($filters['in_stock'], fn ($q) => $q->where('stock', '>', 0));

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
            // dropdown di baris utama
            'options' => [
                'category' => ['Semua kategori', $categories->pluck('name', 'id')->all()],
                'style' => ['Semua style', $label(Bouquet::STYLES)],
                'price' => ['Semua harga', collect(self::PRICES)->map(fn ($p) => $p[0])->all()],
            ],
            // checkbox pilihan ganda di panel "Filter lanjutan"
            'groups' => [
                'occasion' => ['Occasion', $label(Bouquet::OCCASIONS)],
                'color' => ['Warna', $label(Bouquet::COLORS)],
                'size' => ['Ukuran', $label(Bouquet::SIZES)],
            ],
            'advancedActive' => count($filters['occasion']) + count($filters['color']) + count($filters['size'])
                + ($filters['price_min'] !== null) + ($filters['price_max'] !== null)
                + (int) $filters['same_day'] + (int) $filters['in_stock'],
        ]);
    }

    public function show(Bouquet $bouquet)
    {
        // bouquet nonaktif / kategori nonaktif diperlakukan seperti tidak ada
        abort_unless(Bouquet::visible()->whereKey($bouquet->id)->exists(), 404);

        $bouquet->load('category');

        $related = Bouquet::visible()
            ->with('category')
            ->whereKeyNot($bouquet->id)
            ->where(fn ($q) => $q->where('category_id', $bouquet->category_id)
                ->when($bouquet->occasion, fn ($q, $o) => $q->orWhere('occasion', $o)))
            ->orderByRaw('stock > 0 desc')
            ->orderBy('name')
            ->limit(4)
            ->get();

        // tombol kembali mempertahankan filter hanya jika datang dari halaman katalog
        $previous = url()->previous();
        $back = parse_url($previous, PHP_URL_PATH) === parse_url(route('catalog.index'), PHP_URL_PATH)
            ? $previous
            : route('catalog.index');

        return view('catalog.show', compact('bouquet', 'related', 'back'));
    }

    /** Nilai filter yang tidak valid diabaikan, bukan menimbulkan error. */
    private function filters(Request $request): array
    {
        $one = function (string $key, array $allowed) use ($request) {
            $value = $request->query($key);

            return in_array($value, $allowed, true) ? $value : null;
        };

        // menerima ?color=red maupun ?color[]=red&color[]=white
        $many = fn (string $key, array $allowed) => array_values(array_intersect(
            $allowed,
            array_filter(Arr::wrap($request->query($key)), 'is_string')
        ));

        $money = function (string $key) use ($request) {
            $v = $request->query($key);

            return is_numeric($v) && $v >= 0 ? (int) min($v, 99999999) : null;
        };

        [$min, $max] = [$money('price_min'), $money('price_max')];
        if ($min !== null && $max !== null && $min > $max) {
            [$min, $max] = [$max, $min];   // terbalik? tukar saja
        }

        $q = $request->query('q');

        return [
            'q' => is_string($q) ? mb_substr(trim($q), 0, 100) : '',
            'category' => Category::where('is_active', true)->whereKey($request->integer('category'))->value('id'),
            'style' => $one('style', Bouquet::STYLES),
            'occasion' => $many('occasion', Bouquet::OCCASIONS),
            'color' => $many('color', Bouquet::COLORS),
            'size' => $many('size', Bouquet::SIZES),
            'price' => $one('price', array_keys(self::PRICES)),
            'price_min' => $min,
            'price_max' => $max,
            'same_day' => $request->boolean('same_day'),
            'in_stock' => $request->boolean('in_stock'),
            'sort' => $one('sort', array_keys(self::SORTS)) ?? 'bestseller',
        ];
    }
}