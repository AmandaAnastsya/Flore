<?php

namespace App\Http\Controllers;

use App\Models\Flower;
use App\Services\Cart;
use Illuminate\Http\Request;

class CartStemController extends Controller
{
    public function store(Request $request, Flower $flower, Cart $cart)
    {
        abort_unless($flower->is_active, 404);

        $data = $request->validate([
            'quantity' => ['nullable', 'integer', 'min:1', 'max:' . Cart::MAX_STEMS],
        ]);

        $error = $cart->addStem($flower, (int) ($data['quantity'] ?? 1));

        return $error
            ? back()->with('error', $error)
            : back()->with('success', "{$flower->name} ditambahkan ke keranjang.");
    }

    public function update(Request $request, Flower $flower, Cart $cart)
    {
        abort_unless($flower->is_active, 404);

        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:' . Cart::MAX_STEMS],
        ]);

        $error = $cart->setStem($flower, (int) $data['quantity']);

        return $error
            ? back()->with('error', $error)
            : back()->with('success', "Jumlah {$flower->name} diperbarui.");
    }

    public function destroy(Flower $flower, Cart $cart)
    {
        $cart->removeStem($flower->id);

        return back()->with('success', "{$flower->name} dihapus dari keranjang.");
    }
}