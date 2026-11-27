<?php

namespace App\Services;

use App\Exceptions\OrderException;
use App\Models\Courier;
use App\Models\Order;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Logistics & Courier Dispatch: tugaskan kurir → berangkatkan → (re-route / kendala) → konfirmasi terkirim (POD).
 * Semua perubahan memakai kunci baris supaya dua admin tidak saling menimpa.
 */
class DeliveryDispatch
{
    public const PHOTO_DISK = 'local';   // privat: storage/app/private

    /** Tugaskan (atau ganti) kurir sebelum berangkat. */
    public function assign(Order $order, Courier $courier, int $travelMinutes): void
    {
        $this->ensureCourierAvailable($courier);

        DB::transaction(function () use ($order, $courier, $travelMinutes) {
            $locked = $this->lock($order);

            if (! in_array($locked->status, ['payment_confirmed', 'preparing'], true)) {
                throw new OrderException('Kurir hanya bisa ditugaskan untuk pesanan yang sudah dibayar dan belum dikirim.');
            }

            $locked->forceFill(['courier_id' => $courier->id, 'travel_minutes' => $travelMinutes])->save();
        });
    }

    /** Bouquet selesai dirangkai & diserahkan ke kurir: status → Dalam pengiriman. */
    public function dispatch(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $locked = $this->lock($order);

            if ($locked->status !== 'preparing') {
                throw new OrderException('Hanya pesanan berstatus "Sedang disiapkan" yang bisa diberangkatkan.');
            }
            if (! $locked->courier_id) {
                throw new OrderException('Tugaskan kurir terlebih dahulu.');
            }

            $now = now();
            $locked->forceFill([
                'status' => 'out_for_delivery',
                'dispatched_at' => $now,
                'eta_at' => $now->copy()->addMinutes($locked->travel_minutes ?: 60),
            ])->save();
        });
    }

    /** Ganti kurir dan/atau perbarui ETA saat pesanan sudah di jalan. */
    public function reroute(Order $order, Courier $courier, int $minutesFromNow): void
    {
        $this->ensureCourierAvailable($courier);

        DB::transaction(function () use ($order, $courier, $minutesFromNow) {
            $locked = $this->lock($order);

            if ($locked->status !== 'out_for_delivery') {
                throw new OrderException('Re-route hanya untuk pesanan yang sedang dalam perjalanan.');
            }

            $locked->forceFill([
                'courier_id' => $courier->id,
                'travel_minutes' => $minutesFromNow,
                'eta_at' => now()->addMinutes($minutesFromNow),
            ])->save();
        });
    }

    public function reportIssue(Order $order, string $note): void
    {
        DB::transaction(function () use ($order, $note) {
            $locked = $this->lock($order);

            if ($locked->status !== 'out_for_delivery') {
                throw new OrderException('Kendala hanya bisa dicatat untuk pesanan yang sedang dikirim.');
            }

            $locked->forceFill(['delivery_issue' => $note, 'issue_reported_at' => now()])->save();
        });
    }

    public function resolveIssue(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $this->lock($order)->forceFill(['delivery_issue' => null, 'issue_reported_at' => null])->save();
        });
    }

    /** Konfirmasi terkirim + bukti serah terima (Proof of Delivery). */
    public function confirmDelivered(Order $order, string $receivedBy, ?UploadedFile $photo = null): void
    {
        $path = $photo?->store('delivery-photos', self::PHOTO_DISK);

        try {
            DB::transaction(function () use ($order, $receivedBy, $path) {
                $locked = $this->lock($order);

                if ($locked->status !== 'out_for_delivery') {
                    throw new OrderException('Hanya pesanan yang sedang dalam perjalanan yang bisa dikonfirmasi terkirim.');
                }

                $locked->forceFill([
                    'status' => 'delivered',
                    'delivered_at' => now(),
                    'received_by' => $receivedBy,
                    'delivery_photo' => $path,
                    'delivery_issue' => null,
                    'issue_reported_at' => null,
                ])->save();
            });
        } catch (\Throwable $e) {
            if ($path) {
                Storage::disk(self::PHOTO_DISK)->delete($path);   // jangan tinggalkan file yatim
            }
            throw $e;
        }
    }

    private function ensureCourierAvailable(Courier $courier): void
    {
        if (! $courier->is_active) {
            throw new OrderException("Kurir {$courier->name} sedang nonaktif.");
        }
    }

    private function lock(Order $order): Order
    {
        return Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
    }
}
