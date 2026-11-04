<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bouquet;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class BouquetController extends Controller
{
    public function index(Request $request)
    {
        $bouquets = Bouquet::with('category')
            ->when($request->q, fn ($q, $v) => $q->where('name', 'like', "%{$v}%"))
            ->when($request->category, fn ($q, $v) => $q->where('category_id', $v))
            ->when($request->stock === 'low', fn ($q) => $q->lowStock())
            ->when($request->stock === 'out', fn ($q) => $q->outOfStock())
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return view('admin.bouquets.index', [
            'bouquets' => $bouquets,
            'categories' => Category::orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        return view('admin.bouquets.create', [
            'bouquet' => new Bouquet(['size' => 'medium', 'stock' => 0, 'low_stock_threshold' => 5, 'is_active' => true]),
            'categories' => Category::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['slug'] = $this->uniqueSlug($data['name']);

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('bouquets', 'public');
        }

        Bouquet::create($data);

        return redirect()->route('admin.bouquets.index')->with('success', 'Bouquet berhasil ditambahkan.');
    }

    public function edit(Bouquet $bouquet)
    {
        return view('admin.bouquets.edit', [
            'bouquet' => $bouquet,
            'categories' => Category::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Bouquet $bouquet)
    {
        $data = $this->validated($request);

        if ($data['name'] !== $bouquet->name) {
            $data['slug'] = $this->uniqueSlug($data['name'], $bouquet->id);
        }

        if ($request->hasFile('image')) {
            if ($bouquet->image) {
                Storage::disk('public')->delete($bouquet->image);
            }
            $data['image'] = $request->file('image')->store('bouquets', 'public');
        }

        $bouquet->update($data);

        return redirect()->route('admin.bouquets.index')->with('success', 'Bouquet berhasil diperbarui.');
    }

    public function updateStock(Request $request, Bouquet $bouquet)
    {
        $data = $request->validate(['stock' => ['required', 'integer', 'min:0', 'max:100000']]);

        $bouquet->update($data);

        return back()->with('success', "Stok {$bouquet->name} diperbarui menjadi {$data['stock']}.");
    }

    public function destroy(Bouquet $bouquet)
    {
        // soft delete: riwayat order lama tetap utuh (order_items menyimpan bouquet_id)
        $bouquet->delete();

        return redirect()->route('admin.bouquets.index')->with('success', 'Bouquet dihapus.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'category_id' => ['required', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'price' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'stock' => ['required', 'integer', 'min:0', 'max:100000'],
            'low_stock_threshold' => ['required', 'integer', 'min:0', 'max:100000'],
            'occasion' => ['nullable', Rule::in(Bouquet::OCCASIONS)],
            'color' => ['nullable', Rule::in(Bouquet::COLORS)],
            'style' => ['nullable', Rule::in(Bouquet::STYLES)],
            'size' => ['required', Rule::in(Bouquet::SIZES)],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ], [
            'image.max' => 'Ukuran gambar maksimal 2 MB.',
            'image.mimes' => 'Format gambar harus JPG, PNG, atau WEBP.',
        ]);

        $data = Arr::except($data, ['image']);   // file diproses terpisah
        $data['is_same_day'] = $request->boolean('is_same_day');
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }

    private function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'bouquet';
        $slug = $base;
        $i = 2;

        // withTrashed: slug bouquet yang sudah di-soft-delete tetap terhitung karena kolomnya unique
        while (Bouquet::withTrashed()->where('slug', $slug)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}