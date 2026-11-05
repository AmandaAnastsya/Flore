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

        return view('cart.index', [
            'lines' => $lines,
            'subtotal' => $lines->sum('subtotal'),
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
}