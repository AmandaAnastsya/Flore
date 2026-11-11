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
        ]);
    }

    /** Dipanggil berkala oleh dashboard (polling) agar KPI selalu terbaru. */
    public function metrics(SalesAnalytics $analytics)
    {
        return response()->json($analytics->dashboardMetrics())
            ->header('Cache-Control', 'no-store');
    }
}
