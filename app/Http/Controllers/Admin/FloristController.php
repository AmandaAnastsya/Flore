<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Florist;
use App\Models\Order;
use Illuminate\Http\Request;

/** Florist Assignment (sidebar mockup hal. 48): data florist & papan produksi. */
class FloristController extends Controller
{
    public function index()
    {
        $florists = Florist::withCount([
            'orders as active_count' => fn ($q) => $q->whereIn('status', ['payment_confirmed', 'preparing']),
            'orders as done_count' => fn ($q) => $q->whereIn('status', ['out_for_delivery', 'delivered']),
        ])->orderByDesc('is_active')->orderBy('name')->get();

        // papan produksi: semua pesanan yang sedang/akan dirangkai, urut tanggal kirim
        $board = Order::with('florist', 'items')->whereIn('status', ['payment_confirmed', 'preparing'])
            ->orderBy('delivery_date')->orderBy('delivery_slot')->get()
            ->groupBy(fn (Order $o) => $o->status === 'payment_confirmed' ? 'queue' : $o->fulfillment_stage ?? 'arranging');

        return view('admin.florists.index', [
            'florists' => $florists,
            'board' => $board,
            'florist' => new Florist(['is_active' => true]),
        ]);
    }

    public function store(Request $request)
    {
        Florist::create($this->validated($request));

        return redirect()->route('admin.florists.index')->with('success', 'Florist ditambahkan.');
    }

    public function edit(Florist $florist)
    {
        return view('admin.florists.edit', ['florist' => $florist]);
    }

    public function update(Request $request, Florist $florist)
    {
        $florist->update($this->validated($request));

        return redirect()->route('admin.florists.index')->with('success', 'Data florist diperbarui.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'phone' => ['nullable', 'regex:/^(\+62|62|0)8[0-9]{7,12}$/'],
            'specialty' => ['nullable', 'string', 'max:120'],
        ], ['phone.regex' => 'Nomor HP harus nomor Indonesia, contoh 081234567890.'], ['name' => 'nama florist', 'specialty' => 'keahlian']);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
