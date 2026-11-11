<?php

namespace App\Services;

use App\Models\Bouquet;
use App\Models\Flower;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Testimonial;
use App\Models\WizardQuery;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Angka untuk Admin Dashboard & Modul Laporan.
 * Pendapatan = pesanan yang pembayarannya dikonfirmasi admin dan tidak dibatalkan, dihitung pada tanggal paid_at.
 */
class SalesAnalytics
{
    public const ACTIVE_STATUSES = ['payment_confirmed', 'preparing', 'out_for_delivery'];

    /** KPI dashboard — juga dikirim sebagai JSON untuk pembaruan otomatis. */
    public function dashboardMetrics(): array
    {
        $today = today();
        $revenueToday = $this->revenueBetween($today, $today);
        $revenueYesterday = $this->revenueBetween($today->copy()->subDay(), $today->copy()->subDay());

        $wizardSince = $today->copy()->subDays(29);
        $stock = $this->stockAlerts();

        return [
            'revenue_total' => (float) Order::paid()->sum('total_amount'),
            'revenue_today' => $revenueToday,
            'revenue_change' => $revenueYesterday > 0 ? round(($revenueToday - $revenueYesterday) / $revenueYesterday * 100, 1) : null,

            'active_orders' => Order::whereIn('status', self::ACTIVE_STATUSES)->count(),
            'preparing' => Order::where('status', 'preparing')->count(),
            'out_for_delivery' => Order::where('status', 'out_for_delivery')->count(),
            'awaiting_verification' => Order::awaitingVerification()->count(),

            'wizard_today' => WizardQuery::whereDate('created_at', $today)->count(),
            'wizard_30d' => WizardQuery::where('created_at', '>=', $wizardSince)->count(),
            'wizard_conversion' => $this->wizardConversion($wizardSince),

            'stock_alerts' => $stock['bouquets']->count() + $stock['flowers']->count(),
            'stock_out' => $stock['bouquets']->where('stock', '<=', 0)->count() + $stock['flowers']->where('stock', '<=', 0)->count(),

            'pending_testimonials' => Testimonial::withStatus('pending')->count(),
            'updated_at' => now()->format('H:i:s'),
        ];
    }

    public function revenueBetween(CarbonInterface $from, CarbonInterface $to): float
    {
        return (float) Order::paid()
            ->whereBetween('paid_at', [Carbon::parse($from)->startOfDay(), Carbon::parse($to)->endOfDay()])
            ->sum('total_amount');
    }

    /**
     * Pendapatan & jumlah pesanan lunas per hari, hari kosong diisi 0.
     *
     * @return Collection<int, array{date:Carbon, revenue:float, orders:int}>
     */
    public function dailyRevenue(CarbonInterface $from, CarbonInterface $to): Collection
    {
        $from = Carbon::parse($from)->startOfDay();
        $to = Carbon::parse($to)->endOfDay();

        $rows = Order::paid()->whereBetween('paid_at', [$from, $to])
            ->selectRaw('DATE(paid_at) as day, SUM(total_amount) as revenue, COUNT(*) as orders')
            ->groupBy('day')->get()->keyBy(fn ($r) => Carbon::parse($r->day)->toDateString());

        $days = collect();
        for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
            $row = $rows->get($d->toDateString());
            $days->push(['date' => $d->copy(), 'revenue' => (float) ($row->revenue ?? 0), 'orders' => (int) ($row->orders ?? 0)]);
        }

