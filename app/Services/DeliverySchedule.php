<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DeliverySchedule
{
    public const SLOTS = [
        'morning'   => ['label' => 'Pagi (09.00–12.00)',  'start' => 9],
        'afternoon' => ['label' => 'Siang (12.00–15.00)', 'start' => 12],
        'evening'   => ['label' => 'Sore (15.00–18.00)',  'start' => 15],
    ];

    public const LEAD_HOURS = 3;   // slot harus mulai minimal sekian jam dari sekarang
    public const MAX_DAYS = 30;    // pesan maksimal sekian hari ke depan

    /** @return array<string,string> [kunci => label] */
    public static function labels(): array
    {
        return array_map(fn ($slot) => $slot['label'], self::SLOTS);
    }

    /** Kunci slot yang masih bisa dipilih pada tanggal tertentu. */
    public static function slotsFor(Carbon $day, ?Carbon $now = null): array
    {
        $now ??= now();
        $earliest = $now->copy()->addHours(self::LEAD_HOURS);
        $day = $day->copy()->startOfDay();

        return array_keys(array_filter(
            self::SLOTS,
            fn ($slot) => $day->copy()->setTime($slot['start'], 0)->gte($earliest)
        ));
    }

    /** Same-day hanya jika SEMUA bouquet di keranjang berlabel same-day. */
    public static function sameDayPossible(Collection $bouquetLines): bool
    {
        return $bouquetLines->every(fn ($line) => (bool) $line['bouquet']->is_same_day);
    }

    /** Tanggal paling awal yang bisa dipilih: hari ini (kalau mungkin) atau besok. */
    public static function earliestDate(bool $sameDayOk, ?Carbon $now = null): Carbon
    {
        $now ??= now();
        $today = $now->copy()->startOfDay();

        return ($sameDayOk && self::slotsFor($today, $now))
            ? $today
            : $today->addDay();
    }

    /** Mengembalikan [field, pesan] kalau jadwal tidak valid, atau null kalau valid. */
    public static function problem(string $date, string $slot, Collection $bouquetLines, ?Carbon $now = null): ?array
    {
        $now ??= now();
        $today = $now->copy()->startOfDay();
        $day = Carbon::createFromFormat('Y-m-d', $date)->startOfDay();

        if ($day->lt($today)) {
            return ['delivery_date', 'Tanggal pengiriman tidak boleh sudah lewat.'];
        }
        if ($day->gt($today->copy()->addDays(self::MAX_DAYS))) {
            return ['delivery_date', 'Pengiriman maksimal ' . self::MAX_DAYS . ' hari ke depan.'];
        }
        if ($day->equalTo($today) && ! self::sameDayPossible($bouquetLines)) {
            return ['delivery_date', 'Pengiriman hari ini hanya untuk pesanan yang semua bouquet-nya berlabel same-day. Pilih tanggal besok atau setelahnya.'];
        }
        if (! in_array($slot, self::slotsFor($day, $now), true)) {
            return ['delivery_slot', 'Slot waktu ini tidak tersedia untuk tanggal yang dipilih.'];
        }

        return null;
    }
}