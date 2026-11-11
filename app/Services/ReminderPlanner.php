<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Reminder;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Smart Reminder System — menentukan momen yang akan datang dan kapan email dikirim.
 *
 * Sumber momen (berulang tiap tahun):
 *  1. Tanggal lahir customer            (users.date_of_birth)
 *  2. Tanggal anniversary customer      (users.anniversary_date)
 *  3. Momen orang terdekat              (special_moments)
 *  4. Setahun sejak pesanan terkirim    (orders, status delivered) — "recurring purchase anniversary"
 */
class ReminderPlanner
{
    /** Pilihan "kirim pengingat H-?" di profil customer. */
    public const DAY_OPTIONS = [1, 3, 7, 14];

    /** Batas pesanan lama yang ikut dijadikan pengingat, per customer. */
    private const ORDER_LOOKBACK = 20;

    /**
     * Momen customer dalam 12 bulan ke depan, urut tanggal terdekat.
     *
     * @return Collection<int, array{key:string,type:string,title:string,occasion_date:Carbon,remind_at:Carbon,
     *     days_left:int,special_moment_id:?int,order_id:?int,catalog_occasion:?string}>
     */
    public function upcomingFor(User $user, ?CarbonInterface $today = null): Collection
    {
        $today = Carbon::parse($today ?? today())->startOfDay();
        $lead = in_array($user->reminder_days, self::DAY_OPTIONS, true) ? $user->reminder_days : 7;
        $moments = collect();

        $add = function (string $source, string $type, string $title, CarbonInterface $base, array $extra = [])
            use (&$moments, $today, $lead): void {
            $occasion = self::nextOccurrence($base, $today);

            $moments->push(array_merge([
                'key' => "{$source}:{$occasion->toDateString()}",
                'type' => $type,
                'title' => $title,
                'occasion_date' => $occasion,
                'remind_at' => $occasion->copy()->subDays($lead),
                'days_left' => (int) $today->diffInDays($occasion),
                'special_moment_id' => null,
                'order_id' => null,
                'catalog_occasion' => in_array($type, ['birthday', 'anniversary'], true) ? $type : null,
            ], $extra));
        };

        if ($user->date_of_birth) {
            $add("u{$user->id}-birthday", 'birthday', 'Ulang tahunmu', $user->date_of_birth);
        }

        if ($user->anniversary_date) {
            $add("u{$user->id}-anniversary", 'anniversary', 'Anniversary-mu', $user->anniversary_date);
        }

        foreach ($user->specialMoments as $moment) {
            $add("m{$moment->id}", $moment->type, $moment->title, $moment->date, ['special_moment_id' => $moment->id]);
        }

        $orders = $user->orders()->with('items')->where('status', 'delivered')
            ->latest('delivery_date')->limit(self::ORDER_LOOKBACK)->get();

        foreach ($orders as $order) {
            // hanya ulang tahun pesanan (minimal 1 tahun), bukan hari pengirimannya sendiri
            if (self::nextOccurrence($order->delivery_date, $today)->year <= $order->delivery_date->year) {
                continue;
            }

            $what = $order->items->map(fn ($i) => $i->bouquet_name ?? $i->flower_name)->filter()->first() ?? 'bunga';
            $add("o{$order->id}", 'recurring_purchase', "Setahun lalu kamu mengirim {$what} untuk {$order->recipient_name}",
                $order->delivery_date, ['order_id' => $order->id, 'catalog_occasion' => $order->occasion]);
        }

        return $moments->sortBy(fn ($m) => $m['occasion_date']->timestamp)->values();
    }

    /**
     * Buat baris reminder (status pending) untuk semua momen yang sudah masuk jadwal kirim.
     * Idempoten: dedupe_key unik per momen per tahun, jadi aman dijalankan berkali-kali.
     *
     * @return int jumlah reminder baru
     */
    public function plan(?CarbonInterface $today = null): int
    {
        $today = Carbon::parse($today ?? today())->startOfDay();
        $created = 0;

        $this->candidates()
            ->chunkById(200, function ($users) use ($today, &$created) {
                foreach ($users as $user) {
                    foreach ($this->upcomingFor($user, $today) as $m) {
                        if ($m['remind_at']->gt($today)) {
                            continue;   // belum waktunya
                        }

                        $reminder = Reminder::firstOrCreate(['dedupe_key' => $m['key']], [
                            'user_id' => $user->id,
                            'order_id' => $m['order_id'],
                            'special_moment_id' => $m['special_moment_id'],
                            'type' => $m['type'],
                            'title' => $m['title'],
                            'occasion_date' => $m['occasion_date']->toDateString(),
                            'remind_at' => $m['remind_at']->toDateString(),
                            'channel' => 'email',
                            'status' => 'pending',
                        ]);

                        $created += $reminder->wasRecentlyCreated ? 1 : 0;
                    }
                }
            });

        return $created;
    }

    /**
     * Ringkasan untuk admin: pengingat yang AKAN dikirim (belum jatuh tempo), urut tanggal kirim.
     *
     * @return Collection<int, array> setiap item = data momen + 'user'
     */
    public function scheduleOverview(int $limit = 15, ?CarbonInterface $today = null): Collection
    {
        $today = Carbon::parse($today ?? today())->startOfDay();

        return $this->candidates()->limit(500)->get()
            ->flatMap(fn (User $user) => $this->upcomingFor($user, $today)
                ->filter(fn ($m) => $m['remind_at']->gt($today))
                ->map(fn ($m) => $m + ['user' => $user]))
            ->sortBy(fn ($m) => $m['remind_at']->timestamp)
            ->take($limit)
            ->values();
    }

    /** Customer yang mengaktifkan pengingat dan punya minimal satu sumber momen. */
    private function candidates()
    {
        return User::query()
            ->where('role', 'customer')
            ->where('reminders_enabled', true)
            ->where(fn ($q) => $q->whereNotNull('date_of_birth')
                ->orWhereNotNull('anniversary_date')
                ->orWhereHas('specialMoments')
                ->orWhereHas('orders', fn ($o) => $o->where('status', 'delivered')))
            ->with('specialMoments');
    }

    /**
     * Tanggal berikutnya (hari ini atau setelahnya) dengan bulan & tanggal yang sama.
     * 29 Februari dirayakan 28 Februari pada tahun non-kabisat.
     */
    public static function nextOccurrence(CarbonInterface $date, CarbonInterface $today): Carbon
    {
        $today = Carbon::parse($today)->startOfDay();

        foreach ([$today->year, $today->year + 1] as $year) {
            $day = min($date->day, Carbon::create($year, $date->month, 1)->daysInMonth);
            $candidate = Carbon::create($year, $date->month, $day)->startOfDay();

            if ($candidate->gte($today)) {
                return $candidate;
            }
        }

        return $candidate;   // tidak tercapai
    }
}
