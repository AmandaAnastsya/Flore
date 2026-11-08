<?php

namespace App\Http\Controllers;

use App\Http\Requests\CheckoutRequest;
use App\Models\Bouquet;
use App\Services\Cart;
use App\Services\DeliverySchedule;
use App\Exceptions\OrderException;
use App\Services\OrderService;
use Illuminate\Support\Facades\Cache;

class CheckoutController extends Controller
{
    public function index(Cart $cart)
    {
        if ($redirect = $this->guard($cart)) {
            return $redirect;
        }

        $lines = $cart->lines();
        $stemLines = $cart->stemLines();
        $now = now();
        $sameDayOk = DeliverySchedule::sameDayPossible($lines);

        return view('checkout.index', [
            'lines' => $lines,
            'stemLines' => $stemLines,
            'subtotal' => $lines->sum('subtotal') + $stemLines->sum('subtotal'),
            'draft' => session('checkout', []),
            'user' => auth()->user(),
            'sameDayOk' => $sameDayOk,
            'minDate' => DeliverySchedule::earliestDate($sameDayOk, $now)->toDateString(),
            'maxDate' => $now->copy()->addDays(DeliverySchedule::MAX_DAYS)->toDateString(),
            'today' => $now->toDateString(),
            'todaySlots' => $sameDayOk ? DeliverySchedule::slotsFor($now->copy()->startOfDay(), $now) : [],
            'slots' => DeliverySchedule::labels(),
            'occasions' => Bouquet::OCCASIONS,
        ]);
    }

    public function store(CheckoutRequest $request, Cart $cart)
    {
        if ($redirect = $this->guard($cart)) {
            return $redirect;
        }

        $data = $request->validated();

        if ($data['is_anonymous']) {
            $data['sender_name'] = null;   // anonim: nama pengirim tidak dipakai
        }

        session()->put('checkout', $data);

        return redirect()->route('checkout.review');
    }

    public function review(Cart $cart)
    {
        if ($redirect = $this->guard($cart)) {
            return $redirect;
        }

        $data = session('checkout');

        if (! $data) {
            return redirect()->route('checkout.index');
        }

        $lines = $cart->lines();

        // jadwal bisa basi (tanggal lewat, slot habis, isi keranjang berubah)
        $problem = DeliverySchedule::problem($data['delivery_date'], $data['delivery_slot'], $lines);

        if ($problem) {
            return redirect()->route('checkout.index')->with('error', $problem[1] . ' Silakan pilih jadwal ulang.');
        }

        $stemLines = $cart->stemLines();

        return view('checkout.review', [
            'data' => $data,
            'lines' => $lines,
            'stemLines' => $stemLines,
            'subtotal' => $lines->sum('subtotal') + $stemLines->sum('subtotal'),
            'slots' => DeliverySchedule::labels(),
        ]);
    }
    public function place(Cart $cart, OrderService $orders)
{
    if ($redirect = $this->guard($cart)) {
        return $redirect;
    }

    $data = session('checkout');

    if (! $data) {
        return redirect()->route('checkout.index')->with('error', 'Lengkapi data checkout terlebih dahulu.');
    }

    $user = auth()->user();
    $lock = Cache::lock('place-order:' . $user->id, 15);   // cegah klik ganda bersamaan

    try {
        $order = $lock->get(fn () => $orders->place(
            $user, $data, $cart->bouquetQuantities(), $cart->stemQuantities()
        ));
    } catch (OrderException $e) {
        return redirect()
            ->route($e->target === 'checkout' ? 'checkout.index' : 'cart.index')
            ->with('error', $e->getMessage());
    }

    if ($order === false) {
        return redirect()->route('checkout.review')->with('error', 'Pesananmu sedang diproses, mohon tunggu sebentar.');
    }

    $cart->clear();
    session()->forget('checkout');

    return redirect()->route('orders.show', $order->order_number)->with('success', 'Pesanan berhasil dibuat.');
}
    /** Keranjang kosong, atau berubah karena stok → kembali ke keranjang dengan pesan. */
    private function guard(Cart $cart)
    {
        $notices = $cart->sync();

        if ($notices) {
            return redirect()->route('cart.index')->with('error', implode(' ', $notices));
        }

        if ($cart->count() === 0) {
            return redirect()->route('cart.index')->with('error', 'Keranjangmu masih kosong.');
        }

        return null;
    }
}