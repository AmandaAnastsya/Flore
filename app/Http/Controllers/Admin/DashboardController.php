<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bouquet;
use App\Models\Order;
use App\Models\User;
use App\Services\SalesAnalytics;

class DashboardController extends Controller
{
    public function index(SalesAnalytics $analytics)
    {
        $stock = $analytics->stockAlerts();

        return view('admin.dashboard', [
            'metrics' => $analytics->dashboardMetrics(),
            'customers' => User::where('role', 'customer')->count(),
            'bouquets' => Bouquet::count(),
            'orders' => Order::count(),

            'recentOrders' => Order::with('items')->latest()->limit(6)->get(),
            'revenueSeries' => $analytics->dailyRevenue(today()->subDays(13), today()),
            'wizardOccasions' => $analytics->wizardPreferences('occasion'),
            'wizardColors' => $analytics->wizardPreferences('color', 3),
            'bestSellers' => $analytics->bestSellers(today()->subDays(29), today(), 'bouquet', 5),

            'lowStockBouquets' => $stock['bouquets']->take(6),
            'lowStockFlowers' => $stock['flowers']->take(6),
            'lowStockCount' => $stock['bouquets']->count() + $stock['flowers']->count(),

            // Fresh Floral Inventory & Cold-Room Stock (mockup hal. 44): yang paling mendesak di atas
            'floralStock' => \App\Models\Flower::active()->get()
                ->sortBy(fn ($f) => [
                    match (true) { $f->needsRestock() => 0, $f->freshnessState() === 'expired' => 1, $f->freshnessState() === 'aging' => 2, default => 3 },
                    $f->stock,
                ])->take(6)->values(),
        ]);
    }

    /** Dipanggil berkala oleh dashboard (polling) agar KPI selalu terbaru. */
    public function metrics(SalesAnalytics $analytics)
    {
        return response()->json($analytics->dashboardMetrics())
            ->header('Cache-Control', 'no-store');
    }
}
