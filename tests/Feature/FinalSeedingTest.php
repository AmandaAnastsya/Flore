<?php

namespace Tests\Feature;

use App\Models\Bouquet;
use App\Models\Category;
use App\Models\Flower;
use App\Models\Order;
use App\Models\RecommendationRule;
use App\Models\Testimonial;
use App\Models\User;
use App\Models\WizardQuery;
use App\Services\SalesAnalytics;
use Database\Seeders\AdminSeeder;
use Database\Seeders\BouquetSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FinalSeedingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    // ---------------------------------------------------------------- data produk final

    public function test_final_catalog_is_complete_and_in_stock(): void
    {
        config(['flore.admin.password' => 'rahasia-admin-123']);
        $this->seed();

        $this->assertSame(4, Category::count());
        $this->assertSame(12, Bouquet::count());
        $this->assertSame(16, Flower::count());
        $this->assertSame(19, RecommendationRule::count());

        // tidak ada data uji tersisa & semua produk siap jual
        $this->assertSame(0, Bouquet::where('stock', '<=', 0)->count());
        $this->assertSame(0, Bouquet::whereColumn('stock', '<=', 'low_stock_threshold')->count(), 'Ada bouquet yang langsung "stok menipis"');
        $this->assertSame(0, Flower::whereColumn('stock', '<=', 'reorder_level')->count());
        $this->assertSame(0, Bouquet::whereNull('occasion')->orWhereNull('color')->orWhereNull('style')->orWhereNull('size')->count(), 'Atribut wizard belum lengkap');
        $this->assertSame(0, User::where('role', 'customer')->count());
        $this->assertSame(0, Order::count());

        // setiap occasion di wizard punya minimal satu bouquet
        foreach (Bouquet::OCCASIONS as $occasion) {
            $this->assertTrue(Bouquet::where('occasion', $occasion)->exists(), "Tidak ada bouquet untuk occasion {$occasion}");
        }
    }

    public function test_reseeding_syncs_details_but_never_overwrites_running_stock(): void
    {
        $this->seed();
        $rose = Bouquet::where('slug', 'the-sweet-blush-romance')->first();
        $rose->update(['stock' => 3, 'price' => 1]);   // stok berjalan + harga yang terlanjur salah

        $this->seed();

        $rose->refresh();
        $this->assertSame(3, $rose->stock);              // stok tidak ditimpa
        $this->assertEquals(1150000, $rose->price);      // harga kembali sesuai katalog
        $this->assertSame(12, Bouquet::count());
    }

    // ---------------------------------------------------------------- admin

    public function test_admin_credentials_come_from_env_config(): void
    {
        config(['flore.admin' => ['name' => 'Admin Toko', 'email' => 'owner@flore.id', 'phone' => '081111111111', 'password' => 'rahasia-admin-123']]);

        $this->seed(AdminSeeder::class);

        $admin = User::where('email', 'owner@flore.id')->firstOrFail();
        $this->assertSame('admin', $admin->role);
        $this->assertTrue(Hash::check('rahasia-admin-123', $admin->password));
        $this->assertFalse(Hash::check('password', $admin->password));
    }

    public function test_without_password_new_admin_gets_random_one_and_existing_admin_keeps_theirs(): void
    {
        config(['flore.admin.password' => null]);

        $this->seed(AdminSeeder::class);
        $admin = User::where('role', 'admin')->firstOrFail();
        $this->assertFalse(Hash::check('password', $admin->password));   // bukan password default lama
        $hash = $admin->password;

        $this->seed(AdminSeeder::class);
        $this->assertSame($hash, $admin->fresh()->password);
    }

    public function test_production_refuses_missing_or_weak_admin_password(): void
    {
        $this->app['env'] = 'production';

        foreach ([null, 'pendek'] as $password) {
            config(['flore.admin.password' => $password]);
            try {
                $this->app->make(AdminSeeder::class)->run();   // db:seed di production meminta konfirmasi interaktif
                $this->fail('Seeding admin di production seharusnya ditolak');
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString('ADMIN_PASSWORD', $e->getMessage());
            }
        }
        $this->assertSame(0, User::count());
    }

    // ---------------------------------------------------------------- demo

    public function test_demo_seeder_fills_dashboard_reports_and_reminders(): void
    {
        $this->seed();
        $stock = Bouquet::pluck('stock', 'id');

        $this->seed(DemoSeeder::class);

        $this->assertSame(6, User::where('email', 'like', '%@' . DemoSeeder::DOMAIN)->count());
        $this->assertSame(15, Order::count());
        $this->assertSame(['cancelled', 'delivered', 'out_for_delivery', 'payment_confirmed', 'pending_payment', 'preparing'],
            Order::distinct()->orderBy('status')->pluck('status')->all());
        $this->assertSame(1, Order::awaitingVerification()->count());
        Storage::disk('local')->assertExists(Order::awaitingVerification()->value('payment_proof'));
        $this->assertSame(3, Testimonial::where('is_approved', true)->count());
        $this->assertSame(48, WizardQuery::count());

        $m = app(SalesAnalytics::class)->dashboardMetrics();
        $this->assertGreaterThan(0, $m['revenue_total']);
        $this->assertSame(3, $m['active_orders']);
        $this->assertSame(48, $m['wizard_30d']);

        $this->assertEquals($stock, Bouquet::pluck('stock', 'id'));   // stok katalog tidak disentuh

        $clarissa = User::where('email', 'clarissa@' . DemoSeeder::DOMAIN)->first();
        $this->assertTrue(Hash::check(DemoSeeder::PASSWORD, $clarissa->password));
        $this->get('/')->assertSee('Kepercayaan Pelanggan');
    }

    public function test_demo_seeder_is_rerunnable_and_blocked_in_production(): void
    {
        $this->seed();
        $this->seed(DemoSeeder::class);
        $this->seed(DemoSeeder::class);

        $this->assertSame(6, User::where('role', 'customer')->count());
        $this->assertSame(15, Order::count());
        $this->assertSame(48, WizardQuery::count());
        $this->assertCount(1, Storage::disk('local')->files('payment-proofs'));

        $this->app['env'] = 'production';
        $this->expectExceptionMessage('tidak boleh dijalankan di production');
        $this->app->make(DemoSeeder::class)->run();
    }
}
