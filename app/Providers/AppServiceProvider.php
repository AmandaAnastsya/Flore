<?php

namespace App\Providers;

use App\Services\Cart;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();

        // Email invoice & notifikasi status pesanan
        \App\Models\Order::observe(\App\Observers\OrderObserver::class);

        // Studio Settings dari database menimpa default config/flore.php
        \App\Services\StudioSettings::applyToConfig();

        View::composer('layouts.app', fn ($view) => $view->with('cartCount', app(Cart::class)->count()));
    }
}