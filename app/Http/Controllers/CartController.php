<?php

namespace App\Http\Controllers;

use App\Models\Bouquet;
use App\Services\Cart;
use Illuminate\Http\Request;

class CartController extends Controller
{
        public function index(Cart $cart)
{
    $notices = $cart->sync();
    $lines = $cart->lines();
    $stemLines = $cart->stemLines();

    return view('cart.index', [
        'lines' => $lines,
        'stemLines' => $stemLines,
        'subtotal' => $lines->sum('subtotal') + $stemLines->sum('subtotal'),
        'itemCount' => $lines->sum('quantity'),
        'stemCount' => $stemLines->sum('quantity'),
        'notices' => $notices,
    ]);
}

    public function store(Request $request, Bouquet $bouquet, Cart $cart)
    {
        // bouquet nonaktif / kategori nonaktif diperlakukan seperti tidak ada
        abort_unless(Bouquet::visible()->whereKey($bouquet->id)->exists(), 404);

        $data = $request->validate([
            'quantity' => ['nullable', 'integer', 'min:1', 'max:' . Cart::MAX_QTY],
        ]);

        $error = $cart->add($bouquet, (int) ($data['quantity'] ?? 1));

        if ($error) {
            return back()->with('error', $error);
        }

        return back()->with('success', "{$bouquet->name} ditambahkan ke keranjang.");
    }
    public function update(Request $request, Bouquet $bouquet, Cart $cart)
{
    abort_unless(Bouquet::visible()->whereKey($bouquet->id)->exists(), 404);

    $data = $request->validate([
        'quantity' => ['required', 'integer', 'min:1', 'max:' . Cart::MAX_QTY],
    ]);

    $error = $cart->set($bouquet, (int) $data['quantity']);

    return $error
        ? back()->with('error', $error)
        : back()->with('success', "Jumlah {$bouquet->name} diperbarui.");
}

public function destroy(Bouquet $bouquet, Cart $cart)
{
    $cart->remove($bouquet->id);

    return back()->with('success', "{$bouquet->name} dihapus dari keranjang.");
}

public function clear(Cart $cart)
{
    $cart->clear();

    return redirect()->route('cart.index')->with('success', 'Keranjang dikosongkan.');
}
}