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
        ]);
    }
}