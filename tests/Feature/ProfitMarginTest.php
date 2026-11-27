<?php

namespace Tests\Feature;

use App\Models\Bouquet;
use App\Models\Category;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderService;
use App\Services\RecommendationEngine;
use App\Services\WizardWeights;
use Database\Seeders\RecommendationRuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Harga modal (HPP), margin & laba kotor — hanya untuk admin. */
class ProfitMarginTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function bouquet(string $name, float $price, ?float $cost, array $attrs = []): Bouquet
    {
        $cat = Category::firstOrCreate(['slug' => 'hand-bouquet'], ['name' => 'Hand Bouquet']);

        return Bouquet::unguarded(fn () => Bouquet::create(array_merge([
            'category_id' => $cat->id, 'name' => $name, 'slug' => str($name)->slug(), 'price' => $price, 'cost_price' => $cost,
            'stock' => 20, 'low_stock_threshold' => 5, 'is_active' => true, 'size' => 'medium',
            'occasion' => 'anniversary', 'color' => 'pink', 'style' => 'soft-romantic',
        ], $attrs)));
    }

    /** Pesanan lunas lewat OrderService asli (stok dikurangi, HPP disalin). */
    private function paidOrder(Bouquet $b, int $qty = 1): Order
    {
        $order = app(OrderService::class)->place(User::factory()->create(), [
            'recipient_name' => 'Rina', 'recipient_phone' => '081234567890', 'delivery_city' => 'Jakarta',
            'delivery_address' => 'Jl. Mawar 1', 'delivery_date' => today()->addDay()->toDateString(), 'delivery_slot' => 'morning',
        ], [$b->id => $qty], []);
        $order->update(['status' => 'payment_confirmed', 'payment_status' => 'confirmed', 'paid_at' => now()]);

        return $order;
    }

    public function test_admin_sets_cost_price_and_sees_margin_in_inventory(): void
    {
        $admin = $this->admin();
        $cat = Category::create(['name' => 'Hand Bouquet', 'slug' => 'hand-bouquet']);
        $payload = ['category_id' => $cat->id, 'name' => 'Sweet Blush', 'price' => 1000000, 'stock' => 10,
            'low_stock_threshold' => 5, 'size' => 'large', 'is_active' => 1];

        $this->actingAs($admin)->post('/admin/bouquets', $payload + ['cost_price' => -5])->assertSessionHasErrors('cost_price');
        $this->post('/admin/bouquets', $payload + ['cost_price' => 550000])->assertRedirect();

        $b = Bouquet::where('name', 'Sweet Blush')->firstOrFail();
        $this->assertEquals(550000, $b->cost_price);
        $this->assertSame(45.0, $b->marginPercent());
        $this->assertSame(['success', 'Sehat'], $b->marginBadge());

        $this->get('/admin/bouquets')->assertSee('HPP Rp 550.000')->assertSee('45%')->assertSee('Sehat')->assertDontSee('belum punya');
        $this->get("/admin/bouquets/{$b->id}/edit")->assertSee('Harga Modal / HPP')->assertSee('value="550000"', false);

        // HPP boleh dikosongkan → margin tidak dihitung, admin diingatkan
        $this->put("/admin/bouquets/{$b->id}", $payload + ['cost_price' => ''])->assertRedirect();
        $this->assertNull($b->fresh()->cost_price);
        $this->get('/admin/bouquets')->assertSee('1 bouquet belum punya')->assertSee('Isi HPP');

        $this->assertSame(['danger', 'Rendah'], $this->bouquet('Murah', 100000, 90000)->marginBadge());
        $this->assertSame(['danger', 'Rugi'], $this->bouquet('Promo', 100000, 120000)->marginBadge());
        $this->assertSame(['warning', 'Tipis'], $this->bouquet('Tipis', 100000, 70000)->marginBadge());
    }

    public function test_checkout_snapshots_cost_and_reports_show_gross_profit(): void
    {
        $rose = $this->bouquet('Sweet Blush', 1000000, 600000);
        $order = $this->paidOrder($rose, 2);
        $this->assertEquals(600000, $order->items->first()->unit_cost);

        // HPP diubah setelahnya → pesanan lama tetap memakai HPP saat checkout
        $rose->update(['cost_price' => 900000]);
        $this->paidOrder($this->bouquet('No Cost', 500000, null));

        $admin = $this->admin();
        $this->actingAs($admin)->get('/admin/reports/sales')->assertOk()
            ->assertSee('Laba Kotor')->assertSee('Rp 800.000')              // 2 × (1.000.000 − 600.000)
            ->assertSee('Rp 800.000 · 40%')->assertSee('dari 80% penjualan ber-HPP');

        $this->get('/admin/reports/products')->assertOk()->assertSeeInOrder(['Sweet Blush', 'Rp 800.000', '40%']);
        $this->get("/admin/orders/{$order->id}")->assertSee('Laba kotor')->assertSee('Rp 800.000 · 40%');

        $csv = $this->get('/admin/reports/sales?export=csv')->streamedContent();
        $this->assertStringContainsString('"Laba Kotor"', $csv);
        $this->assertStringContainsString('1200000', $csv);   // HPP total pesanan pertama
        $this->assertStringContainsString('Laba Kotor', $this->get('/admin/reports/products?export=csv')->streamedContent());
    }

    public function test_cost_price_is_never_exposed_to_customers(): void
    {
        $this->seed(RecommendationRuleSeeder::class);
        $rose = $this->bouquet('Sweet Blush', 1000000, 612345);

        $this->assertArrayNotHasKey('cost_price', $rose->toArray());
        $this->get("/catalog/{$rose->slug}")->assertOk()->assertDontSee('612')->assertDontSee('HPP');
        $this->get('/catalog')->assertDontSee('612.345');
        $json = $this->getJson('/recommendations?occasion=anniversary')->assertOk()->getContent();
        $this->assertStringNotContainsString('612', $json);
        $this->assertStringNotContainsString('margin', strtolower($json));
    }

    public function test_margin_factor_prefers_higher_margin_when_weighted(): void
    {
        $this->seed(RecommendationRuleSeeder::class);
        $thin = $this->bouquet('Thin Margin', 400000, 360000);     // 10% — lebih murah, menang saat seri
        $rich = $this->bouquet('Rich Margin', 450000, 200000);     // 55%
        $engine = app(RecommendationEngine::class);

        $this->assertSame($thin->id, $engine->recommend(['occasion' => 'anniversary'])->items[0]['bouquet']->id);

        app(WizardWeights::class)->save(['occasion' => 50, 'color' => 10, 'style' => 10, 'size' => 10, 'margin' => 20, 'stock' => 0]);
        $top = $engine->recommend(['occasion' => 'anniversary'])->items[0];
        $this->assertSame($rich->id, $top['bouquet']->id);
        $this->assertSame(100, $top['percent']);
        $this->assertSame(20, app(WizardWeights::class)->current()['margin']);

        $this->actingAs($this->admin())->get('/admin/wizard')->assertSee('Optimasi Profit Margin');
    }
}
