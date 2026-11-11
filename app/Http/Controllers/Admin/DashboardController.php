<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bouquet;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;

class DashboardController extends Controller
{
    public function index()
    {
        $paid = Order::where('payment_status', 'confirmed')->where('status', '!=', 'cancelled');

        return view('admin.dashboard', [
            'customers' => User::where('role', 'customer')->count(),
            'bouquets' => Bouquet::count(),
            'orders' => Order::count(),

            'revenue' => (float) (clone $paid)->sum('total_amount'),
            'revenueToday' => (float) (clone $paid)->whereDate('paid_at', today())->sum('total_amount'),
            'activeOrders' => Order::whereIn('status', ['payment_confirmed', 'preparing', 'out_for_delivery'])->count(),
            'preparing' => Order::where('status', 'preparing')->count(),
            'awaiting' => Order::awaitingVerification()->count(),

            'recentOrders' => Order::with('items')->latest()->limit(6)->get(),

            // Momen yang paling sering dipilih customer saat checkout
            'occasions' => Order::where('status', '!=', 'cancelled')->whereNotNull('occasion')->where('occasion', '!=', '')
                ->selectRaw('occasion, COUNT(*) as total')->groupBy('occasion')->orderByDesc('total')->limit(5)->get(),

            'bestSellers' => OrderItem::whereNotNull('bouquet_name')
                ->whereHas('order', fn ($q) => $q->where('status', '!=', 'cancelled'))
                ->selectRaw('bouquet_name, SUM(quantity) as qty, SUM(subtotal) as revenue')
                ->groupBy('bouquet_name')->orderByDesc('qty')->limit(5)->get(),

            'lowStockCount' => Bouquet::where('stock', '<=', 0)->orWhere(fn ($q) => $q->lowStock())->count(),
            'lowStock' => Bouquet::whereColumn('stock', '<=', 'low_stock_threshold')->orderBy('stock')->limit(6)->get(),
        ]);
    }
}
