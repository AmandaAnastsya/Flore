<?php

namespace Tests\Feature;

use App\Models\Flower;
use App\Models\StockMovement;
use App\Models\Supply;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Fresh Floral & Supply Inventory Hub (mockup hal. 49) + Inventory Report. */
class InventoryHubTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function flowerPayload(array $o = []): array
    {
        return array_merge([
            'name' => 'Rose Pink Ecuador', 'species' => 'rose', 'color' => 'pink', 'origin' => 'Import (Ecuador)',
            'price_per_stem' => 25000, 'cost_per_stem' => 15000, 'bundle_size' => 10, 'bundle_price' => 230000,
            'reorder_level' => 30, 'shelf_life_days' => 6, 'stock' => 100, 'is_active' => 1,
        ], $o);
    }

    public function test_hub_is_admin_only(): void
    {
        $this->get('/admin/inventory')->assertRedirect();
        $this->actingAs(User::factory()->create())->get('/admin/inventory')->assertForbidden();
        $this->actingAs(User::factory()->create())->post('/admin/inventory/flowers', $this->flowerPayload())->assertForbidden();
        $this->actingAs(User::factory()->create())->get('/admin/reports/inventory')->assertForbidden();
    }

    public function test_admin_adds_flower_which_logs_opening_stock_and_appears_in_shop(): void
    {
        $this->actingAs($this->admin())->post('/admin/inventory/flowers', $this->flowerPayload(['bundle_size' => 10, 'bundle_price' => '']))
            ->assertSessionHasErrors('bundle_price');

        $this->post('/admin/inventory/flowers', $this->flowerPayload())->assertRedirect('/admin/inventory');
        $rose = Flower::where('name', 'Rose Pink Ecuador')->firstOrFail();
        $this->assertSame('rose-pink-ecuador', $rose->slug);
        $this->assertSame(100, $rose->stock);
        $this->assertNotNull($rose->last_restocked_at);
        $this->assertSame(100, StockMovement::where('item_id', $rose->id)->value('change'));

        $this->get('/admin/inventory')->assertOk()->assertSee('Fresh Floral & Supply Inventory Hub', false)
            ->assertSee('Rose Pink Ecuador')->assertSee('100 Batang')->assertSee('Tiba hari ini')->assertSee('100%')->assertSee('Aman');

        // tampil di katalog per tangkai, HPP tidak
        $this->get('/stems')->assertSee('Rose Pink Ecuador')->assertDontSee('15.000');
        $this->assertArrayNotHasKey('cost_per_stem', $rose->toArray());
    }

    public function test_freshness_restock_and_waste(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/admin/inventory/flowers', $this->flowerPayload(['stock' => 40, 'shelf_life_days' => 6]));
        $rose = Flower::firstOrFail();

        $this->travel(4)->days();
        $rose->refresh();
        $this->assertSame(2, $rose->remainingDays());
        $this->assertSame(33, $rose->freshnessPercent());
        $this->assertSame('aging', $rose->freshnessState());
        $this->travel(3)->days();
        $this->assertSame('expired', $rose->fresh()->freshnessState());
        $this->get('/admin/inventory')->assertSee('lewat umur segar')->assertSee('1 varietas lewat umur segar');

        // bunga layu dibuang
        $this->post("/admin/inventory/flowers/{$rose->id}/adjust", ['stock' => 50, 'reason' => 'waste'])->assertSessionHas('error');
        $this->post("/admin/inventory/flowers/{$rose->id}/adjust", ['stock' => 25, 'reason' => 'waste', 'note' => 'Kelopak layu'])->assertSessionHas('success');
        $this->assertSame(25, $rose->fresh()->stock);
        $this->assertSame('critical', $rose->fresh()->inventoryLevel());
        $this->get('/admin/inventory')->assertSee('Kritis (Restock)')->assertSee('1 Item');

        // restock: stok naik, tanggal tiba baru, HPP diperbarui
        $this->post("/admin/inventory/flowers/{$rose->id}/restock", ['quantity' => 0])->assertSessionHasErrors('quantity');
        $this->post("/admin/inventory/flowers/{$rose->id}/restock", ['quantity' => 60, 'unit_cost' => 16000, 'note' => 'Pasar Rawa Belong'])->assertSessionHas('success');
        $rose->refresh();
        $this->assertSame(85, $rose->stock);
        $this->assertEquals(16000, $rose->cost_per_stem);
        $this->assertSame(100, $rose->freshnessPercent());
        $this->assertSame(['restock', 'waste', 'restock'], StockMovement::orderBy('id')->pluck('reason')->all());
        $this->assertSame($admin->id, StockMovement::latest('id')->value('user_id'));
    }

    public function test_supplies_tabs_and_critical_kpi(): void
    {
        $this->actingAs($this->admin());
        $this->post('/admin/inventory/supplies', ['type' => 'wrapping', 'name' => 'Pita Satin', 'variant' => 'Dusty Pink', 'unit' => 'meter',
            'reorder_level' => 50, 'cost_per_unit' => 2000, 'stock' => 480, 'is_active' => 1])->assertRedirect('/admin/inventory?tab=wrapping');
        $this->post('/admin/inventory/supplies', ['type' => 'vase', 'name' => 'Vas Kaca Silinder', 'unit' => 'pcs',
            'reorder_level' => 5, 'stock' => 3, 'is_active' => 1]);
        $this->post('/admin/inventory/supplies', ['type' => 'gelas', 'name' => 'X', 'unit' => 'pcs', 'reorder_level' => 1, 'stock' => 1])
            ->assertSessionHasErrors('type');

        $this->get('/admin/inventory?tab=wrapping')->assertSee('Pita Satin')->assertSee('480 meter')->assertDontSee('3 pcs')   // baris vas hanya di tab Vas
            ->assertSee('480 Meter');                                   // KPI suplai wrapping
        $this->get('/admin/inventory?tab=vase')->assertSee('Vas Kaca Silinder')->assertSee('Kritis (Restock)')
            ->assertSee('1 Item')->assertSee('Vas Kaca Silinder');      // KPI stok kritis
        $this->get('/admin/dashboard')->assertSee('title="Perlu restock">1<', false);

        $vase = Supply::where('type', 'vase')->first();
        $this->post("/admin/inventory/supplies/{$vase->id}/restock", ['quantity' => 20])->assertSessionHas('success');
        $this->assertSame(23, $vase->fresh()->stock);
        $this->put("/admin/inventory/supplies/{$vase->id}", ['type' => 'vase', 'name' => 'Vas Kaca Tinggi', 'unit' => 'pcs', 'reorder_level' => 5, 'is_active' => 1])
            ->assertRedirect();
        $this->assertSame('Vas Kaca Tinggi', $vase->fresh()->name);
        $this->assertSame(23, $vase->fresh()->stock);   // edit tidak mengubah stok
    }

    public function test_inventory_report_and_stem_margin(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/admin/inventory/flowers', $this->flowerPayload(['stock' => 100, 'cost_per_stem' => 15000]));
        $rose = Flower::firstOrFail();
        $this->post("/admin/inventory/flowers/{$rose->id}/adjust", ['stock' => 90, 'reason' => 'waste']);

        // penjualan per tangkai menyimpan HPP → margin per tangkai di laporan produk
        $order = app(OrderService::class)->place(User::factory()->create(), [
            'recipient_name' => 'Rina', 'recipient_phone' => '081234567890', 'delivery_city' => 'Jakarta',
            'delivery_address' => 'Jl. Mawar 1', 'delivery_date' => today()->addDay()->toDateString(), 'delivery_slot' => 'morning',
        ], [], [$rose->id => 10]);
        $order->update(['status' => 'payment_confirmed', 'payment_status' => 'confirmed', 'paid_at' => now()]);
        $this->assertEquals(15000, $order->items->first()->unit_cost);

        $this->get('/admin/reports/inventory')->assertOk()
            ->assertSee('Inventory Report')
            ->assertSee('Rp 1.200.000')          // 80 tangkai × 15.000
            ->assertSee('100 Unit')              // barang masuk (stok awal)
            ->assertSee('10 Unit')->assertSee('Rp 150.000')   // dibuang
            ->assertSee('+ 10 tangkai bunga')
            ->assertSee('Dibuang (layu/rusak)');

        $csv = $this->get('/admin/reports/inventory?export=csv')->streamedContent();
        $this->assertStringContainsString('Rose Pink Ecuador', $csv);
        $this->assertStringContainsString('"Nilai Persediaan"', $csv);

        // 10 tangkai paket: 230.000 − 150.000 = 80.000 (34,8%)
        $this->get('/admin/reports/products')->assertSeeInOrder(['Bunga Per Tangkai', 'Rose Pink Ecuador', 'Rp 80.000', '34.8%']);
    }
}
