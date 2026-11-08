<?php

namespace Tests\Feature;

use App\Exceptions\OrderException;
use App\Models\Bouquet;
use App\Models\Category;
use App\Models\Flower;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class OrderPlacementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-10 08:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function bouquet(string $name, int $price = 100000, int $stock = 10): Bouquet
    {
        $cat = Category::firstOrCreate(['slug' => 'hand-bouquet'], ['name' => 'Hand Bouquet']);

        return Bouquet::unguarded(fn () => Bouquet::create([
            'category_id' => $cat->id, 'name' => $name, 'slug' => str($name)->slug(),
            'price' => $price, 'stock' => $stock, 'is_same_day' => false, 'is_active' => true,
        ]));
    }

    private function flower(string $name = 'Rose Merah', int $stock = 100): Flower
    {
        return Flower::create([
            'name' => $name, 'slug' => str($name)->slug(), 'species' => 'rose', 'price_per_stem' => 8000,
            'bundle_size' => 10, 'bundle_price' => 70000, 'stock' => $stock, 'is_active' => true,
        ]);
    }

    private function checkoutData(array $override = []): array
    {
        return array_merge([
            'recipient_name' => 'Budi Santoso', 'recipient_phone' => '081234567890',
            'delivery_city' => 'Bandung', 'delivery_address' => 'Jl. Merdeka No. 10, Bandung',
            'delivery_date' => now()->addDays(2)->toDateString(), 'delivery_slot' => 'morning',
            'occasion' => 'birthday', 'card_message' => 'Selamat ulang tahun!',
            'sender_name' => 'Amanda', 'is_anonymous' => false,
        ], $override);
    }

    /** Login, isi keranjang lewat HTTP, lengkapi form checkout. */
    private function readyToPlace(Bouquet $b, int $qty = 2): User
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->post("/cart/items/{$b->slug}", ['quantity' => $qty]);
        $this->post('/checkout', $this->checkoutData());

        return $user;
    }

    // ------------------------------------------------------- lewat HTTP

    public function test_placing_order_creates_order_items_and_decrements_stock(): void
    {
        $b = $this->bouquet('Rose Love', 100000, 10);
        $user = $this->readyToPlace($b, 2);

        $response = $this->post('/checkout/place');

        $order = Order::first();
        $response->assertRedirect(route('orders.show', $order->order_number));

        $this->assertSame($user->id, $order->user_id);
        $this->assertSame('pending_payment', $order->status);
        $this->assertSame('unpaid', $order->payment_status);
        $this->assertSame('Budi Santoso', $order->recipient_name);
        $this->assertSame('morning', $order->delivery_slot);
        $this->assertEquals(200000, $order->subtotal);
        $this->assertEquals(200000, $order->total_amount);
        $this->assertStringStartsWith('FLR-261110-', $order->order_number);

        $item = $order->items->first();
        $this->assertSame($b->id, $item->bouquet_id);
        $this->assertNull($item->flower_id);
        $this->assertSame('Rose Love', $item->bouquet_name);
        $this->assertEquals(100000, $item->price);
        $this->assertSame(2, $item->quantity);
        $this->assertEquals(200000, $item->subtotal);

        $this->assertSame(8, $b->fresh()->stock);

        // keranjang & draft checkout dikosongkan
        $this->assertEmpty(session('cart', []));
        $this->assertNull(session('checkout'));
    }

    public function test_confirmation_page_shows_the_order_to_its_owner_only(): void
    {
        $this->readyToPlace($this->bouquet('Rose Love'));
        $this->post('/checkout/place');
        $order = Order::first();

        $this->get("/orders/{$order->order_number}")->assertOk()
            ->assertSee($order->order_number)->assertSee('Menunggu pembayaran')
            ->assertSee('Rose Love')->assertSee('Budi Santoso')->assertSee('Rp 200.000');

        $this->actingAs(User::factory()->create())
            ->get("/orders/{$order->order_number}")->assertNotFound();

        auth()->logout();
        $this->get("/orders/{$order->order_number}")->assertRedirect(route('login'));
    }

    public function test_stems_are_ordered_with_bundle_price_and_decrement_flower_stock(): void
    {
        $b = $this->bouquet('Rose Love');
        $f = $this->flower('Rose Merah', 100);

        $this->actingAs(User::factory()->create());
        $this->post("/cart/items/{$b->slug}");
        $this->post("/cart/stems/{$f->slug}", ['quantity' => 25]);   // 2 paket + 5 = 180.000
        $this->post('/checkout', $this->checkoutData());

        $this->post('/checkout/place');

        $order = Order::first();
        $this->assertEquals(280000, $order->total_amount);
        $this->assertCount(2, $order->items);

        $stem = $order->items->firstWhere('flower_id', $f->id);
        $this->assertNull($stem->bouquet_id);
        $this->assertSame('Rose Merah', $stem->name);
        $this->assertSame(25, $stem->quantity);
        $this->assertEquals(8000, $stem->price);
        $this->assertEquals(180000, $stem->subtotal);

        $this->assertSame(75, $f->fresh()->stock);
        $this->assertSame(9, $b->fresh()->stock);
        $this->assertEmpty(session('cart_stems', []));
    }

    public function test_order_keeps_price_snapshot_when_catalog_price_changes(): void
    {
        $b = $this->bouquet('Rose Love', 100000);
        $this->readyToPlace($b, 1);
        $this->post('/checkout/place');

        $b->update(['price' => 999000, 'name' => 'Nama Baru']);

        $item = OrderItem::first();
        $this->assertEquals(100000, $item->price);
        $this->assertSame('Rose Love', $item->name);
        $this->assertEquals(100000, Order::first()->total_amount);
    }

    public function test_double_submit_creates_only_one_order(): void
    {
        $b = $this->bouquet('Rose Love', 100000, 10);
        $this->readyToPlace($b, 2);

        $this->post('/checkout/place');
        $this->post('/checkout/place')->assertRedirect(route('cart.index'));

        $this->assertSame(1, Order::count());
        $this->assertSame(8, $b->fresh()->stock);   // hanya dikurangi sekali
    }

    public function test_place_requires_login_and_draft(): void
    {
        $this->post('/checkout/place')->assertRedirect(route('login'));

        $b = $this->bouquet('Rose Love');
        $this->actingAs(User::factory()->create());
        $this->post("/cart/items/{$b->slug}");

        $this->post('/checkout/place')->assertRedirect(route('checkout.index'))->assertSessionHas('error');
        $this->assertSame(0, Order::count());
        $this->assertSame(10, $b->fresh()->stock);
    }

    public function test_stale_schedule_blocks_order_and_keeps_stock(): void
    {
        $b = $this->bouquet('Rose Love');
        $this->readyToPlace($b, 1);

        Carbon::setTestNow(Carbon::parse('2026-11-13 08:00:00'));   // tanggal kirim (12 Nov) sudah lewat

        $this->post('/checkout/place')->assertRedirect(route('checkout.index'))->assertSessionHas('error');
        $this->assertSame(0, Order::count());
        $this->assertSame(10, $b->fresh()->stock);
    }

    public function test_stock_dropped_since_cart_was_built_is_caught_before_ordering(): void
    {
        $b = $this->bouquet('Rose Love', 100000, 10);
        $this->readyToPlace($b, 5);

        $b->update(['stock' => 3]);

        $this->post('/checkout/place')->assertRedirect(route('cart.index'))->assertSessionHas('error');
        $this->assertSame(0, Order::count());
        $this->assertSame(3, $b->fresh()->stock);
    }

    // ------------------------------------------------ service (logika inti)

    public function test_service_all_or_nothing_when_any_item_lacks_stock(): void
    {
        $ok = $this->bouquet('Rose Love', 100000, 5);
        $short = $this->bouquet('Tulip Dream', 150000, 1);
        $user = User::factory()->create();

        try {
            app(OrderService::class)->place($user, $this->checkoutData(), [$ok->id => 2, $short->id => 3], []);
            $this->fail('OrderException seharusnya dilempar.');
        } catch (OrderException $e) {
            $this->assertStringContainsString('Tulip Dream', $e->getMessage());
        }

        $this->assertSame(5, $ok->fresh()->stock);      // tidak ada yang berkurang
        $this->assertSame(1, $short->fresh()->stock);
        $this->assertSame(0, Order::count());
        $this->assertSame(0, OrderItem::count());
    }

    public function test_service_never_oversells_last_unit(): void
    {
        $b = $this->bouquet('Rose Love', 100000, 1);
        $a = User::factory()->create();
        $c = User::factory()->create();
        $service = app(OrderService::class);

        $service->place($a, $this->checkoutData(), [$b->id => 1], []);

        $this->expectException(OrderException::class);
        try {
            $service->place($c, $this->checkoutData(), [$b->id => 1], []);
        } finally {
            $this->assertSame(0, $b->fresh()->stock);   // tidak negatif
            $this->assertSame(1, Order::count());
        }
    }

    public function test_service_can_take_exact_remaining_stock_and_stems(): void
    {
        $b = $this->bouquet('Rose Love', 100000, 3);
        $f = $this->flower('Rose Merah', 12);

        app(OrderService::class)->place(User::factory()->create(), $this->checkoutData(), [$b->id => 3], [$f->id => 12]);

        $this->assertSame(0, $b->fresh()->stock);
        $this->assertSame(0, $f->fresh()->stock);
    }

    public function test_service_rejects_hidden_or_missing_items_and_empty_cart(): void
    {
        $b = $this->bouquet('Rose Love');
        $f = $this->flower('Rose Merah');
        $user = User::factory()->create();
        $service = app(OrderService::class);

        $b->update(['is_active' => false]);
        $f->update(['is_active' => false]);

        foreach ([[[$b->id => 1], []], [[], [$f->id => 1]], [[999 => 1], []], [[], []]] as [$bq, $sq]) {
            try {
                $service->place($user, $this->checkoutData(), $bq, $sq);
                $this->fail('OrderException seharusnya dilempar.');
            } catch (OrderException $e) {
                $this->assertNotSame('', $e->getMessage());
            }
        }

        $this->assertSame(0, Order::count());
    }
}