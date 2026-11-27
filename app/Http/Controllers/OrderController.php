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

    /**
     * Re-Order (mockup hal. 47 "Track / Re-Order"): isi ulang keranjang dari pesanan lama sesuai stok saat ini,
     * dan siapkan data penerima & kartu ucapan untuk checkout. Tanggal & slot kirim dipilih ulang.
     */
    public function reorder(Order $order, \App\Services\Cart $cart)
    {
        $this->authorizeOwner($order);
        $order->load('items');
        $added = $notes = [];

        foreach ($order->items as $item) {
            if ($item->isStem()) {
                $flower = \App\Models\Flower::find($item->flower_id);
                if (! $flower || ! $flower->is_active || $flower->stock <= 0) {
                    $notes[] = "{$item->name} sedang tidak tersedia";
                    continue;
                }
                $have = $cart->stemQuantities()[$flower->id] ?? 0;
                $qty = min($item->quantity, $flower->stock - $have, \App\Services\Cart::MAX_STEMS - $have);
                if ($qty > 0 && ! $cart->addStem($flower, $qty)) {
                    $added[] = "{$qty} tangkai {$flower->name}";
                }
            } else {
                $bouquet = \App\Models\Bouquet::visible()->find($item->bouquet_id);
                if (! $bouquet || $bouquet->stock <= 0) {
                    $notes[] = "{$item->name} sedang tidak tersedia";
                    continue;
                }
                $have = $cart->quantityOf($bouquet->id);
                $qty = min($item->quantity, $bouquet->stock - $have, \App\Services\Cart::MAX_QTY - $have);
                if ($qty > 0 && ! $cart->add($bouquet, $qty)) {
                    $added[] = "{$qty}× {$bouquet->name}";
                }
            }

            if (isset($qty) && $qty > 0 && $qty < $item->quantity) {
                $notes[] = "{$item->name} hanya tersedia {$qty}";
            }
            unset($qty);
        }

        if (! $added) {
            return back()->with('error', 'Maaf, item dari pesanan ini sedang tidak tersedia' . ($notes ? ' (' . implode('; ', $notes) . ').' : '.'));
        }

        // data penerima & kartu dipakai lagi; tanggal & slot sengaja dikosongkan
        session()->put('reorder_draft', collect($order->only([
            'recipient_name', 'recipient_phone', 'delivery_city', 'delivery_address', 'occasion', 'card_message', 'sender_name', 'is_anonymous',
        ]))->all());

        return redirect()->route('cart.index')->with('success', 'Ditambahkan ke keranjang: ' . implode(', ', $added) . '.'
            . ($notes ? ' Catatan: ' . implode('; ', $notes) . '.' : ''));
    }

    /** Invoice / kuitansi yang bisa dicetak atau disimpan sebagai PDF (mockup hal. 47 "Unduh Kuitansi"). */
    public function invoice(Order $order)
    {
        $this->authorizeOwner($order);

        return view('orders.invoice', [
            'order' => $order->load('items', 'user'),
            'slots' => DeliverySchedule::labels(),
            'backUrl' => route('orders.show', $order->order_number),
        ]);
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