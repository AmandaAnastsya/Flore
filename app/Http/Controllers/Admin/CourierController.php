<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Courier;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Data armada kurir untuk halaman Logistics & Courier Dispatch. */
class CourierController extends Controller
{
    public function index()
    {
        $couriers = Courier::withCount([
            'orders as on_the_way_count' => fn ($q) => $q->where('status', 'out_for_delivery'),
            'orders as delivered_count' => fn ($q) => $q->where('status', 'delivered'),
            'orders as assigned_count' => fn ($q) => $q->whereIn('status', ['payment_confirmed', 'preparing']),
        ])->orderByDesc('is_active')->orderBy('name')->get();

        return view('admin.couriers.index', ['couriers' => $couriers, 'courier' => new Courier(['vehicle' => 'motor', 'is_active' => true])]);
    }

    public function store(Request $request)
    {
        Courier::create($this->validated($request));

        return redirect()->route('admin.couriers.index')->with('success', 'Kurir ditambahkan.');
    }

    public function edit(Courier $courier)
    {
        return view('admin.couriers.edit', ['courier' => $courier]);
    }

    public function update(Request $request, Courier $courier)
    {
        $data = $this->validated($request);

        if (! $data['is_active'] && $courier->orders()->where('status', 'out_for_delivery')->exists()) {
            return back()->withInput()->with('error', 'Kurir ini masih membawa pesanan. Selesaikan atau re-route pesanannya dulu.');
        }

        $courier->update($data);

        return redirect()->route('admin.couriers.index')->with('success', 'Data kurir diperbarui.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'phone' => ['required', 'string', 'regex:/^(\+62|62|0)8[0-9]{7,12}$/'],
            'vehicle' => ['required', Rule::in(array_keys(Courier::VEHICLES))],
            'plate_number' => ['nullable', 'string', 'max:15'],
            'notes' => ['nullable', 'string', 'max:160'],
        ], ['phone.regex' => 'Nomor HP harus nomor Indonesia, contoh 081234567890.'], [
            'name' => 'nama kurir', 'phone' => 'nomor HP', 'vehicle' => 'armada', 'plate_number' => 'plat nomor', 'notes' => 'catatan',
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $data['plate_number'] = isset($data['plate_number']) ? strtoupper($data['plate_number']) : null;

        return $data;
    }
}
