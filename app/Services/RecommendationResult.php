<?php

namespace App\Services;

use Illuminate\Support\Collection;

final class RecommendationResult
{
    /**
     * @param  Collection<int,array>  $items     bouquet terurut + skor + alasan
     * @param  array<string,string>   $criteria  pilihan yang dipakai [criteria => value]
     * @param  array<string,string>   $ignored   pilihan yang diabaikan (tidak valid / rule nonaktif)
     */
    public function __construct(
        public readonly string $mode,        // exact | relaxed_budget | alternatives | browse | none
        public readonly string $message,
        public readonly Collection $items,
        public readonly int $total,          // jumlah kandidat sebelum dipotong limit
        public readonly array $criteria,
        public readonly ?string $budget,     // kunci budget yang dipakai
        public readonly array $ignored,
    ) {
    }

    public function isEmpty(): bool
    {
        return $this->items->isEmpty();
    }
}