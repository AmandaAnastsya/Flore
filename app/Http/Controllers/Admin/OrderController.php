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
    public function index(Request $request)
    {
        $q = $request->query('q');
        $q = is_string($q) ? mb_substr(trim($q), 0, 100) : '';

        $status = $request->query('status');
        $status = is_string($status) && array_key_exists($status, Order::STATUS_LABELS) ? $status : null;

        $payment = $request->query('payment_status');
        $payment = is_string($payment) && array_key_exists($payment, Order::PAYMENT_LABELS) ? $payment : null;

        $orders = Order::with('user')->withCount('items')
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
            'awaiting' => Order::awaitingVerification()->count(),
        ]);
    }

    public function show(Order $order)
    {
        return view('admin.orders.show', [
            'order' => $order->load('items', 'user'),
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