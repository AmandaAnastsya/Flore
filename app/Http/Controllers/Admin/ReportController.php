<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use App\Services\SalesAnalytics;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Modul Laporan: Sales Report, Best-selling Products, Customer Data (FR-12). */
class ReportController extends Controller
{
    public const PRESETS = [
        '7d' => '7 hari',
        '30d' => '30 hari',
        'month' => 'Bulan ini',
        'year' => 'Tahun ini',
    ];

    private const MAX_DAYS = 366;

    public function sales(Request $request, SalesAnalytics $analytics)
    {
        [$from, $to, $preset] = $this->range($request);

        $transactions = Order::paid()->with('user')
            ->whereBetween('paid_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->latest('paid_at');

        if ($request->query('export') === 'csv') {
            return $this->csv("laporan-penjualan_{$from->toDateString()}_{$to->toDateString()}.csv",
                ['No. Pesanan', 'Tanggal Lunas', 'Customer', 'Email', 'Penerima', 'Kota', 'Subtotal', 'Ongkir', 'Diskon', 'Total', 'Status'],
                $transactions->cursor()->map(fn (Order $o) => [
                    $o->order_number, $o->paid_at?->format('Y-m-d H:i'), $o->user?->name, $o->user?->email,
                    $o->recipient_name, $o->delivery_city, $o->subtotal, $o->delivery_fee, $o->discount, $o->total_amount,
                    Order::STATUS_LABELS[$o->status] ?? $o->status,
                ]));
        }

        return view('admin.reports.sales', [
            'from' => $from, 'to' => $to, 'preset' => $preset, 'presets' => self::PRESETS,
            'summary' => $analytics->salesSummary($from, $to),
            'series' => $analytics->dailyRevenue($from, $to),
            'transactions' => $transactions->paginate(15)->withQueryString(),
        ]);
    }

    public function products(Request $request, SalesAnalytics $analytics)
    {
        [$from, $to, $preset] = $this->range($request);

        $bouquets = $analytics->bestSellers($from, $to, 'bouquet', null);
        $flowers = $analytics->bestSellers($from, $to, 'flower', null);

        if ($request->query('export') === 'csv') {
            $rows = $bouquets->map(fn ($r, $i) => [$i + 1, 'Bouquet', $r['name'], $r['qty'], $r['orders'], $r['revenue'], $r['share'] . '%'])
                ->concat($flowers->map(fn ($r, $i) => [$i + 1, 'Per tangkai', $r['name'], $r['qty'], $r['orders'], $r['revenue'], $r['share'] . '%']));

            return $this->csv("produk-terlaris_{$from->toDateString()}_{$to->toDateString()}.csv",
                ['Peringkat', 'Jenis', 'Produk', 'Terjual', 'Jumlah Pesanan', 'Pendapatan', 'Kontribusi'], $rows);
        }

        return view('admin.reports.products', [
            'from' => $from, 'to' => $to, 'preset' => $preset, 'presets' => self::PRESETS,
            'bouquets' => $bouquets, 'flowers' => $flowers,
        ]);
    }

    public function customers(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $q = mb_substr($q, 0, 100);
        $sort = in_array($request->query('sort'), ['spent', 'orders', 'newest'], true) ? $request->query('sort') : 'spent';

        $customers = User::where('role', 'customer')
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w->where('name', 'like', "%{$q}%")
                ->orWhere('email', 'like', "%{$q}%")->orWhere('phone', 'like', "%{$q}%")))
            ->withCount('orders')
            ->withSum(['orders as total_spent' => fn ($o) => $o->paid()], 'total_amount')
            ->withMax('orders as last_order_at', 'created_at')
            ->when($sort === 'spent', fn ($query) => $query->orderByDesc('total_spent'))
            ->when($sort === 'orders', fn ($query) => $query->orderByDesc('orders_count'))
            ->orderByDesc('created_at');

        if ($request->query('export') === 'csv') {
            return $this->csv('data-customer_' . today()->toDateString() . '.csv',
                ['Nama', 'Email', 'No. HP', 'Tanggal Lahir', 'Anniversary', 'Terdaftar', 'Jumlah Pesanan', 'Total Belanja', 'Pesanan Terakhir'],
                $customers->cursor()->map(fn (User $u) => [
                    $u->name, $u->email, $u->phone, $u->date_of_birth?->toDateString(), $u->anniversary_date?->toDateString(),
                    $u->created_at->toDateString(), $u->orders_count, (float) $u->total_spent,
                    $u->last_order_at ? Carbon::parse($u->last_order_at)->toDateString() : '',
                ]));
        }

        return view('admin.reports.customers', [
            'customers' => $customers->paginate(20)->withQueryString(),
            'q' => $q,
            'sort' => $sort,
            'stats' => [
                'total' => User::where('role', 'customer')->count(),
                'new_30d' => User::where('role', 'customer')->where('created_at', '>=', today()->subDays(29))->count(),
                'buyers' => User::where('role', 'customer')->whereHas('orders', fn ($o) => $o->paid())->count(),
                'repeat' => User::where('role', 'customer')->has('orders', '>=', 2)->count(),
            ],
        ]);
    }

    /** @return array{0: Carbon, 1: Carbon, 2: ?string} */
    private function range(Request $request): array
    {
        $preset = $request->query('preset');
        $today = today();

        if (is_string($preset) && isset(self::PRESETS[$preset])) {
            $from = match ($preset) {
                '7d' => $today->copy()->subDays(6),
                '30d' => $today->copy()->subDays(29),
                'month' => $today->copy()->startOfMonth(),
                'year' => $today->copy()->startOfYear(),
            };

            return [$from, $today, $preset];
        }

        $from = $this->date($request->query('from')) ?? $today->copy()->subDays(29);
        $to = $this->date($request->query('to')) ?? $today->copy();

        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }
        if ($from->diffInDays($to) > self::MAX_DAYS) {
            $from = $to->copy()->subDays(self::MAX_DAYS);
        }

        $isDefault = ! $request->has('from') && ! $request->has('to');

        return [$from, $to, $isDefault ? '30d' : null];
    }

    private function date($value): ?Carbon
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('!Y-m-d', $value);
        } catch (\Throwable) {
            return null;
        }
    }

    /** CSV ber-BOM UTF-8 agar langsung rapi dibuka di Excel. */
    private function csv(string $filename, array $header, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($header, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $header, ';');
            foreach ($rows as $row) {
                // cegah formula injection saat dibuka di Excel
                fputcsv($out, array_map(fn ($v) => is_string($v) && preg_match('/^[=+\-@]/', $v) ? "'" . $v : $v, $row), ';');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
