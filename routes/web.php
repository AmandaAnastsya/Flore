<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\BouquetImageController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\CatalogController;

Route::get('/', function () {
    return view('welcome');
});

Auth::routes();

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
Route::get('/catalog', [CatalogController::class, 'index'])->name('catalog.index');

Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('login', [AdminAuthController::class, 'showLoginForm'])->name('login');
        Route::post('login', [AdminAuthController::class, 'login'])
            ->middleware('throttle:5,1')
            ->name('login.submit');
    });

    Route::middleware(['auth', 'role:admin'])->group(function () {
        Route::get('dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

        Route::resource('categories', CategoryController::class)->except('show');

        Route::get('bouquet-images', [BouquetImageController::class, 'index'])->name('bouquet-images.index');
        Route::get('bouquet-images/{bouquet}/edit', [BouquetImageController::class, 'edit'])->name('bouquet-images.edit');
        Route::put('bouquet-images/{bouquet}', [BouquetImageController::class, 'update'])->name('bouquet-images.update');
        Route::delete('bouquet-images/{bouquet}', [BouquetImageController::class, 'destroy'])->name('bouquet-images.destroy');
        Route::resource('bouquets', \App\Http\Controllers\Admin\BouquetController::class)->except('show');
        Route::patch('bouquets/{bouquet}/stock', [\App\Http\Controllers\Admin\BouquetController::class, 'updateStock'])
            ->name('bouquets.stock');
    });
});
Route::resource('categories', CategoryController::class)->except('show');

Route::get('bouquet-images', [BouquetImageController::class, 'index'])->name('bouquet-images.index');
Route::get('bouquet-images/{bouquet}/edit', [BouquetImageController::class, 'edit'])->name('bouquet-images.edit');
Route::put('bouquet-images/{bouquet}', [BouquetImageController::class, 'update'])->name('bouquet-images.update');
Route::delete('bouquet-images/{bouquet}', [BouquetImageController::class, 'destroy'])->name('bouquet-images.destroy');