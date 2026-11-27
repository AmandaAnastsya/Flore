<?php

namespace Tests\Feature;

use App\Models\Bouquet;
use App\Models\Category;
use App\Models\Order;
use App\Models\RecommendationRule;
use App\Models\User;
use App\Models\WizardQuery;
use App\Services\RecommendationEngine;
use App\Services\WizardWeights;
use Database\Seeders\DemoSeeder;
use Database\Seeders\RecommendationRuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Smart Wizard & AI Insights (proposal hal. 51). */
class WizardInsightsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(RecommendationRuleSeeder::class);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function bouquet(string $name, array $attrs = []): Bouquet
    {
        $cat = Category::firstOrCreate(['slug' => 'hand-bouquet'], ['name' => 'Hand Bouquet']);

        return Bouquet::unguarded(fn () => Bouquet::create(array_merge([
            'category_id' => $cat->id, 'name' => $name, 'slug' => str($name)->slug(), 'description' => 'Pink Roses & Peonies',
            'price' => 450000, 'stock' => 10, 'low_stock_threshold' => 5, 'is_active' => true,
            'occasion' => 'anniversary', 'color' => 'pink', 'style' => 'soft-romantic', 'size' => 'medium',
        ], $attrs)));
    }

    public function test_page_is_admin_only_and_listed_in_sidebar(): void
    {
        $this->get('/admin/wizard')->assertRedirect();
        $this->actingAs(User::factory()->create())->get('/admin/wizard')->assertForbidden();
        $this->actingAs(User::factory()->create())->put('/admin/wizard/weights', [])->assertForbidden();

        $this->actingAs($this->admin())->get('/admin/wizard')->assertOk()
            ->assertSee('Smart Wizard & AI Insights', false)
            ->assertSee('Section 1: Behavioral Insights', false)
            ->assertSee('Rule Engine Configurator')
            ->assertSee('Belum ada pencarian dengan pilihan warna.');
        $this->get('/admin/dashboard')->assertSee(route('admin.wizard.index'), false);
    }

    public function test_wizard_logs_recommended_bouquets_and_insights_use_real_data(): void
    {
        $rose = $this->bouquet('Sweet Blush');
        $this->bouquet('White Grace', ['color' => 'white', 'occasion' => 'wedding', 'price' => 900000]);
        $customer = User::factory()->create();

        // customer memakai wizard → bouquet teratas tercatat
        $this->actingAs($customer)->getJson('/recommendations?occasion=anniversary&color=pink')->assertOk();
        $q = WizardQuery::latest('id')->first();
        $this->assertSame($rose->id, $q->bouquet_ids[0]);

        // lalu memesan bouquet itu → terhitung "dipesan"
        $order = Order::create([
            'user_id' => $customer->id, 'recipient_name' => 'Rina', 'recipient_phone' => '081234567890', 'delivery_city' => 'Jakarta',
            'delivery_address' => 'Jl. Mawar 1', 'delivery_date' => today()->addDay()->toDateString(), 'delivery_slot' => 'morning',
            'occasion' => 'anniversary', 'subtotal' => 450000, 'total_amount' => 450000,
            'status' => 'payment_confirmed', 'payment_status' => 'confirmed',
        ]);
        $order->items()->create(['bouquet_id' => $rose->id, 'bouquet_name' => $rose->name, 'price' => 450000, 'quantity' => 1, 'subtotal' => 450000]);

        // pencarian yang tidak menemukan bouquet persis → "permintaan belum terpenuhi"
        $this->getJson('/recommendations?occasion=graduation&color=lavender')->assertOk();

        $this->actingAs($this->admin())->get('/admin/wizard')->assertOk()
            ->assertSee('2 Queries')
            ->assertSee('1. Sweet Blush')->assertSee('Pink Roses &amp; Peonies', false)->assertSee('2 rekomendasi · 1 dipesan')   // juga muncul sebagai alternatif di pencarian kedua
            ->assertSee('Rp 450.000')                                // rata-rata nilai pesanan anniversary
            ->assertSee('Momen: Graduation')->assertSee('Warna: Lavender')
            ->assertSee('Warna Pink dipilih di 50% pencarian')
            ->assertSee('1 dari 2 pencarian tidak menemukan bouquet yang cocok persis')
            ->assertSee('100%');                                     // konversi: customer wizard lalu memesan
    }

    public function test_configurator_saves_weights_and_changes_ranking(): void
    {
        $pink = $this->bouquet('Pink Birthday', ['occasion' => 'birthday', 'color' => 'pink']);
        $anniv = $this->bouquet('White Anniversary', ['occasion' => 'anniversary', 'color' => 'white']);
        $engine = app(RecommendationEngine::class);

        // bawaan: momen (30) lebih berat dari warna (20)
        $this->assertSame($anniv->id, $engine->recommend(['occasion' => 'anniversary', 'color' => 'pink'])->items[0]['bouquet']->id);
        $this->actingAs($this->admin())->get('/admin/wizard')->assertSee('bobot bawaan');

        // total harus 100
        $this->put('/admin/wizard/weights', ['weights' => ['occasion' => 30, 'color' => 30, 'style' => 10, 'size' => 10, 'margin' => 0, 'stock' => 10]])
            ->assertSessionHasErrors('weights');
        $this->put('/admin/wizard/weights', ['weights' => ['occasion' => 2, 'color' => 78, 'style' => 10, 'size' => 10, 'margin' => 0, 'stock' => 0]])
            ->assertSessionHasErrors('weights.occasion');

        $this->put('/admin/wizard/weights', ['weights' => ['occasion' => 20, 'color' => 50, 'style' => 10, 'size' => 10, 'margin' => 0, 'stock' => 10]])
            ->assertRedirect('/admin/wizard')->assertSessionHas('success');

        $this->assertSame(['occasion' => 20, 'color' => 50, 'style' => 10, 'size' => 10, 'margin' => 0, 'stock' => 10], app(WizardWeights::class)->current());
        $this->assertSame([20], RecommendationRule::where('criteria', 'occasion')->pluck('weight')->unique()->values()->all());

        // warna kini lebih berat → bouquet pink naik ke atas
        $this->assertSame($pink->id, $engine->recommend(['occasion' => 'anniversary', 'color' => 'pink'])->items[0]['bouquet']->id);
        $this->get('/admin/wizard')->assertSee('bobot kustom');

        // simulasi di halaman memakai bobot aktif & tidak mencatat pencarian
        $this->get('/admin/wizard?sim[occasion]=anniversary&sim[color]=pink')->assertOk()
            ->assertSeeInOrder(['Jalankan Simulasi', 'Pink Birthday', 'White Anniversary']);
        $this->assertSame(0, WizardQuery::count());

        // reset → kembali ke bobot seeder & faktor stok dihapus
        $this->post('/admin/wizard/weights/reset')->assertSessionHas('success');
        $this->assertSame(30, (int) RecommendationRule::where(['criteria' => 'occasion', 'value' => 'anniversary'])->value('weight'));
        $this->assertFalse(RecommendationRule::where(RecommendationEngine::STOCK_RULE)->exists());
        $this->assertSame($anniv->id, $engine->recommend(['occasion' => 'anniversary', 'color' => 'pink'])->items[0]['bouquet']->id);
    }

    public function test_inventory_safety_prefers_healthy_stock_only_when_weighted(): void
    {
        $low = $this->bouquet('Low Stock Rose', ['stock' => 2, 'price' => 300000]);   // lebih murah → menang saat seri
        $ok = $this->bouquet('Healthy Rose', ['stock' => 20, 'price' => 400000]);
        $engine = app(RecommendationEngine::class);

        $this->assertSame($low->id, $engine->recommend(['occasion' => 'anniversary'])->items[0]['bouquet']->id);

        app(WizardWeights::class)->save(['occasion' => 40, 'color' => 20, 'style' => 15, 'size' => 10, 'margin' => 0, 'stock' => 15]);
        $top = $engine->recommend(['occasion' => 'anniversary'])->items[0];
        $this->assertSame($ok->id, $top['bouquet']->id);
        $this->assertContains('Stok aman, siap dirangkai', $top['reasons']);
        $this->assertSame(100, $top['percent']);
    }

    public function test_demo_seeder_fills_insights(): void
    {
        $this->seed();
        $this->seed(DemoSeeder::class);

        $this->assertSame(48, WizardQuery::whereNotNull('bouquet_ids')->count());
        $this->actingAs(User::where('role', 'admin')->first())->get('/admin/wizard?days=90')->assertOk()
            ->assertSee('48 Queries')->assertDontSee('Data muncul setelah customer memakai wizard.');
    }
}
