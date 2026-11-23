<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use App\Services\ReminderPlanner;

/** FR-11: detail data customer (profil, tanggal penting, riwayat pesanan) — Site Map "Customer Details". */
class CustomerController extends Controller
{
    public function show(User $user, ReminderPlanner $planner)
    {
        abort_unless($user->role === 'customer', 404);

        $user->load('specialMoments');

        return view('admin.customers.show', [
            'customer' => $user,
            'orders' => $user->orders()->with('items')->latest()->paginate(10),
            'stats' => [
                'orders' => $user->orders()->count(),
                'delivered' => $user->orders()->where('status', 'delivered')->count(),
                'spent' => (float) $user->orders()->paid()->sum('total_amount'),
                'cancelled' => $user->orders()->where('status', 'cancelled')->count(),
            ],
            'upcoming' => $planner->upcomingFor($user)->take(4),
            'testimonials' => $user->testimonials()->latest()->limit(3)->get(),
            'statusLabels' => Order::STATUS_LABELS,
            'statusBadges' => Order::STATUS_BADGES,
        ]);
    }
}