        return $days;
    }

    /** Ringkasan penjualan dalam rentang tanggal. */
    public function salesSummary(CarbonInterface $from, CarbonInterface $to): array
    {
        $range = [Carbon::parse($from)->startOfDay(), Carbon::parse($to)->endOfDay()];
        $paid = Order::paid()->whereBetween('paid_at', $range);
        $count = (clone $paid)->count();
        $revenue = (float) (clone $paid)->sum('total_amount');

        return [
            'revenue' => $revenue,
            'orders' => $count,
            'average' => $count ? $revenue / $count : 0,
            'items' => (int) OrderItem::whereHas('order', fn ($q) => $q->paid()->whereBetween('paid_at', $range))->sum('quantity'),
            'cancelled' => Order::where('status', 'cancelled')->whereBetween('cancelled_at', $range)->count(),
            'unpaid_value' => (float) Order::where('status', 'pending_payment')->whereBetween('created_at', $range)->sum('total_amount'),
        ];
    }

    /**
     * Produk terlaris dalam rentang tanggal (dari pesanan lunas).
     *
     * @param  'bouquet'|'flower'  $kind
     */
    public function bestSellers(CarbonInterface $from, CarbonInterface $to, string $kind = 'bouquet', ?int $limit = 10): Collection
    {
        $range = [Carbon::parse($from)->startOfDay(), Carbon::parse($to)->endOfDay()];
        $nameCol = $kind === 'flower' ? 'flower_name' : 'bouquet_name';
        $idCol = $kind === 'flower' ? 'flower_id' : 'bouquet_id';

        $rows = OrderItem::whereNotNull($idCol)
            ->whereHas('order', fn ($q) => $q->paid()->whereBetween('paid_at', $range))
            ->selectRaw("{$idCol} as product_id, MAX({$nameCol}) as product_name, SUM(quantity) as qty, SUM(subtotal) as revenue, COUNT(DISTINCT order_id) as orders")
            ->groupBy($idCol)
            ->orderByDesc('qty')->orderByDesc('revenue')
            ->when($limit, fn ($q) => $q->limit($limit))
            ->get();

        $total = max(1.0, (float) $rows->sum('revenue'));

        return $rows->map(fn ($r) => [
            'id' => (int) $r->product_id,
            'name' => $r->product_name,
            'qty' => (int) $r->qty,
            'orders' => (int) $r->orders,
            'revenue' => (float) $r->revenue,
            'share' => round((float) $r->revenue / $total * 100, 1),
        ]);
    }

    /** Bouquet & bunga per tangkai yang menipis / habis. */
    public function stockAlerts(): array
    {
        return [
            'bouquets' => Bouquet::where(fn ($q) => $q->where('stock', '<=', 0)->orWhereColumn('stock', '<=', 'low_stock_threshold'))
                ->orderBy('stock')->get(['id', 'name', 'stock', 'low_stock_threshold']),
            'flowers' => Flower::whereColumn('stock', '<=', 'reorder_level')
                ->orderBy('stock')->get(['id', 'name', 'stock', 'reorder_level']),
        ];
    }

    /**
     * Preferensi yang paling sering dipilih di Smart Wizard (30 hari terakhir).
     *
     * @param  'occasion'|'color'|'style'|'budget'  $field
     */
    public function wizardPreferences(string $field, int $limit = 4): Collection
    {
        $rows = WizardQuery::where('created_at', '>=', today()->subDays(29))->whereNotNull($field)
            ->selectRaw("{$field} as value, COUNT(*) as total")->groupBy($field)
            ->orderByDesc('total')->limit($limit)->get();

        $sum = max(1, (int) WizardQuery::where('created_at', '>=', today()->subDays(29))->whereNotNull($field)->count());

        return $rows->map(fn ($r) => [
            'value' => $r->value,
            'total' => (int) $r->total,
            'percent' => (int) round($r->total / $sum * 100),
        ]);
    }

    /**
     * % customer (login) yang memakai wizard lalu membuat pesanan setelahnya, dalam periode.
     */
    public function wizardConversion(CarbonInterface $since): ?int
    {
        $firstUse = WizardQuery::whereNotNull('user_id')->where('created_at', '>=', $since)
            ->selectRaw('user_id, MIN(created_at) as first_at')->groupBy('user_id')->pluck('first_at', 'user_id');

        if ($firstUse->isEmpty()) {
            return null;
        }

        $lastOrder = Order::whereIn('user_id', $firstUse->keys())->where('created_at', '>=', $since)
            ->selectRaw('user_id, MAX(created_at) as last_at')->groupBy('user_id')->pluck('last_at', 'user_id');

        $converted = $firstUse->filter(fn ($first, $userId) => isset($lastOrder[$userId])
            && Carbon::parse($lastOrder[$userId])->gte(Carbon::parse($first)))->count();

        return (int) round($converted / $firstUse->count() * 100);
    }
}
