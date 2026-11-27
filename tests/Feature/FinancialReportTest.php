<?php

namespace Tests\Feature;

use App\Models\Bouquet;
use App\Models\Category;
use App\Models\Expense;
use App\Models\User;
use App\Services\OrderService;
use App\Services\SalesAnalytics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Financial & Sales Report (mockup hal. 52): laba bersih, biaya operasional, tren bulanan, ekspor Excel/PDF. */
class FinancialReportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-20 10:00'));
        $this->admin = User::factory()->create(['role' => 'admin', 'name' => 'Admin Floré']);
    }

    private function sell(float $price, ?float $cost, int $qty = 1, ?string $at = null): void
    {
        $cat = Category::firstOrCreate(['slug' => 'hand-bouquet'], ['name' => 'Hand Bouquet']);
        $b = Bouquet::unguarded(fn () => Bouquet::create([
            'category_id' => $cat->id, 'name' => 'Bouquet ' . uniqid(), 'slug' => uniqid('b'), 'price' => $price, 'cost_price' => $cost,
            'stock' => 50, 'is_active' => true,
        ]));
        $order = app(OrderService::class)->place(User::factory()->create(), [
            'recipient_name' => 'Rina', 'recipient_phone' => '081234567890', 'delivery_city' => 'Jakarta', 'delivery_address' => 'Jl. Mawar 1',
            'delivery_date' => today()->addDay()->toDateString(), 'delivery_slot' => 'morning',
        ], [$b->id => $qty], []);
        $order->update(['status' => 'delivered', 'payment_status' => 'confirmed', 'paid_at' => $at ? Carbon::parse($at) : now()]);
    }

    public function test_expenses_page_records_and_validates(): void
    {
        $this->get('/admin/reports/expenses')->assertRedirect();
        $this->actingAs(User::factory()->create())->get('/admin/reports/expenses')->assertForbidden();

        $this->actingAs($this->admin)->post('/admin/reports/expenses', ['spent_on' => '2026-12-01', 'category' => 'x', 'description' => '', 'amount' => 0])
            ->assertSessionHasErrors(['spent_on', 'category', 'description', 'amount']);

        $this->post('/admin/reports/expenses', ['spent_on' => '2026-11-15', 'category' => 'gaji', 'description' => 'Gaji florist November', 'amount' => 3000000])
            ->assertSessionHas('success');
        $this->post('/admin/reports/expenses', ['spent_on' => '2026-11-10', 'category' => 'sewa', 'description' => 'Sewa studio', 'amount' => 1500000]);

        $this->get('/admin/reports/expenses?preset=month')->assertOk()
            ->assertSee('Rp 4.500.000')->assertSee('Gaji florist November')->assertSee('Gaji &amp; upah', false);

        $e = Expense::where('category', 'sewa')->first();
        $this->delete("/admin/reports/expenses/{$e->id}")->assertSessionHas('success');
        $this->assertSame(1, Expense::count());
    }

    public function test_net_profit_and_monthly_trend(): void
    {
        $this->sell(1000000, 600000, 2);                         // November: laba kotor 800.000
        $this->sell(500000, 300000, 1, '2026-10-12 10:00');      // Oktober: laba kotor 200.000
        Expense::create(['spent_on' => '2026-11-05', 'category' => 'gaji', 'description' => 'Gaji', 'amount' => 300000]);
        Expense::create(['spent_on' => '2026-09-05', 'category' => 'sewa', 'description' => 'Sewa', 'amount' => 100000]);   // September: tanpa penjualan

        $s = app(SalesAnalytics::class)->salesSummary(Carbon::parse('2026-11-01'), today());
        $this->assertEquals(800000, $s['gross_profit']);
        $this->assertEquals(300000, $s['expenses']);
        $this->assertEquals(500000, $s['net_profit']);
        $this->assertSame(25.0, $s['net_margin']);   // 500.000 / 2.000.000

        $trend = app(SalesAnalytics::class)->monthlyTrend(6)->keyBy(fn ($m) => $m['month']->format('Y-m'));
        $this->assertCount(6, $trend);
        $this->assertEquals(500000, $trend['2026-11']['net_profit']);
        $this->assertEquals(200000, $trend['2026-10']['net_profit']);
        $this->assertEquals(-100000, $trend['2026-09']['net_profit']);   // biaya tanpa penjualan = rugi
        $this->assertNull($trend['2026-08']['net_profit']);

        $this->actingAs($this->admin)->get('/admin/reports/sales?preset=month')->assertOk()
            ->assertSee('Laba Bersih (Net Profit)')->assertSee('Rp 500.000')->assertSee('Margin bersih 25%')
            ->assertSee('Tren Penjualan & Laba Bersih (Bulanan)', false)->assertSee('Produk Terlaris & Margin', false)
            ->assertSee('Metode Pembayaran')->assertSee('Transfer BCA');
    }

    public function test_excel_and_print_exports_for_every_report(): void
    {
        $this->sell(1000000, 600000);
        $this->actingAs($this->admin);

        foreach (['sales', 'products', 'inventory', 'customers', 'expenses'] as $report) {
            $xlsx = $this->get("/admin/reports/{$report}?export=xlsx");
            $xlsx->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            $this->assertStringContainsString('.xlsx"', $xlsx->headers->get('Content-Disposition'));
            $bin = $xlsx->getContent();
            $this->assertSame("PK\x03\x04", substr($bin, 0, 4), "{$report}: bukan arsip ZIP/XLSX");
            $this->assertStringContainsString('xl/worksheets/sheet1.xml', $bin);

            $this->get("/admin/reports/{$report}?export=print")->assertOk()->assertSee('Cetak / Simpan PDF');
        }

        // isi xlsx penjualan: header + angka sebagai angka
        $bin = $this->get('/admin/reports/sales?export=xlsx')->getContent();
        $this->assertStringContainsString('Metode Pembayaran', $bin);
        $this->assertStringContainsString('<v>1000000</v>', $bin);
        $this->assertStringContainsString('<v>400000</v>', $bin);   // laba kotor

        $this->get('/admin/reports/sales?export=print')->assertSee('Laporan Penjualan')->assertSee('1.000.000');
        $this->get('/admin/reports/sales')->assertSee('Ekspor Excel')->assertSee('Cetak / PDF');
    }

    public function test_xlsx_is_a_valid_zip_archive(): void
    {
        $bin = \App\Support\SimpleXlsx::build('Uji', ['Nama', 'Jumlah'], [['Mawar & "Lily" <b>', 12500], ['=1+1', '0812']]);

        // akhir arsip: End Of Central Directory dengan 6 file
        $eocd = strrpos($bin, "PK\x05\x06");
        $this->assertNotFalse($eocd);
        $this->assertSame(6, unpack('v', substr($bin, $eocd + 10, 2))[1]);
        $this->assertStringContainsString('Mawar &amp; &quot;Lily&quot; &lt;b&gt;', $bin);
        $this->assertStringContainsString('<v>12500</v>', $bin);
        $this->assertStringContainsString('<t xml:space="preserve">0812</t>', $bin);   // nomor HP tetap teks
        $this->assertStringContainsString('<t xml:space="preserve">=1+1</t>', $bin);   // bukan formula
    }
}
