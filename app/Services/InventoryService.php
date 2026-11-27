<?php

namespace App\Services;

use App\Exceptions\OrderException;
use App\Models\Flower;
use App\Models\StockMovement;
use App\Models\Supply;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/** Restock, koreksi stok & pencatatan bunga layu — semuanya tercatat di stock_movements. */
class InventoryService
{
    /** Barang masuk. Untuk bunga: tanggal tiba diperbarui (kesegaran dihitung ulang) dan HPP bisa diperbarui. */
    public function restock(Flower|Supply $item, int $qty, ?float $unitCost = null, ?string $note = null): StockMovement
    {
        if ($qty < 1) {
            throw new OrderException('Jumlah restock minimal 1.');
        }

        return DB::transaction(function () use ($item, $qty, $unitCost, $note) {
            $locked = $this->lock($item);
            $locked->stock += $qty;

            if ($unitCost !== null) {
                $locked->forceFill([$locked instanceof Flower ? 'cost_per_stem' : 'cost_per_unit' => $unitCost]);
            }
            if ($locked instanceof Flower) {
                $locked->last_restocked_at = now();
            }
            $locked->save();

            return $this->log($locked, 'restock', $qty, $note);
        });
    }

    /** Koreksi hasil stock opname, atau buang bunga layu/rusak (reason = waste). */
    public function adjust(Flower|Supply $item, int $newStock, string $reason, ?string $note = null): StockMovement
    {
        if (! in_array($reason, ['adjustment', 'waste'], true)) {
            throw new \InvalidArgumentException("Alasan tidak dikenal: {$reason}");
        }

        return DB::transaction(function () use ($item, $newStock, $reason, $note) {
            $locked = $this->lock($item);
            $change = $newStock - $locked->stock;

            if ($change === 0) {
                throw new OrderException('Stok tidak berubah.');
            }
            if ($reason === 'waste' && $change > 0) {
                throw new OrderException('Pencatatan bunga layu harus mengurangi stok.');
            }

            $locked->stock = $newStock;
            $locked->save();

            return $this->log($locked, $reason, $change, $note);
        });
    }

    /** Stok awal saat barang baru dibuat. */
    public function opening(Flower|Supply $item): void
    {
        if ($item->stock > 0) {
            $this->log($item, 'restock', $item->stock, 'Stok awal');
        }
    }

    private function log(Flower|Supply $item, string $reason, int $change, ?string $note): StockMovement
    {
        return StockMovement::create([
            'item_type' => $item instanceof Flower ? 'flower' : 'supply',
            'item_id' => $item->id,
            'item_name' => $item->name,
            'reason' => $reason,
            'change' => $change,
            'stock_after' => $item->stock,
            'unit_cost' => $item->unitCost(),
            'note' => $note,
            'user_id' => auth()->id(),
        ]);
    }

    private function lock(Model $item): Flower|Supply
    {
        return $item::query()->whereKey($item->getKey())->lockForUpdate()->firstOrFail();
    }
}
