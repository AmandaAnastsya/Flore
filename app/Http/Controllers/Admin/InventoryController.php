<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\OrderException;
use App\Http\Controllers\Controller;
use App\Models\Flower;
use App\Models\Supply;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Fresh Floral & Supply Inventory Hub (mockup hal. 49). */
class InventoryController extends Controller
{
    public const TABS = [
        'stems' => 'Bunga Segar (Fresh Stems)',
        'wrapping' => 'Pita & Wrapping Paper',
        'vase' => 'Vas & Aksesoris',
    ];

    public function index(Request $request)
    {
        $tab = array_key_exists($request->query('tab'), self::TABS) ? $request->query('tab') : 'stems';
        $q = mb_substr(trim((string) $request->query('q', '')), 0, 100);
        $like = '%' . addcslashes($q, '%_\\') . '%';

        $items = $tab === 'stems'
            ? Flower::query()->when($q, fn ($w) => $w->where(fn ($x) => $x->where('name', 'like', $like)->orWhere('species', 'like', $like)))
                ->orderByDesc('is_active')->orderBy('name')->get()
            : Supply::where('type', $tab)->when($q, fn ($w) => $w->where(fn ($x) => $x->where('name', 'like', $like)->orWhere('variant', 'like', $like)))
                ->orderByDesc('is_active')->orderBy('name')->get();

        $flowers = Flower::active()->get();
        $supplies = Supply::active()->get();
        $withAge = $flowers->filter(fn ($f) => $f->stock > 0 && $f->freshnessPercent() !== null);

        return view('admin.inventory.index', [
            'tab' => $tab, 'q' => $q, 'items' => $items,
            'kpi' => [
                'stems' => (int) $flowers->sum('stock'),
                'varieties' => $flowers->count(),
                'critical' => $flowers->filter->needsRestock()->count() + $supplies->filter->needsRestock()->count(),
                'critical_names' => $flowers->concat($supplies)->filter->needsRestock()->pluck('name')->take(3)->implode(', '),
                'freshness' => $withAge->isEmpty() ? null : (int) round($withAge->avg(fn ($f) => $f->freshnessPercent())),
                'expired' => $flowers->filter(fn ($f) => $f->stock > 0 && $f->freshnessState() === 'expired')->count(),
                'wrapping_meter' => (int) $supplies->where('type', 'wrapping')->where('unit', 'meter')->sum('stock'),
                'wrapping_items' => $supplies->where('type', 'wrapping')->count(),
            ],
        ]);
    }

    // ------------------------------------------------------------ bunga per tangkai

    public function createFlower()
    {
        return view('admin.inventory.flower-form', ['flower' => new Flower(['reorder_level' => 30, 'is_active' => true, 'stock' => 0])]);
    }

    public function storeFlower(Request $request, InventoryService $inventory)
    {
        $data = $this->validateFlower($request) + $request->validate(['stock' => ['required', 'integer', 'min:0', 'max:100000']], [], ['stock' => 'stok awal']);
        $data['slug'] = $this->uniqueSlug($data['name']);

        $flower = new Flower($data);
        $flower->last_restocked_at = $data['stock'] > 0 ? now() : null;
        $flower->save();
        $inventory->opening($flower);

        return redirect()->route('admin.inventory.index')->with('success', "Bunga {$flower->name} ditambahkan.");
    }

    public function editFlower(Flower $flower)
    {
        return view('admin.inventory.flower-form', ['flower' => $flower]);
    }

    public function updateFlower(Request $request, Flower $flower)
    {
        $flower->update($this->validateFlower($request, $flower));

        return redirect()->route('admin.inventory.index')->with('success', "Data {$flower->name} diperbarui.");
    }

    // ------------------------------------------------------------ supplies

    public function createSupply(Request $request)
    {
        $type = array_key_exists($request->query('type'), Supply::TYPES) ? $request->query('type') : 'wrapping';

        return view('admin.inventory.supply-form', ['supply' => new Supply([
            'type' => $type, 'unit' => $type === 'wrapping' ? 'meter' : 'pcs', 'reorder_level' => 10, 'is_active' => true, 'stock' => 0,
        ])]);
    }

    public function storeSupply(Request $request, InventoryService $inventory)
    {
        $data = $this->validateSupply($request) + $request->validate(['stock' => ['required', 'integer', 'min:0', 'max:100000']], [], ['stock' => 'stok awal']);
        $supply = Supply::create($data);
        $inventory->opening($supply);

        return redirect()->route('admin.inventory.index', ['tab' => $supply->type])->with('success', "{$supply->name} ditambahkan.");
    }

