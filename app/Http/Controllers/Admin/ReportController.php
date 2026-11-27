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

    /** Format ekspor laporan: CSV, Excel (.xlsx) dan halaman cetak (Simpan sebagai PDF dari browser). */
    public const EXPORTS = ['csv', 'xlsx', 'print'];

    public function sales(Request $request, SalesAnalytics $analytics)
    {
        [$from, $to, $preset] = $this->range($request);

        $transactions = Order::paid()->with('user', 'items')
            ->whereBetween('paid_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->latest('paid_at');

        if (in_array($request->query('export'), self::EXPORTS, true)) {
            return $this->csv("laporan-penjualan_{$from->toDateString()}_{$to->toDateString()}.csv",
                ['No. Pesanan', 'Tanggal Lunas', 'Customer', 'Email', 'Penerima', 'Kota', 'Metode Pembayaran', 'Subtotal', 'Ongkir', 'Diskon', 'Total', 'HPP', 'Laba Kotor', 'Status'],
                $transactions->cursor()->map(fn (Order $o) => [
                    $o->order_number, $o->paid_at?->format('Y-m-d H:i'), $o->user?->name, $o->user?->email,
                    $o->recipient_name, $o->delivery_city, 'Transfer ' . config('flore.payment.bank'), $o->subtotal, $o->delivery_fee, $o->discount, $o->total_amount,
                    ...($o->items->every(fn ($i) => $i->unit_cost !== null)
                        ? [$o->items->sum(fn ($i) => (float) $i->unit_cost * $i->quantity), $o->items->sum(fn ($i) => $i->grossProfit())]
                        : ['', '']),
                    Order::STATUS_LABELS[$o->status] ?? $o->status,
                ]));
        }

        return view('admin.reports.sales', [
            'from' => $from, 'to' => $to, 'preset' => $preset, 'presets' => self::PRESETS,
            'summary' => $analytics->salesSummary($from, $to),
            'series' => $analytics->dailyRevenue($from, $to),
            'trend' => $analytics->monthlyTrend(6),
            'topProducts' => $analytics->bestSellers($from, $to, 'bouquet', 4),
            'transactions' => $transactions->paginate(15)->withQueryString(),
        ]);
    }

    public function products(Request $request, SalesAnalytics $analytics)
    {
        [$from, $to, $preset] = $this->range($request);

        $bouquets = $analytics->bestSellers($from, $to, 'bouquet', null);
        $flowers = $analytics->bestSellers($from, $to, 'flower', null);

        if (in_array($request->query('export'), self::EXPORTS, true)) {
            $rows = $bouquets->map(fn ($r, $i) => [$i + 1, 'Bouquet', $r['name'], $r['qty'], $r['orders'], $r['revenue'], $r['share'] . '%', $r['profit'] ?? '', $r['margin'] === null ? '' : $r['margin'] . '%'])
                ->concat($flowers->map(fn ($r, $i) => [$i + 1, 'Per tangkai', $r['name'], $r['qty'], $r['orders'], $r['revenue'], $r['share'] . '%', $r['profit'] ?? '', $r['margin'] === null ? '' : $r['margin'] . '%']));

            return $this->csv("produk-terlaris_{$from->toDateString()}_{$to->toDateString()}.csv",
                ['Peringkat', 'Jenis', 'Produk', 'Terjual', 'Jumlah Pesanan', 'Pendapatan', 'Kontribusi', 'Laba Kotor', 'Margin'], $rows);
        }

        return view('admin.reports.products', [
            'from' => $from, 'to' => $to, 'preset' => $preset, 'presets' => self::PRESETS,
            'bouquets' => $bouquets, 'flowers' => $flowers,
        ]);
    }

    /** Biaya operasional (gaji, sewa, listrik, …) untuk menghitung Laba Bersih. */
    public function expenses(Request $request)
    {
        [$from, $to, $preset] = $this->range($request);
        $query = \App\Models\Expense::with('user')->whereBetween('spent_on', [$from->toDateString(), $to->toDateString()])
            ->latest('spent_on')->latest('id');

        if (in_array($request->query('export'), self::EXPORTS, true)) {
            return $this->csv("biaya-operasional_{$from->toDateString()}_{$to->toDateString()}.csv",
                ['Tanggal', 'Kategori', 'Keterangan', 'Jumlah', 'Dicatat oleh'],
                $query->get()->map(fn ($e) => [$e->spent_on->toDateString(), \App\Models\Expense::CATEGORIES[$e->category] ?? $e->category,
                    $e->description, (float) $e->amount, $e->user?->name]));
        }

        $all = (clone $query)->get();

        return view('admin.reports.expenses', [
            'from' => $from, 'to' => $to, 'preset' => $preset, 'presets' => self::PRESETS,
            'expenses' => $query->paginate(20)->withQueryString(),
            'total' => (float) $all->sum('amount'),
            'byCategory' => $all->groupBy('category')->map(fn ($g) => (float) $g->sum('amount'))->sortDesc(),
        ]);
    }

    public function storeExpense(Request $request)
    {
        $data = $request->validate([
            'spent_on' => ['required', 'date', 'before_or_equal:today'],
            'category' => ['required', \Illuminate\Validation\Rule::in(array_keys(\App\Models\Expense::CATEGORIES))],
            'description' => ['required', 'string', 'max:200'],
            'amount' => ['required', 'numeric', 'min:1', 'max:999999999'],
        ], [], ['spent_on' => 'tanggal', 'category' => 'kategori', 'description' => 'keterangan', 'amount' => 'jumlah']);

        \App\Models\Expense::create($data + ['user_id' => $request->user()->id]);

        return back()->with('success', 'Biaya operasional dicatat.');
    }

    public function destroyExpense(\App\Models\Expense $expense)
    {
        $expense->delete();

        return back()->with('success', 'Catatan biaya dihapus.');
    }

    /** Inventory Report (Use Case "View Inventory Report"): nilai persediaan, item kritis, pergerakan stok. */
    public function inventory(Request $request)
    {
        [$from, $to, $preset] = $this->range($request);
        $range = [$from->copy()->startOfDay(), $to->copy()->endOfDay()];

        $rows = collect()
            ->concat(\App\Models\Bouquet::with('category')->get()->map(fn ($b) => [
                'type' => 'Bouquet', 'name' => $b->name, 'stock' => $b->stock, 'unit' => 'pcs', 'reorder' => $b->low_stock_threshold,
                'critical' => $b->stock <= $b->low_stock_threshold, 'cost' => $b->cost_price === null ? null : (float) $b->cost_price,
                'active' => $b->is_active,
            ]))
            ->concat(\App\Models\Flower::all()->map(fn ($f) => [
                'type' => 'Bunga per tangkai', 'name' => $f->name, 'stock' => $f->stock, 'unit' => 'tangkai', 'reorder' => $f->reorder_level,
                'critical' => $f->needsRestock(), 'cost' => $f->unitCost(), 'active' => $f->is_active,
            ]))
            ->concat(\App\Models\Supply::all()->map(fn ($s) => [
                'type' => \App\Models\Supply::TYPES[$s->type], 'name' => trim($s->name . ' ' . $s->variant), 'stock' => $s->stock, 'unit' => $s->unit,
                'reorder' => $s->reorder_level, 'critical' => $s->needsRestock(), 'cost' => $s->unitCost(), 'active' => $s->is_active,
            ]))
            ->map(fn ($r) => $r + ['value' => $r['cost'] === null ? null : $r['cost'] * $r['stock']])
            ->sortBy([['critical', 'desc'], ['type', 'asc'], ['name', 'asc']])->values();

        if (in_array($request->query('export'), self::EXPORTS, true)) {
            return $this->csv('laporan-inventaris_' . today()->toDateString() . '.csv',
                ['Jenis', 'Item', 'Stok', 'Satuan', 'Batas Restock', 'Status', 'HPP per Unit', 'Nilai Persediaan', 'Aktif'],
                $rows->map(fn ($r) => [$r['type'], $r['name'], $r['stock'], $r['unit'], $r['reorder'], $r['critical'] ? 'Kritis' : 'Aman',
                    $r['cost'] ?? '', $r['value'] ?? '', $r['active'] ? 'Ya' : 'Tidak']));
        }

        $moves = \App\Models\StockMovement::whereBetween('created_at', $range)->get();
        $sum = fn ($reason) => [
            'qty' => (int) abs($moves->where('reason', $reason)->sum('change')),
            'value' => (float) $moves->where('reason', $reason)->sum(fn ($m) => $m->value() ?? 0),
            'count' => $moves->where('reason', $reason)->count(),
        ];
        $sold = \App\Models\OrderItem::whereHas('order', fn ($q) => $q->paid()->whereBetween('paid_at', $range));

        return view('admin.reports.inventory', [
            'from' => $from, 'to' => $to, 'preset' => $preset, 'presets' => self::PRESETS,
            'rows' => $rows,
            'summary' => [
                'value' => (float) $rows->sum('value'),
                'uncosted' => $rows->whereNull('cost')->where('stock', '>', 0)->count(),
                'critical' => $rows->where('critical', true)->where('active', true)->count(),
                'restock' => $sum('restock'),
                'waste' => $sum('waste'),
                'adjustment' => $sum('adjustment'),
                'sold_bouquets' => (int) (clone $sold)->whereNotNull('bouquet_id')->sum('quantity'),
                'sold_stems' => (int) (clone $sold)->whereNotNull('flower_id')->sum('quantity'),
            ],
            'movements' => \App\Models\StockMovement::with('user')->whereBetween('created_at', $range)->latest()->latest('id')->paginate(20)->withQueryString(),
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

        if (in_array($request->query('export'), self::EXPORTS, true)) {
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

    /**
     * Ekspor laporan sesuai ?export=: xlsx (Excel), print (halaman cetak → Simpan PDF), atau csv (default).
     * CSV ber-BOM UTF-8 agar langsung rapi dibuka di Excel.
     */
    private function csv(string $filename, array $header, iterable $rows): \Symfony\Component\HttpFoundation\Response
    {
        $format = request()->query('export');
        $base = preg_replace('/\.csv$/', '', $filename);
        $title = ucwords(str_replace('-', ' ', strtok($base, '_')));

        if ($format === 'xlsx') {
            return response(\App\Support\SimpleXlsx::build($title, $header, $rows), 200, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="' . $base . '.xlsx"',
            ]);
        }

        if ($format === 'print') {
            return response()->view('admin.reports.print', [
                'title' => $title, 'header' => $header, 'rows' => collect($rows)->all(),
                'period' => preg_match('/_(\d{4}-\d{2}-\d{2})_(\d{4}-\d{2}-\d{2})$/', $base, $m) ? "{$m[1]} s/d {$m[2]}" : 'per ' . today()->toDateString(),
            ]);
        }

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
