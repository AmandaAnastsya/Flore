<?php

namespace App\Http\Controllers;

use App\Exceptions\OrderException;
use App\Models\Order;
use App\Services\DeliverySchedule;
use App\Services\OrderWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status');
        $status = is_string($status) && array_key_exists($status, Order::STATUS_LABELS) ? $status : null;

        $orders = Order::where('user_id', auth()->id())
            ->when($status, fn ($q) => $q->where('status', $status))
            ->withCount('items')
            ->latest()->latest('id')
            ->paginate(10)->withQueryString();

        return view('orders.index', ['orders' => $orders, 'status' => $status]);
    }

    public function show(Order $order)
    {
        $this->authorizeOwner($order);

        return view('orders.show', [
            'order' => $order->load('items', 'courier'),
            'slots' => DeliverySchedule::labels(),
            'bank' => config('flore.payment'),
        ]);
    }

    public function uploadProof(Request $request, Order $order, OrderWorkflow $workflow)
    {
        $this->authorizeOwner($order);

        $request->validate([
            'payment_proof' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:3072'],
        ], [
            'payment_proof.required' => 'Pilih file bukti pembayaran.',
            'payment_proof.mimes' => 'Format harus JPG, PNG, WEBP, atau PDF.',
            'payment_proof.max' => 'Ukuran file maksimal 3 MB.',
        ]);

        try {
            $workflow->uploadProof($order, $request->file('payment_proof'));
        } catch (OrderException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Bukti pembayaran terkirim. Admin akan memverifikasi secepatnya.');
    }

    public function cancel(Order $order, OrderWorkflow $workflow)
    {
        $this->authorizeOwner($order);

        try {
            $workflow->cancel($order, byAdmin: false);
        } catch (OrderException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Pesanan dibatalkan.');
    }

    /** Bukti pembayaran: hanya pemilik order dan admin. */
    public function proof(Order $order)
    {
        abort_unless($order->user_id === auth()->id() || auth()->user()->isAdmin(), 404);
        abort_unless($order->payment_proof && Storage::disk('local')->exists($order->payment_proof), 404);

        return Storage::disk('local')->response($order->payment_proof, null, ['X-Content-Type-Options' => 'nosniff']);
    }

    /** Foto hasil rangkaian sebelum lulus QC ("Live studio arrangement snapshot", mockup hal. 45). */
    public function qcPhoto(Order $order)
    {
        $this->authorizeOwner($order);
        $disk = \App\Services\FulfillmentService::PHOTO_DISK;
        abort_unless($order->qc_photo && Storage::disk($disk)->exists($order->qc_photo), 404);

        return Storage::disk($disk)->response($order->qc_photo, null, ['X-Content-Type-Options' => 'nosniff']);
    }

    /** Foto serah terima dari kurir (Proof of Delivery) — hanya pemilik pesanan. */
    public function deliveryPhoto(Order $order)
    {
        $this->authorizeOwner($order);
        $disk = \App\Services\DeliveryDispatch::PHOTO_DISK;
        abort_unless($order->delivery_photo && Storage::disk($disk)->exists($order->delivery_photo), 404);

        return Storage::disk($disk)->response($order->delivery_photo, null, ['X-Content-Type-Options' => 'nosniff']);
    }

    private function authorizeOwner(Order $order): void
    {
        abort_unless($order->user_id === auth()->id(), 404);
    }
}