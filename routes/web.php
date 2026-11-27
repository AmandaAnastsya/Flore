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
use App\Http\Controllers\RecommendationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
Route::get('/', function (\App\Services\RecommendationEngine $engine) {
    return view('welcome', [
        'featured' => \App\Models\Bouquet::visible()->where('stock', '>', 0)->with('category')
            ->withSum('orderItems as sold_count', 'quantity')
            ->orderByDesc('sold_count')->latest('id')->limit(6)->get(),
        'wizardOptions' => $engine->options(),
        'testimonials' => \App\Models\Testimonial::approved()->with('user')->latest('approved_at')->limit(3)->get(),
    ]);
})->name('landing');

Auth::routes();

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
Route::get('/catalog', [CatalogController::class, 'index'])->name('catalog.index');
Route::get('/catalog/{bouquet:slug}', [CatalogController::class, 'show'])->name('catalog.show');

// Smart Bouquet Recommendation Wizard
Route::get('/wizard', [RecommendationController::class, 'index'])->name('wizard.index');
Route::get('/recommendations', [RecommendationController::class, 'recommend'])
    ->middleware('throttle:60,1')
    ->name('wizard.recommend');

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

        Route::get('orders', [AdminOrderController::class, 'index'])->name('orders.index');
        Route::get('orders/{order}', [AdminOrderController::class, 'show'])->name('orders.show');
        Route::post('orders/{order}/confirm', [AdminOrderController::class, 'confirm'])->name('orders.confirm');
        Route::post('orders/{order}/reject', [AdminOrderController::class, 'reject'])->name('orders.reject');
        Route::post('orders/{order}/advance', [AdminOrderController::class, 'advance'])->name('orders.advance');
        Route::post('orders/{order}/cancel', [AdminOrderController::class, 'cancel'])->name('orders.cancel');
        Route::post('orders/{order}/florist', [AdminOrderController::class, 'assignFlorist'])->name('orders.florist');
        Route::post('orders/{order}/stage', [AdminOrderController::class, 'advanceStage'])->name('orders.stage');
        Route::get('orders/{order}/qc-photo', [AdminOrderController::class, 'qcPhoto'])->name('orders.qc-photo');
        Route::get('orders/{order}/print', [AdminOrderController::class, 'printSlip'])->name('orders.print');
        Route::resource('florists', \App\Http\Controllers\Admin\FloristController::class)->only(['index', 'store', 'edit', 'update']);

        // Logistics & Courier Dispatch (proposal hal. 50)
        Route::get('logistics', [\App\Http\Controllers\Admin\LogisticsController::class, 'index'])->name('logistics.index');
        Route::post('logistics/{order}/assign', [\App\Http\Controllers\Admin\LogisticsController::class, 'assign'])->name('logistics.assign');
        Route::post('logistics/{order}/dispatch', [\App\Http\Controllers\Admin\LogisticsController::class, 'depart'])->name('logistics.dispatch');
        Route::post('logistics/{order}/reroute', [\App\Http\Controllers\Admin\LogisticsController::class, 'reroute'])->name('logistics.reroute');
        Route::post('logistics/{order}/issue', [\App\Http\Controllers\Admin\LogisticsController::class, 'issue'])->name('logistics.issue');
        Route::post('logistics/{order}/issue/resolve', [\App\Http\Controllers\Admin\LogisticsController::class, 'resolveIssue'])->name('logistics.issue.resolve');
        Route::post('logistics/{order}/deliver', [\App\Http\Controllers\Admin\LogisticsController::class, 'deliver'])->name('logistics.deliver');
        Route::get('logistics/{order}/photo', [\App\Http\Controllers\Admin\LogisticsController::class, 'photo'])->name('logistics.photo');
        Route::resource('couriers', \App\Http\Controllers\Admin\CourierController::class)->only(['index', 'store', 'edit', 'update']);

        // Fresh Floral & Supply Inventory Hub (mockup hal. 49)
        Route::controller(\App\Http\Controllers\Admin\InventoryController::class)->prefix('inventory')->name('inventory.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('flowers/create', 'createFlower')->name('flowers.create');
            Route::post('flowers', 'storeFlower')->name('flowers.store');
            Route::get('flowers/{flower}/edit', 'editFlower')->name('flowers.edit');
            Route::put('flowers/{flower}', 'updateFlower')->name('flowers.update');
            Route::get('supplies/create', 'createSupply')->name('supplies.create');
            Route::post('supplies', 'storeSupply')->name('supplies.store');
            Route::get('supplies/{supply}/edit', 'editSupply')->name('supplies.edit');
            Route::put('supplies/{supply}', 'updateSupply')->name('supplies.update');
            Route::post('flowers/{flower}/restock', 'restockFlower')->name('flowers.restock');
            Route::post('flowers/{flower}/adjust', 'adjustFlower')->name('flowers.adjust');
            Route::post('supplies/{supply}/restock', 'restockSupply')->name('supplies.restock');
            Route::post('supplies/{supply}/adjust', 'adjustSupply')->name('supplies.adjust');
        });

        // Ganti password admin
        Route::get('account/password', [\App\Http\Controllers\PasswordController::class, 'edit'])->name('password.edit');
        Route::put('account/password', [\App\Http\Controllers\PasswordController::class, 'update'])
            ->middleware('throttle:6,1')->name('password.update');

        // Studio Settings
        Route::get('settings', [\App\Http\Controllers\Admin\SettingsController::class, 'edit'])->name('settings.edit');
        Route::put('settings', [\App\Http\Controllers\Admin\SettingsController::class, 'update'])->name('settings.update');

        // Smart Wizard & AI Insights (proposal hal. 51)
        Route::get('wizard', [\App\Http\Controllers\Admin\WizardInsightController::class, 'index'])->name('wizard.index');
        Route::put('wizard/weights', [\App\Http\Controllers\Admin\WizardInsightController::class, 'updateWeights'])->name('wizard.weights');
        Route::post('wizard/weights/reset', [\App\Http\Controllers\Admin\WizardInsightController::class, 'resetWeights'])->name('wizard.weights.reset');

        // Smart Reminder: log & pemicu manual
        Route::get('reminders', [\App\Http\Controllers\Admin\ReminderController::class, 'index'])->name('reminders.index');
        Route::post('reminders/run', [\App\Http\Controllers\Admin\ReminderController::class, 'run'])
            ->middleware('throttle:5,1')->name('reminders.run');

        // Dashboard real-time (polling JSON)
        Route::get('dashboard/metrics', [AdminDashboardController::class, 'metrics'])->name('dashboard.metrics');

        // Modul laporan
        Route::get('reports/sales', [\App\Http\Controllers\Admin\ReportController::class, 'sales'])->name('reports.sales');
        Route::get('reports/products', [\App\Http\Controllers\Admin\ReportController::class, 'products'])->name('reports.products');
        Route::get('reports/expenses', [\App\Http\Controllers\Admin\ReportController::class, 'expenses'])->name('reports.expenses');
        Route::post('reports/expenses', [\App\Http\Controllers\Admin\ReportController::class, 'storeExpense'])->name('reports.expenses.store');
        Route::delete('reports/expenses/{expense}', [\App\Http\Controllers\Admin\ReportController::class, 'destroyExpense'])->name('reports.expenses.destroy');
        Route::get('reports/inventory', [\App\Http\Controllers\Admin\ReportController::class, 'inventory'])->name('reports.inventory');
        Route::get('reports/customers', [\App\Http\Controllers\Admin\ReportController::class, 'customers'])->name('reports.customers');
        Route::get('customers/{user}', [\App\Http\Controllers\Admin\CustomerController::class, 'show'])->name('customers.show');

        // Approve testimonial
        Route::get('testimonials', [\App\Http\Controllers\Admin\TestimonialController::class, 'index'])->name('testimonials.index');
        Route::post('testimonials/{testimonial}/approve', [\App\Http\Controllers\Admin\TestimonialController::class, 'approve'])->name('testimonials.approve');
        Route::post('testimonials/{testimonial}/reject', [\App\Http\Controllers\Admin\TestimonialController::class, 'reject'])->name('testimonials.reject');
    });
});
// Catatan keamanan: semua route pengelolaan (kategori, gambar, dll.) HANYA boleh ada di grup admin di atas.
// Salinan tanpa proteksi di sini pernah membuat /categories & /bouquet-images bisa diubah tanpa login.

