<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bouquet;
use App\Services\ProductPhoto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BouquetImageController extends Controller
{
    public function index()
    {
        $bouquets = Bouquet::with('category')->orderBy('name')->paginate(12);

        return view('admin.bouquet-images.index', compact('bouquets'));
    }

    public function edit(Bouquet $bouquet)
    {
        return view('admin.bouquet-images.edit', compact('bouquet'));
    }

    public function update(Request $request, Bouquet $bouquet)
    {
        $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'], // maks 2 MB
        ], [
            'image.max' => 'Ukuran gambar maksimal 2 MB.',
            'image.mimes' => 'Format gambar harus JPG, PNG, atau WEBP.',
        ]);

        // hapus file lama supaya storage tidak menumpuk
        if ($bouquet->image) {
            Storage::disk('public')->delete($bouquet->image);
        }

        // crop 4:3, clean look, logo Floré kiri bawah, simpan WebP
        $path = app(ProductPhoto::class)->store($request->file('image')->getRealPath());
        $bouquet->update(['image' => $path]);

        return redirect()->route('admin.bouquet-images.index')->with('success', 'Gambar bouquet berhasil diunggah.');
    }

    public function destroy(Bouquet $bouquet)
    {
        if ($bouquet->image) {
            Storage::disk('public')->delete($bouquet->image);
            $bouquet->update(['image' => null]);
        }

        return back()->with('success', 'Gambar bouquet dihapus.');
    }
}