    public function editSupply(Supply $supply)
    {
        return view('admin.inventory.supply-form', ['supply' => $supply]);
    }

    public function updateSupply(Request $request, Supply $supply)
    {
        $supply->update($this->validateSupply($request));

        return redirect()->route('admin.inventory.index', ['tab' => $supply->type])->with('success', "Data {$supply->name} diperbarui.");
    }

    // ------------------------------------------------------------ pergerakan stok

    public function restockFlower(Request $request, Flower $flower, InventoryService $inventory)
    {
        return $this->restock($request, $flower, $inventory);
    }

    public function restockSupply(Request $request, Supply $supply, InventoryService $inventory)
    {
        return $this->restock($request, $supply, $inventory);
    }

    public function adjustFlower(Request $request, Flower $flower, InventoryService $inventory)
    {
        return $this->adjust($request, $flower, $inventory);
    }

    public function adjustSupply(Request $request, Supply $supply, InventoryService $inventory)
    {
        return $this->adjust($request, $supply, $inventory);
    }

    private function restock(Request $request, Flower|Supply $item, InventoryService $inventory)
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'unit_cost' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'note' => ['nullable', 'string', 'max:200'],
        ], [], ['quantity' => 'jumlah masuk', 'unit_cost' => 'HPP per unit']);

        return $this->run($item, fn () => $inventory->restock($item, (int) $data['quantity'],
            isset($data['unit_cost']) ? (float) $data['unit_cost'] : null, $data['note'] ?? null),
            "Restock {$item->name}: +{$data['quantity']}.");
    }

    private function adjust(Request $request, Flower|Supply $item, InventoryService $inventory)
    {
        $data = $request->validate([
            'stock' => ['required', 'integer', 'min:0', 'max:100000'],
            'reason' => ['required', Rule::in(['adjustment', 'waste'])],
            'note' => ['nullable', 'string', 'max:200'],
        ], [], ['stock' => 'stok baru', 'reason' => 'alasan']);

        return $this->run($item, fn () => $inventory->adjust($item, (int) $data['stock'], $data['reason'], $data['note'] ?? null),
            "Stok {$item->name} menjadi {$data['stock']}.");
    }

    // ------------------------------------------------------------ helper

    private function validateFlower(Request $request, ?Flower $flower = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('flowers', 'name')->ignore($flower?->id)],
            'species' => ['required', 'string', 'max:50'],
            'color' => ['nullable', 'string', 'max:30'],
            'origin' => ['nullable', 'string', 'max:60'],
            'description' => ['nullable', 'string', 'max:500'],
            'price_per_stem' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'bundle_size' => ['nullable', 'integer', 'min:2', 'max:100', 'required_with:bundle_price'],
            'bundle_price' => ['nullable', 'numeric', 'min:0', 'max:99999999', 'required_with:bundle_size'],
            'cost_per_stem' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'reorder_level' => ['required', 'integer', 'min:0', 'max:100000'],
            'shelf_life_days' => ['nullable', 'integer', 'min:1', 'max:30'],
        ], [], [
            'species' => 'jenis bunga', 'origin' => 'asal', 'price_per_stem' => 'harga per tangkai', 'bundle_size' => 'isi paket',
            'bundle_price' => 'harga paket', 'cost_per_stem' => 'HPP per tangkai', 'reorder_level' => 'batas restock', 'shelf_life_days' => 'umur segar',
        ]);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }

    private function validateSupply(Request $request): array
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(Supply::TYPES))],
            'name' => ['required', 'string', 'max:100'],
            'variant' => ['nullable', 'string', 'max:100'],
            'unit' => ['required', Rule::in(Supply::UNITS)],
            'reorder_level' => ['required', 'integer', 'min:0', 'max:100000'],
            'cost_per_unit' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
        ], [], ['type' => 'kategori', 'variant' => 'varian / warna', 'unit' => 'satuan', 'reorder_level' => 'batas restock', 'cost_per_unit' => 'HPP per unit']);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }

    private function run(Flower|Supply $item, callable $action, string $success)
    {
        $tab = $item instanceof Flower ? 'stems' : $item->type;

        try {
            $action();
        } catch (OrderException $e) {
            return redirect()->route('admin.inventory.index', ['tab' => $tab])->with('error', "{$item->name}: {$e->getMessage()}");
        }

        return redirect()->route('admin.inventory.index', ['tab' => $tab])->with('success', $success);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'bunga';
        $slug = $base;
        for ($i = 2; Flower::withTrashed()->where('slug', $slug)->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }
}
