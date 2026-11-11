<?php

namespace Tests\Feature;

use App\Models\Bouquet;
use App\Models\Category;
use App\Models\Flower;
use App\Models\Order;
use App\Models\Testimonial;
use App\Models\User;
use App\Models\WizardQuery;
use Database\Seeders\RecommendationRuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AdminReportsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $customer;
    private Bouquet $rose;
    private Bouquet $lily;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-11 10:00'));

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->customer = User::factory()->create(['role' => 'customer', 'name' => 'Clarissa', 'phone' => '081234567890']);

        $cat = Category::create(['name' => 'Hand Bouquet', 'slug' => 'hand-bouquet', 'is_active' => true]);
        $this->rose = Bouquet::create(['category_id' => $cat->id, 'name' => 'Rose Love', 'slug' => 'rose-love', 'price' => 200000, 'stock' => 10, 'low_stock_threshold' => 2]);
        $this->lily = Bouquet::create(['category_id' => $cat->id, 'name' => 'Lily Pure', 'slug' => 'lily-pure', 'price' => 100000, 'stock' => 1, 'low_stock_threshold' => 2]);
    }

    /** Pesanan dengan item; $paidAt null = belum lunas. */
    private function order(array $items, ?string $paidAt, string $status = 'payment_confirmed', ?User $user = null, array $attrs = []): Order
    {
        $total = collect($items)->sum(fn ($i) => $i[0]->price * $i[1]);

        $order = Order::create(array_merge([
            'user_id' => ($user ?? $this->customer)->id, 'recipient_name' => 'Budi', 'recipient_phone' => '081234567890',
            'delivery_city' => 'Bandung', 'delivery_address' => 'Jl. Merdeka No. 10', 'delivery_date' => '2026-11-20',
            'delivery_slot' => 'morning', 'subtotal' => $total, 'total_amount' => $total,
            'status' => $status, 'payment_status' => $paidAt ? 'confirmed' : 'unpaid', 'paid_at' => $paidAt,
        ], $attrs));

        foreach ($items as [$b, $qty]) {
            $order->items()->create(['bouquet_id' => $b->id, 'bouquet_name' => $b->name, 'price' => $b->price, 'quantity' => $qty, 'subtotal' => $b->price * $qty]);
        }

        return $order;
    }

    // ---------------------------------------------------------------- dashboard

    public function test_wizard_usage_is_logged(): void
    {
        $this->seed(RecommendationRuleSeeder::class);

        $this->actingAs($this->customer)->getJson('/recommendations?occasion=birthday&color=pink&budget=200-350&style=ngawur')->assertOk();

        $q = WizardQuery::sole();
        $this->assertSame($this->customer->id, $q->user_id);
        $this->assertSame(['birthday', 'pink', null, '200-350'], [$q->occasion, $q->color, $q->style, $q->budget]);   // pilihan tidak valid tidak dicatat
    }

    public function test_dashboard_metrics_json(): void
    {
        $this->order([[$this->rose, 2]], '2026-11-11 09:00', 'preparing');              // hari ini 400rb
        $this->order([[$this->rose, 1]], '2026-11-10 09:00', 'out_for_delivery');       // kemarin 200rb
        $this->order([[$this->lily, 1]], null, 'pending_payment');                      // belum lunas
        $this->order([[$this->rose, 5]], '2026-11-11 08:00', 'cancelled');              // batal: tidak dihitung
        Flower::create(['name' => 'Peony', 'slug' => 'peony', 'species' => 'peony', 'price_per_stem' => 20000, 'stock' => 5, 'reorder_level' => 30]);
        WizardQuery::create(['user_id' => $this->customer->id, 'occasion' => 'birthday', 'mode' => 'exact']);

        $this->getJson('/admin/dashboard/metrics')->assertUnauthorized();
        $this->actingAs($this->customer)->getJson('/admin/dashboard/metrics')->assertForbidden();

        $this->actingAs($this->admin)->getJson('/admin/dashboard/metrics')->assertOk()
            ->assertJson([
                'revenue_total' => 600000,
                'revenue_today' => 400000,
                'revenue_change' => 100,
                'active_orders' => 2,
                'preparing' => 1,
                'out_for_delivery' => 1,
                'wizard_today' => 1,
                'wizard_30d' => 1,
                'stock_alerts' => 2,          // Lily (bouquet) + Peony (per tangkai)
            ])
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_wizard_conversion_counts_orders_after_first_query(): void
    {
        $other = User::factory()->create(['role' => 'customer']);

        $this->travelTo(Carbon::parse('2026-11-05 10:00'));
        WizardQuery::create(['user_id' => $this->customer->id, 'mode' => 'exact']);
        WizardQuery::create(['user_id' => $other->id, 'mode' => 'exact']);

        $this->travelTo(Carbon::parse('2026-11-06 10:00'));
        $this->order([[$this->rose, 1]], null, 'pending_payment');   // hanya Clarissa yang lanjut memesan

        $this->travelTo(Carbon::parse('2026-11-11 10:00'));

        $this->actingAs($this->admin)->getJson('/admin/dashboard/metrics')->assertJsonPath('wizard_conversion', 50);
    }

    public function test_dashboard_page_shows_kpis_chart_and_preferences(): void
    {
        $this->order([[$this->rose, 2]], '2026-11-11 09:00');
        WizardQuery::create(['occasion' => 'anniversary', 'color' => 'pink', 'mode' => 'exact']);

        $this->actingAs($this->admin)->get('/admin/dashboard')->assertOk()
            ->assertSee("Today's Revenue", false)->assertSee('Active Orders')->assertSee('Smart Wizard Queries')->assertSee('Flower Stock Alert')
            ->assertSee('data-dashboard-metrics="' . route('admin.dashboard.metrics') . '"', false)
            ->assertSee('Pendapatan 14 Hari Terakhir')->assertSee('Rp 400.000')
            ->assertSee('Smart Wizard Preferences')->assertSee('Anniversary')
            ->assertSee('Peringatan stok')->assertSee('Lily Pure');
    }

    // ---------------------------------------------------------------- sales report

    public function test_sales_report_filters_by_paid_date_and_excludes_cancelled(): void
    {
        $in = $this->order([[$this->rose, 2]], '2026-11-05 10:00', 'delivered');
        $out = $this->order([[$this->rose, 1]], '2026-09-01 10:00', 'delivered');
        $cancel = $this->order([[$this->lily, 1]], '2026-11-06 10:00', 'cancelled', null, ['cancelled_at' => '2026-11-06 12:00']);

        $this->actingAs($this->customer)->get('/admin/reports/sales')->assertForbidden();

        $this->actingAs($this->admin)->get('/admin/reports/sales?from=2026-11-01&to=2026-11-11')->assertOk()
            ->assertSee('Financial & Sales Report')
            ->assertSee($in->order_number)->assertDontSee($out->order_number)->assertDontSee($cancel->order_number)
            ->assertSee('Rp 400.000')
            ->assertViewHas('summary', fn ($s) => $s['revenue'] == 400000 && $s['orders'] === 1 && $s['items'] === 2 && $s['cancelled'] === 1);

        // preset & tanggal ngawur tidak error
        $this->get('/admin/reports/sales?preset=year')->assertOk()->assertSee($out->order_number);
        $this->get('/admin/reports/sales?from=ngawur&to=2026-13-45')->assertOk();
        $this->get('/admin/reports/sales?from=2026-11-11&to=2026-11-01')->assertOk()->assertSee($in->order_number);   // tertukar → dibalik
    }

    public function test_sales_report_csv_export(): void
    {
        $order = $this->order([[$this->rose, 2]], '2026-11-05 10:00', 'delivered');

        $res = $this->actingAs($this->admin)->get('/admin/reports/sales?from=2026-11-01&to=2026-11-11&export=csv');

        $res->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('laporan-penjualan_2026-11-01_2026-11-11.csv', $res->headers->get('Content-Disposition'));
        $csv = $res->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('"No. Pesanan";"Tanggal Lunas";Customer', $csv);
        $this->assertStringContainsString($order->order_number, $csv);
        $this->assertStringContainsString('400000', $csv);
    }

    // ---------------------------------------------------------------- products & customers

    public function test_best_selling_products_ranked_with_share(): void
    {
        $this->order([[$this->rose, 3], [$this->lily, 1]], '2026-11-05 10:00', 'delivered');   // rose 600rb, lily 100rb
        $this->order([[$this->lily, 4]], null, 'pending_payment');                              // belum lunas: tidak dihitung

        $this->actingAs($this->admin)->get('/admin/reports/products?preset=30d')->assertOk()
            ->assertSeeInOrder(['Rose Love', 'Lily Pure'])
            ->assertViewHas('bouquets', fn ($rows) => $rows[0]['qty'] === 3 && $rows[0]['share'] == 85.7 && $rows[1]['qty'] === 1);

        $csv = $this->get('/admin/reports/products?preset=30d&export=csv')->streamedContent();
        $this->assertStringContainsString('"Rose Love";3;1;600000', str_replace('Bouquet;', '', $csv));
    }

    public function test_customer_report_totals_search_and_csv(): void
    {
        $this->order([[$this->rose, 2]], '2026-11-05 10:00', 'delivered');
        $this->order([[$this->lily, 1]], null, 'pending_payment');
        $budi = User::factory()->create(['role' => 'customer', 'name' => 'Budi Santoso', 'email' => 'budi@example.com']);

        $this->actingAs($this->admin)->get('/admin/reports/customers')->assertOk()
            ->assertSeeInOrder(['Clarissa', 'Budi Santoso'])        // urut total belanja
            ->assertSee('Rp 400.000')
            ->assertViewHas('stats', fn ($s) => $s['total'] === 2 && $s['buyers'] === 1 && $s['repeat'] === 1);

        $this->get('/admin/reports/customers?q=budi@')->assertSee('Budi Santoso')->assertDontSee('Clarissa');

        $csv = $this->get('/admin/reports/customers?export=csv')->streamedContent();
        $this->assertStringContainsString('Clarissa', $csv);
        $this->assertStringNotContainsString($this->admin->email, $csv);   // admin bukan customer
    }

    public function test_csv_neutralises_formula_injection(): void
    {
        User::factory()->create(['role' => 'customer', 'name' => '=HYPERLINK("http://evil")']);

        $csv = $this->actingAs($this->admin)->get('/admin/reports/customers?export=csv')->streamedContent();

        $this->assertStringContainsString("'=HYPERLINK", $csv);
    }

    // ---------------------------------------------------------------- testimonial

    public function test_customer_submits_testimonial_only_for_own_delivered_order_once(): void
    {
        $pending = $this->order([[$this->rose, 1]], null, 'pending_payment');
        $delivered = $this->order([[$this->rose, 1]], '2026-11-05 10:00', 'delivered');
        $url = fn (Order $o) => route('orders.testimonial', $o->order_number);

        $this->actingAs($this->customer)->get(route('orders.show', $delivered->order_number))->assertSee('Kirim Testimonial');
        $this->get(route('orders.show', $pending->order_number))->assertDontSee('Kirim Testimonial');

        $this->post($url($pending), ['rating' => 5, 'content' => 'Bagus sekali bunganya'])->assertSessionHas('error');
        $this->post($url($delivered), ['rating' => 9, 'content' => 'pendek'])->assertSessionHasErrorsIn('testimonial', ['rating', 'content']);

        $this->actingAs(User::factory()->create())->post($url($delivered), ['rating' => 5, 'content' => 'Bukan pesanan saya'])->assertNotFound();

        $this->actingAs($this->customer)->post($url($delivered), ['rating' => 5, 'content' => 'Bunganya segar dan cantik sekali!'])
            ->assertSessionHas('success');
        $this->post($url($delivered), ['rating' => 4, 'content' => 'Kirim lagi untuk kedua kali'])->assertSessionHas('error');

        $this->assertSame(1, Testimonial::count());
        $this->assertSame('pending', Testimonial::first()->status());
        $this->get(route('orders.show', $delivered->order_number))->assertSee('Menunggu persetujuan admin');
    }

    public function test_admin_approves_and_rejects_testimonial(): void
    {
        $order = $this->order([[$this->rose, 1]], '2026-11-05 10:00', 'delivered');
        $t = Testimonial::create(['user_id' => $this->customer->id, 'order_id' => $order->id, 'rating' => 5, 'content' => 'Rangkaiannya punya jiwa!']);

        $this->get('/')->assertDontSee('Rangkaiannya punya jiwa!');
        $this->actingAs($this->customer)->post(route('admin.testimonials.approve', $t))->assertForbidden();

        $this->actingAs($this->admin)->get('/admin/testimonials')->assertOk()->assertSee('Rangkaiannya punya jiwa!')->assertSee('Setujui');
        $this->get('/admin/dashboard')->assertSee('<span class="count">1</span>', false);   // badge sidebar

        $this->post(route('admin.testimonials.approve', $t))->assertSessionHas('success');
        $this->assertSame('approved', $t->fresh()->status());
        $this->get('/')->assertSee('Kepercayaan Pelanggan')->assertSee('Rangkaiannya punya jiwa!')->assertSee('Clarissa');

        $this->post(route('admin.testimonials.reject', $t));
        $this->assertSame('rejected', $t->fresh()->status());
        $this->get('/')->assertDontSee('Rangkaiannya punya jiwa!');
        $this->get('/admin/testimonials?status=rejected')->assertSee('Rangkaiannya punya jiwa!');
    }
}
