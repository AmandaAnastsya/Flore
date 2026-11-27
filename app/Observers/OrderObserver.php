<?php

namespace App\Observers;

use App\Models\Order;
use App\Notifications\OrderUpdate;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Kirim email invoice & update status pesanan ke customer.
 * Kejadian ditentukan saat model disimpan, email dikirim setelah transaksi database berhasil (afterCommit),
 * jadi tidak ada email untuk perubahan yang akhirnya dibatalkan (rollback).
 */
class OrderObserver
{
    public function created(Order $order): void
    {
        $this->queue($order, ['placed']);
    }

    public function updated(Order $order): void
    {
        $events = [];

        if ($order->wasChanged('payment_status')) {
            $events[] = match ($order->payment_status) {
                'proof_uploaded' => 'proof_uploaded',
                'confirmed' => 'paid',
                'rejected' => 'payment_rejected',
                default => null,
            };
        }

        if ($order->wasChanged('status') && in_array($order->status, ['preparing', 'out_for_delivery', 'delivered', 'cancelled'], true)) {
            $events[] = $order->status;
        }

        $this->queue($order, array_filter($events));
    }

    private function queue(Order $order, array $events): void
    {
        if (! $events) {
            return;
        }

        $id = $order->id;
        DB::afterCommit(function () use ($id, $events) {
            $fresh = Order::with('items', 'courier', 'user')->find($id);
            $user = $fresh?->user;

            if (! $user || $user->isDeleted()) {
                return;
            }

            foreach ($events as $event) {
                if (! OrderUpdate::isMandatory($event) && ! $user->order_updates_enabled) {
                    continue;   // customer mematikan email update status (invoice & email penting tetap dikirim)
                }

                try {
                    $user->notify(new OrderUpdate($fresh, $event));
                } catch (Throwable $e) {
                    report($e);   // gagal kirim email tidak membatalkan perubahan pesanan
                }
            }
        });
    }
}
