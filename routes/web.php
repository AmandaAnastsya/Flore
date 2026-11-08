<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\BouquetImageController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CartStemController; 
use App\Http\Controllers\StemController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\OrderController;
Route::get('/', function () {
    return view('welcome');
});

Auth::routes();

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
Route::get('/catalog', [CatalogController::class, 'index'])->name('catalog.index');
Route::get('/catalog/{bouquet:slug}', [CatalogController::class, 'show'])->name('catalog.show');
Route::get('/catalog', [CatalogController::class, 'index'])->name('catalog.index');
Route::get('/catalog/{bouquet:slug}', [CatalogController::class, 'show'])->name('catalog.show');

Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
Route::post('/cart/items/{bouquet:slug}', [CartController::class, 'store'])
    ->middleware('throttle:60,1')
    ->name('cart.store');

Route::patch('/cart/items/{bouquet:slug}', [CartController::class, 'update'])
    ->middleware('throttle:60,1')
    ->name('cart.update');
Route::delete('/cart/items/{bouquet:slug}', [CartController::class, 'destroy'])
    ->withTrashed()   // bouquet yang sudah dihapus admin tetap bisa dibuang dari keranjang
    ->name('cart.destroy');
Route::delete('/cart', [CartController::class, 'clear'])->name('cart.clear');
Route::get('/stems', [StemController::class, 'index'])->name('stems.index');

Route::post('/cart/stems/{flower:slug}', [CartStemController::class, 'store'])
    ->middleware('throttle:60,1')->name('cart.stems.store');
Route::patch('/cart/stems/{flower:slug}', [CartStemController::class, 'update'])
    ->middleware('throttle:60,1')->name('cart.stems.update');
Route::delete('/cart/stems/{flower:slug}', [CartStemController::class, 'destroy'])
    ->withTrashed()->name('cart.stems.destroy');
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

Route::middleware('auth')->prefix('checkout')->name('checkout.')->group(function () {
    Route::get('/', [CheckoutController::class, 'index'])->name('index');
    Route::post('/', [CheckoutController::class, 'store'])->middleware('throttle:30,1')->name('store');
    Route::get('/review', [CheckoutController::class, 'review'])->name('review');
    Route::post('/place', [CheckoutController::class, 'place'])->middleware('throttle:10,1')->name('place');
});

Route::get('/orders/{order:order_number}', [OrderController::class, 'show'])
    ->middleware('auth')->name('orders.show');