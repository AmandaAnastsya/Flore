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

            return $next;
        });
    }

    /** Batalkan dan kembalikan stok, atomik dan hanya sekali. */
    public function cancel(Order $order, bool $byAdmin): void
    {
        DB::transaction(function () use ($order, $byAdmin) {
            $locked = $this->lock($order);

            $allowed = $byAdmin ? $locked->canBeCancelledByAdmin() : $locked->canBeCancelledByCustomer();

            if (! $allowed) {
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

            $locked->update(['status' => 'cancelled', 'cancelled_at' => now()]);
        });
    }

    /** Baca ulang baris order dengan kunci, supaya status yang dicek adalah yang terbaru. */
    private function lock(Order $order): Order
    {
        return Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
    }
}