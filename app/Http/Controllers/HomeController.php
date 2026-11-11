<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index()
{
    if (auth()->user()->isAdmin()) {
        return redirect()->route('admin.dashboard');
    }

    $user = auth()->user();

    return view('home', [
        'wizardOptions' => app(\App\Services\RecommendationEngine::class)->options(),
        'openWizard' => request()->boolean('wizard'),
        'orderCount' => $user->orders()->count(),
        'activeCount' => $user->orders()->whereNotIn('status', ['delivered', 'cancelled'])->count(),
        'recentOrders' => $user->orders()->with('items')->latest()->limit(3)->get(),
        'upcoming' => app(\App\Services\ReminderPlanner::class)->upcomingFor($user->load('specialMoments'))->take(3),
    ]);
}
}
