<?php

namespace App\Models\Concerns;

/** Level stok untuk Inventory Hub: Habis, Kritis (restock), Sedang, Aman. */
trait HasInventoryLevel
{
    public const LEVELS = [
        'out' => ['Habis', 'danger'],
        'critical' => ['Kritis (Restock)', 'danger'],
        'medium' => ['Sedang', 'warning'],
        'safe' => ['Aman', 'success'],
    ];

    public function inventoryLevel(): string
    {
        return match (true) {
            $this->stock <= 0 => 'out',
            $this->stock <= $this->reorder_level => 'critical',
            $this->stock <= (int) ceil($this->reorder_level * 1.5) => 'medium',
            default => 'safe',
        };
    }

    public function needsRestock(): bool
    {
        return in_array($this->inventoryLevel(), ['out', 'critical'], true);
    }

    public function stockValue(): ?float
    {
        $cost = $this->unitCost();

        return $cost === null ? null : $cost * $this->stock;
    }

    abstract public function unitCost(): ?float;
}
