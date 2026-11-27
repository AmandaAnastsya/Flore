<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\OrderException;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\DeliverySchedule;
use App\Services\OrderWorkflow;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    /**
     * Tab Order Management (mockup hal. 48). "Aktif" = live orders: semua pesanan yang masih berjalan.
     * [label, filter query]
     */
    private static function tabs(): array
    {
        return [
            'active' => ['Aktif', fn ($q) => $q->whereIn('status', ['pending_payment', 'payment_confirmed', 'preparing', 'out_for_delivery'])],
            'verify' => ['Menunggu Verifikasi', fn ($q) => $q->awaitingVerification()],
            'preparing' => ['Dirangkai', fn ($q) => $q->whereIn('status', ['payment_confirmed', 'preparing'])],
            'shipping' => ['Dikirim', fn ($q) => $q->where('status', 'out_for_delivery')],
            'done' => ['Selesai', fn ($q) => $q->where('status', 'delivered')],
            'cancelled' => ['Dibatalkan', fn ($q) => $q->where('status', 'cancelled')],
            'all' => ['Semua', fn ($q) => $q],
        ];
    }

    public function index(Request $request)
    {
        $q = $request->query('q');
        $q = is_string($q) ? mb_substr(trim($q), 0, 100) : '';

        $status = $request->query('status');
        $status = is_string($status) && array_key_exists($status, Order::STATUS_LABELS) ? $status : null;

        $payment = $request->query('payment_status');
        $payment = is_string($payment) && array_key_exists($payment, Order::PAYMENT_LABELS) ? $payment : null;

        $tabs = self::tabs();
        $tab = $request->query('tab');
        // tanpa tab: buka "Aktif" — kecuali sedang mencari/memfilter, maka cari di semua pesanan
        $tab = is_string($tab) && isset($tabs[$tab]) ? $tab : (($q || $status || $payment) ? 'all' : 'active');

        $counts = collect($tabs)->map(fn ($t) => $t[1](Order::query())->count());

        $orders = $tabs[$tab][1](Order::query())->with('user')->withCount('items')
            ->when($q, function ($query, $term) {
                $like = '%' . addcslashes($term, '%_\\') . '%';
                $query->where(fn ($w) => $w->where('order_number', 'like', $like)->orWhere('recipient_name', 'like', $like));
            })
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($payment, fn ($query) => $query->where('payment_status', $payment))
            ->latest()->latest('id')
            ->paginate(15)->withQueryString();

        return view('admin.orders.index', [
            'orders' => $orders,
            'q' => $q, 'status' => $status, 'payment' => $payment,
            'tab' => $tab, 'tabs' => collect($tabs)->map(fn ($t) => $t[0]), 'counts' => $counts,
        ]);
    }

    public function show(Order $order)
    {
        return view('admin.orders.show', [
            'order' => $order->load('items', 'user', 'courier', 'florist'),
            'florists' => \App\Models\Florist::active()->orderBy('name')->get(),
            'slots' => DeliverySchedule::labels(),
        ]);
    }

    public function confirm(Order $order, OrderWorkflow $workflow)
    {
        return $this->run(fn () => $workflow->confirmPayment($order), 'Pembayaran dikonfirmasi.');
    }

    public function reject(Order $order, OrderWorkflow $workflow)
    {
        return $this->run(fn () => $workflow->rejectPayment($order), 'Bukti pembayaran ditolak. Customer bisa mengunggah ulang.');
    }

    public function advance(Order $order, OrderWorkflow $workflow)
    {
        return $this->run(function () use ($order, $workflow) {
            $next = $workflow->advance($order);
            session()->flash('success', 'Status diubah menjadi: ' . Order::STATUS_LABELS[$next] . '.');
        });
    }

    public function cancel(Order $order, OrderWorkflow $workflow)
    {
        return $this->run(fn () => $workflow->cancel($order, byAdmin: true), 'Pesanan dibatalkan dan stok dikembalikan.');
    }

    // ---------------------------------------------------- produksi (mockup hal. 48)

    public function assignFlorist(Request $request, Order $order, \App\Services\FulfillmentService $fulfillment)
    {
        $data = $request->validate([
            'florist_id' => ['required', 'integer', \Illuminate\Validation\Rule::exists('florists', 'id')->where('is_active', true)],
            'production_note' => ['nullable', 'string', 'max:255'],
        ], [], ['florist_id' => 'florist', 'production_note' => 'catatan produksi']);

        return $this->run(fn () => $fulfillment->assignFlorist($order, \App\Models\Florist::findOrFail($data['florist_id']), $data['production_note'] ?? null),
            'Florist ditugaskan.');
    }

    public function advanceStage(Request $request, Order $order, \App\Services\FulfillmentService $fulfillment)
    {
        $request->validate(['qc_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120']], [], ['qc_photo' => 'foto QC']);

        return $this->run(function () use ($request, $order, $fulfillment) {
            $stage = $fulfillment->advanceStage($order, $request->file('qc_photo'));
            session()->flash('success', 'Tahap produksi: ' . Order::STAGES[$stage] . '.');
        });
    }

    public function qcPhoto(Order $order)
    {
        $disk = \App\Services\FulfillmentService::PHOTO_DISK;
        abort_unless($order->qc_photo && \Illuminate\Support\Facades\Storage::disk($disk)->exists($order->qc_photo), 404);

        return \Illuminate\Support\Facades\Storage::disk($disk)->response($order->qc_photo, null, ['X-Content-Type-Options' => 'nosniff']);
    }

    /** Halaman cetak: label pengiriman + kartu ucapan. */
    public function printSlip(Order $order)
    {
        return view('admin.orders.print', [
            'order' => $order->load('items', 'courier', 'florist'),
            'slots' => DeliverySchedule::labels(),
        ]);
    }

    private function run(callable $action, ?string $success = null)
    {
        try {
            $action();
        } catch (OrderException $e) {
            return back()->with('error', $e->getMessage());
        }

        return $success ? back()->with('success', $success) : back();
    }
}