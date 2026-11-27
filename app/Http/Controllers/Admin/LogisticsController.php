<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\OrderException;
use App\Http\Controllers\Controller;
use App\Models\Courier;
use App\Models\Order;
use App\Services\DeliveryDispatch;
use App\Services\DeliverySchedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/** Logistics & Courier Dispatch Dashboard (proposal hal. 50). */
class LogisticsController extends Controller
{
    public function index(Request $request)
    {
        $tab = $request->query('tab') === 'delivered' ? 'delivered' : 'queue';

        $queue = Order::with('courier')->withCount('items')
            ->whereIn('status', ['payment_confirmed', 'preparing', 'out_for_delivery'])
            ->orderBy('delivery_date')->orderBy('delivery_slot')->orderBy('id')
            ->get()
            // urutan kerja: yang terlambat/berkendala dulu, lalu yang sedang jalan, siap pick-up, menunggu kurir, belum dirangkai
            ->sortBy(fn (Order $o) => [
                ['late' => 0, 'on_the_way' => 1, 'ready_pickup' => 2, 'waiting_courier' => 3, 'not_prepared' => 4][$o->dispatchState()] - ($o->hasOpenIssue() ? 1 : 0),
                $o->delivery_date->timestamp,
            ])->values();

        $deliveredToday = Order::with('courier')->withCount('items')
            ->where('status', 'delivered')->whereDate('delivered_at', today())
            ->latest('delivered_at')->get();

        $list = $tab === 'delivered' ? $deliveredToday : $queue;

        $selected = null;
        if ($id = $request->integer('order')) {
            $selected = Order::with('courier', 'items')->whereIn('status', ['payment_confirmed', 'preparing', 'out_for_delivery', 'delivered'])->find($id);
        }
        $selected ??= $list->first()?->load('items');

        $onWay = $queue->where('status', 'out_for_delivery');
        $onTime = $deliveredToday->map->deliveredOnTime()->filter(fn ($v) => $v !== null);

        return view('admin.logistics.index', [
            'tab' => $tab,
            'list' => $list,
            'selected' => $selected,
            'couriers' => Courier::active()->orderBy('name')->get(),
            'slots' => DeliverySchedule::labels(),
            'studioAddress' => config('flore.studio.address'),
            'kpi' => [
                'ready' => $queue->where('status', 'preparing')->count(),
                'ready_no_courier' => $queue->where('status', 'preparing')->whereNull('courier_id')->count(),
                'active_couriers' => $onWay->pluck('courier_id')->filter()->unique()->count(),
                'on_way' => $onWay->count(),
                'delivered_today' => $deliveredToday->count(),
                'on_time_pct' => $onTime->isEmpty() ? null : (int) round($onTime->filter()->count() / $onTime->count() * 100),
                'late' => $onWay->filter->isLate()->count(),
                'issues' => $onWay->filter->hasOpenIssue()->count(),
            ],
            'counts' => ['queue' => $queue->count(), 'delivered' => $deliveredToday->count()],
        ]);
    }

    public function assign(Request $request, Order $order, DeliveryDispatch $dispatch)
    {
        $data = $this->validateCourier($request);

        return $this->run($order, fn () => $dispatch->assign($order, Courier::findOrFail($data['courier_id']), $data['travel_minutes']),
            'Kurir ditugaskan. Tekan "Berangkatkan" saat bouquet diserahkan ke kurir.');
    }

    public function depart(Order $order, DeliveryDispatch $dispatch)
    {
        return $this->run($order, fn () => $dispatch->dispatch($order), 'Pesanan diberangkatkan. Customer kini melihat status "Dalam pengiriman".');
    }

    public function reroute(Request $request, Order $order, DeliveryDispatch $dispatch)
    {
        $data = $this->validateCourier($request);

        return $this->run($order, fn () => $dispatch->reroute($order, Courier::findOrFail($data['courier_id']), $data['travel_minutes']),
            'Kurir/ETA diperbarui.');
    }

    public function issue(Request $request, Order $order, DeliveryDispatch $dispatch)
    {
        $data = $request->validate(['delivery_issue' => ['required', 'string', 'max:255']], [], ['delivery_issue' => 'catatan kendala']);

        return $this->run($order, fn () => $dispatch->reportIssue($order, $data['delivery_issue']), 'Kendala dicatat.');
    }

    public function resolveIssue(Order $order, DeliveryDispatch $dispatch)
    {
        return $this->run($order, fn () => $dispatch->resolveIssue($order), 'Kendala ditandai selesai.');
    }

    public function deliver(Request $request, Order $order, DeliveryDispatch $dispatch)
    {
        $data = $request->validate([
            'received_by' => ['required', 'string', 'max:80'],
            'delivery_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], [], ['received_by' => 'nama penerima', 'delivery_photo' => 'foto serah terima']);

        return $this->run($order, fn () => $dispatch->confirmDelivered($order, $data['received_by'], $request->file('delivery_photo')),
            'Pesanan dikonfirmasi terkirim.');
    }

    /** Foto serah terima (POD) — disk privat; admin atau pemilik pesanan. */
    public function photo(Order $order)
    {
        abort_unless($order->delivery_photo && Storage::disk(DeliveryDispatch::PHOTO_DISK)->exists($order->delivery_photo), 404);

        return Storage::disk(DeliveryDispatch::PHOTO_DISK)->response($order->delivery_photo, null, ['X-Content-Type-Options' => 'nosniff']);
    }

    private function validateCourier(Request $request): array
    {
        return $request->validate([
            'courier_id' => ['required', 'integer', Rule::exists('couriers', 'id')->where('is_active', true)],
            'travel_minutes' => ['required', 'integer', Rule::in(Courier::TRAVEL_OPTIONS)],
        ], [], ['courier_id' => 'kurir', 'travel_minutes' => 'estimasi waktu tempuh']);
    }

    private function run(Order $order, callable $action, string $success)
    {
        try {
            $action();
        } catch (OrderException $e) {
            return redirect()->route('admin.logistics.index', ['order' => $order->id])->with('error', $e->getMessage());
        }

        return redirect()->route('admin.logistics.index', ['order' => $order->id])->with('success', $success);
    }
}
