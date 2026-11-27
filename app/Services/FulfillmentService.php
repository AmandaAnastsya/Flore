<?php

namespace App\Services;

use App\Exceptions\OrderException;
use App\Models\Florist;
use App\Models\Order;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Produksi di studio (mockup hal. 48): tugaskan florist → Potong & Rangkai → QC & Foto → Packing → Siap handover kurir.
 * Tahap ini melengkapi status "Sedang disiapkan" dan tidak mengunci tombol status yang sudah ada.
 */
class FulfillmentService
{
    public const PHOTO_DISK = 'local';

    public function assignFlorist(Order $order, Florist $florist, ?string $note = null): void
    {
        if (! $florist->is_active) {
            throw new OrderException("Florist {$florist->name} sedang nonaktif.");
        }

        DB::transaction(function () use ($order, $florist, $note) {
            $locked = $this->lock($order);

            if (! in_array($locked->status, ['payment_confirmed', 'preparing'], true)) {
                throw new OrderException('Florist hanya bisa ditugaskan untuk pesanan yang sudah dibayar dan belum dikirim.');
            }

            $locked->forceFill(['florist_id' => $florist->id, 'production_note' => $note])->save();
        });
    }

    /** Majukan satu tahap. Dari "Dibayar" langsung mulai Potong & Rangkai. Lulus QC wajib ada foto. */
    public function advanceStage(Order $order, ?UploadedFile $qcPhoto = null): string
    {
        $path = $qcPhoto?->store('qc-photos', self::PHOTO_DISK);

        try {
            return DB::transaction(function () use ($order, $path) {
                $locked = $this->lock($order);

                if ($locked->status === 'payment_confirmed') {
                    $locked->forceFill(['status' => 'preparing', 'fulfillment_stage' => 'arranging', 'stage_updated_at' => now()])->save();

                    return 'arranging';
                }

                $next = $locked->nextStage();
                if (! $next) {
                    throw new OrderException('Tahap produksi pesanan ini tidak bisa dimajukan.');
                }

                $data = ['fulfillment_stage' => $next, 'stage_updated_at' => now()];

                if ($locked->fulfillment_stage === 'qc') {   // qc → packing = lulus QC
                    if (! $path && ! $locked->qc_photo) {
                        throw new OrderException('Unggah foto hasil rangkaian sebelum menandai lulus QC.');
                    }
                    if ($path) {
                        if ($locked->qc_photo) {
                            Storage::disk(self::PHOTO_DISK)->delete($locked->qc_photo);
                        }
                        $data['qc_photo'] = $path;
                    }
                    $data['qc_passed_at'] = now();
                } elseif ($path) {
                    throw new OrderException('Foto QC hanya diunggah pada tahap QC.');
                }

                $locked->forceFill($data)->save();

                return $next;
            });
        } catch (\Throwable $e) {
            if ($path && ! Order::where('qc_photo', $path)->exists()) {
                Storage::disk(self::PHOTO_DISK)->delete($path);
            }
            throw $e;
        }
    }

    private function lock(Order $order): Order
    {
        return Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
    }
}
