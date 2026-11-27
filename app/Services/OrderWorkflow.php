<?php

namespace App\Services;

use App\Exceptions\OrderException;
use App\Models\Bouquet;
use App\Models\Flower;
use App\Models\Order;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class OrderWorkflow
{
    private const DISK = 'local';   // privat: storage/app/private

    /** Customer mengunggah (atau mengganti) bukti pembayaran. */
    public function uploadProof(Order $order, UploadedFile $file): void
    {
        $newPath = $file->store('payment-proofs', self::DISK);
        $oldPath = null;

        try {
            DB::transaction(function () use ($order, $newPath, &$oldPath) {
                $locked = $this->lock($order);

                if (! $locked->canUploadProof()) {
                    throw new OrderException('Bukti pembayaran tidak bisa diunggah untuk pesanan ini.');
                }

                $oldPath = $locked->payment_proof;
                $locked->update(['payment_proof' => $newPath, 'payment_status' => 'proof_uploaded']);
            });
        } catch (\Throwable $e) {
            Storage::disk(self::DISK)->delete($newPath);   // jangan tinggalkan file yatim
            throw $e;
        }

        if ($oldPath) {
            Storage::disk(self::DISK)->delete($oldPath);
        }
    }

    public function confirmPayment(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $locked = $this->lock($order);

            if (! $locked->isAwaitingVerification()) {
                throw new OrderException('Pembayaran ini tidak sedang menunggu verifikasi.');
            }

            $locked->update([
                'status' => 'payment_confirmed',
                'payment_status' => 'confirmed',
                'paid_at' => now(),
            ]);
        });
    }

    public function rejectPayment(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $locked = $this->lock($order);

            if (! $locked->isAwaitingVerification()) {
                throw new OrderException('Pembayaran ini tidak sedang menunggu verifikasi.');
            }

            $locked->update(['payment_status' => 'rejected']);
        });
    }

    /** Majukan: dikonfirmasi → disiapkan → dikirim → terkirim. */
    public function advance(Order $order): string
    {
        return DB::transaction(function () use ($order) {
            $locked = $this->lock($order);
            $next = $locked->nextStatus();

            if (! $next) {
                throw new OrderException('Status pesanan ini tidak bisa dimajukan.');
            }

            $locked->update(['status' => $next]);

            // jaga data logistik tetap konsisten bila status dimajukan dari halaman Order Management
            if ($next === 'preparing') {
                $locked->forceFill(['fulfillment_stage' => 'arranging', 'stage_updated_at' => now()])->save();
            } elseif ($next === 'out_for_delivery') {
                $locked->forceFill([
                    'dispatched_at' => now(),
                    'eta_at' => now()->addMinutes($locked->travel_minutes ?: 60),
                ])->save();
            } elseif ($next === 'delivered') {
                $locked->forceFill(['delivered_at' => now(), 'delivery_issue' => null, 'issue_reported_at' => null])->save();
            }

            return $next;
        });
    }

    /** Batalkan dan kembalikan stok, atomik dan hanya sekali. */
    public function cancel(Order $order, bool $byAdmin, ?string $reason = null): void
    {
        $this->cancelWith($order, fn (Order $locked) => $byAdmin ? $locked->canBeCancelledByAdmin() : $locked->canBeCancelledByCustomer(),
            $reason ?? ($byAdmin ? 'Dibatalkan oleh admin.' : 'Dibatalkan oleh customer.'));
    }

    /**
     * Batal otomatis bila belum dibayar melewati batas waktu (State Machine hal. 39: autoCancel [payment timeout]).
     * Dicek ulang di dalam kunci baris: kalau customer baru saja mengunggah bukti, pesanan TIDAK dibatalkan.
     *
     * @return bool true bila pesanan dibatalkan
     */
    public function expireUnpaid(Order $order): bool
    {
        try {
            $hours = (int) config('flore.payment.timeout_hours', 24);
            $this->cancelWith($order, fn (Order $locked) => $locked->isPaymentOverdue(),
                "Dibatalkan otomatis karena belum dibayar dalam {$hours} jam.");

            return true;
        } catch (OrderException) {
            return false;
        }
    }

    private function cancelWith(Order $order, callable $allowed, string $reason): void
    {
        DB::transaction(function () use ($order, $allowed, $reason) {
            $locked = $this->lock($order);

            if (! $allowed($locked)) {
                throw new OrderException('Pesanan ini tidak bisa dibatalkan.');
            }

            $items = $locked->items()->get();

            // urut id agar konsisten dengan proses order (hindari deadlock)
            foreach ($items->whereNotNull('bouquet_id')->sortBy('bouquet_id') as $item) {
                Bouquet::withTrashed()->whereKey($item->bouquet_id)->increment('stock', $item->quantity);
            }
            foreach ($items->whereNotNull('flower_id')->sortBy('flower_id') as $item) {
                Flower::withTrashed()->whereKey($item->flower_id)->increment('stock', $item->quantity);
            }

            $locked->forceFill(['cancel_reason' => $reason]);
            $locked->update(['status' => 'cancelled', 'cancelled_at' => now()]);
        });
    }

    /** Baca ulang baris order dengan kunci, supaya status yang dicek adalah yang terbaru. */
    private function lock(Order $order): Order
    {
        return Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
    }
}