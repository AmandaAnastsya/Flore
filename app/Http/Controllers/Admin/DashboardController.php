<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bouquet;
use App\Models\Order;
use App\Models\User;

class DashboardController extends Controller
{
    public function index()
    {
        return view('admin.dashboard', [
            'customers' => User::where('role', 'customer')->count(),
            'bouquets' => Bouquet::count(),
            'orders' => Order::count(),
            'lowStockCount' => Bouquet::where('stock', '<=', 0)->orWhere(fn ($q) => $q->lowStock())->count(),
            'lowStock' => Bouquet::whereColumn('stock', '<=', 'low_stock_threshold')->orderBy('stock')->limit(5)->get(),
        ]);
    }
}