Route::middleware('auth')->prefix('checkout')->name('checkout.')->group(function () {
    Route::get('/', [CheckoutController::class, 'index'])->name('index');
    Route::post('/', [CheckoutController::class, 'store'])->middleware('throttle:30,1')->name('store');
    Route::get('/review', [CheckoutController::class, 'review'])->name('review');
    Route::post('/place', [CheckoutController::class, 'place'])->middleware('throttle:10,1')->name('place');
});

Route::middleware('auth')->group(function () {
    // Profil & momen spesial (Smart Reminder)
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    // Ganti password (customer)
    Route::get('/account/password', [\App\Http\Controllers\PasswordController::class, 'edit'])->name('account.password.edit');
    Route::put('/account/password', [\App\Http\Controllers\PasswordController::class, 'update'])
        ->middleware('throttle:6,1')->name('account.password.update');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/moments', [ProfileController::class, 'storeMoment'])
        ->middleware('throttle:30,1')->name('profile.moments.store');
    Route::delete('/profile/moments/{moment}', [ProfileController::class, 'destroyMoment'])->name('profile.moments.destroy');

    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order:order_number}', [OrderController::class, 'show'])->name('orders.show');
    Route::post('/orders/{order:order_number}/payment', [OrderController::class, 'uploadProof'])
        ->middleware('throttle:10,1')->name('orders.payment');
    Route::post('/orders/{order:order_number}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');
    Route::get('/orders/{order:order_number}/proof', [OrderController::class, 'proof'])->name('orders.proof');
    Route::get('/orders/{order:order_number}/qc-photo', [OrderController::class, 'qcPhoto'])->name('orders.qc-photo');
    Route::get('/orders/{order:order_number}/delivery-photo', [OrderController::class, 'deliveryPhoto'])->name('orders.delivery-photo');
    Route::post('/orders/{order:order_number}/testimonial', [\App\Http\Controllers\TestimonialController::class, 'store'])
        ->middleware('throttle:10,1')->name('orders.testimonial');
});