<?php

namespace Tests\Feature;

use App\Models\Bouquet;
use App\Models\Category;
use App\Models\Flower;
use App\Models\User;
use App\Services\DeliverySchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-10 08:00:00'));   // Selasa pagi
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function customer(): User
    {
        return User::factory()->create();
    }

    private function bouquet(string $name, bool $sameDay = false, int $stock = 10): Bouquet
    {
        $cat = Category::firstOrCreate(['slug' => 'hand-bouquet'], ['name' => 'Hand Bouquet']);

        return Bouquet::unguarded(fn () => Bouquet::create([
            'category_id' => $cat->id, 'name' => $name, 'slug' => str($name)->slug(),
            'price' => 100000, 'stock' => $stock, 'is_same_day' => $sameDay, 'is_active' => true,
        ]));
    }

    private function addToCart(Bouquet $b, int $qty = 1)
    {
        return $this->post("/cart/items/{$b->slug}", ['quantity' => $qty]);
    }

    private function payload(array $override = []): array
    {
        return array_merge([
            'recipient_name' => 'Budi Santoso',
            'recipient_phone' => '081234567890',
            'delivery_city' => 'Bandung',
            'delivery_address' => 'Jl. Merdeka No. 10, Bandung',
            'delivery_date' => now()->addDays(2)->toDateString(),
            'delivery_slot' => 'morning',
            'occasion' => 'birthday',
            'card_message' => 'Selamat ulang tahun!',
            'sender_name' => 'Amanda',
        ], $override);
    }

    /** Login + keranjang berisi 1 bouquet. */
    private function readyCart(bool $sameDay = false): Bouquet
    {
        $this->actingAs($this->customer());
        $b = $this->bouquet('Rose Love', $sameDay);
        $this->addToCart($b);

        return $b;
    }

    // ------------------------------------------------------------ akses

    public function test_guest_is_sent_to_login(): void
    {
        $this->get('/checkout')->assertRedirect(route('login'));
        $this->post('/checkout', $this->payload())->assertRedirect(route('login'));
        $this->get('/checkout/review')->assertRedirect(route('login'));
    }

    public function test_empty_cart_cannot_checkout(): void
    {
        $this->actingAs($this->customer());

        $this->get('/checkout')->assertRedirect(route('cart.index'))->assertSessionHas('error');
        $this->get('/checkout/review')->assertRedirect(route('cart.index'));
    }

    public function test_cart_checkout_button_is_now_active(): void
    {
        $this->readyCart();

        $this->get('/cart')->assertSee(route('checkout.index'), false)->assertDontSee('segera hadir');
    }

    public function test_changed_stock_sends_customer_back_to_cart_with_notice(): void
    {
        $b = $this->readyCart();
        $b->update(['stock' => 0]);

        $this->get('/checkout')->assertRedirect(route('cart.index'))->assertSessionHas('error');
        $this->get('/cart')->assertSee('Keranjangmu masih kosong');
    }

    // ------------------------------------------------------------- form

    public function test_form_shows_all_sections_and_summary(): void
    {
        $this->readyCart();

        $this->get('/checkout')->assertOk()
            ->assertSee('Data Penerima')->assertSee('Slot Waktu Pengiriman')->assertSee('Pesan Kartu Ucapan')
            ->assertSee('Rose Love')->assertSee('Rp 100.000')
            ->assertSee('maxlength="200"', false)
            ->assertSee('Pagi (09.00–12.00)');
    }

    public function test_stems_appear_in_summary(): void
    {
        $this->actingAs($this->customer());
        $f = Flower::create([
            'name' => 'Rose Merah', 'slug' => 'rose-merah', 'species' => 'rose', 'price_per_stem' => 8000,
            'bundle_size' => 10, 'bundle_price' => 70000, 'stock' => 100, 'is_active' => true,
        ]);
        $this->post("/cart/stems/{$f->slug}", ['quantity' => 25]);

        $this->get('/checkout')->assertOk()->assertSee('Rose Merah × 25 tangkai')->assertSee('Rp 180.000');
    }

    public function test_min_date_depends_on_same_day_eligibility(): void
    {
        $this->readyCart(sameDay: false);
        $this->get('/checkout')->assertSee('min="2026-11-11"', false);

        $this->delete('/cart');
        $this->addToCart($this->bouquet('Fast Rose', true));
        $this->get('/checkout')->assertSee('min="2026-11-10"', false);
    }

    // ------------------------------------------------------ simpan & tinjau

    public function test_valid_submit_goes_to_review_and_shows_data(): void
    {
        $this->readyCart();

        $this->post('/checkout', $this->payload(['recipient_phone' => '0812 3456-7890']))
            ->assertRedirect(route('checkout.review'))
            ->assertSessionHasNoErrors();

        $this->assertSame('081234567890', session('checkout.recipient_phone'));   // dinormalkan

        $this->get('/checkout/review')->assertOk()
            ->assertSee('Budi Santoso')->assertSee('Jl. Merdeka No. 10')
            ->assertSee('Selamat ulang tahun!')->assertSee('Pagi (09.00–12.00)')
            ->assertSee('Rp 100.000');
    }

    public function test_back_to_form_keeps_what_was_filled(): void
    {
        $this->readyCart();
        $this->post('/checkout', $this->payload());

        $this->get('/checkout')->assertSee('Budi Santoso')->assertSee('Jl. Merdeka No. 10, Bandung');
    }

    public function test_review_without_draft_goes_back_to_form(): void
    {
        $this->readyCart();

        $this->get('/checkout/review')->assertRedirect(route('checkout.index'));
    }

    public function test_invalid_fields_are_rejected(): void
    {
        $this->readyCart();

        $cases = [
            ['recipient_name' => ''],
            ['recipient_phone' => '12345'],
            ['recipient_phone' => '+1 555 0100'],
            ['delivery_city' => ''],
            ['delivery_address' => 'pendek'],
            ['delivery_date' => ''],
            ['delivery_date' => 'besok'],
            ['delivery_slot' => 'midnight'],
            ['delivery_slot' => ''],
            ['occasion' => 'hack'],
            ['card_message' => str_repeat('a', 201)],
            ['delivery_date' => now()->subDay()->toDateString()],
            ['delivery_date' => now()->addDays(DeliverySchedule::MAX_DAYS + 1)->toDateString()],
        ];

        foreach ($cases as $bad) {
            $this->post('/checkout', $this->payload($bad))->assertSessionHasErrors(array_key_first($bad));
        }

        $this->assertNull(session('checkout'));
    }

    public function test_optional_card_fields_may_be_empty_and_accepts_exactly_200_chars(): void
    {
        $this->readyCart();

        $this->post('/checkout', $this->payload(['occasion' => '', 'card_message' => '', 'sender_name' => '']))
            ->assertRedirect(route('checkout.review'));
        $this->post('/checkout', $this->payload(['card_message' => str_repeat('a', 200)]))
            ->assertRedirect(route('checkout.review'));
    }

    public function test_anonymous_clears_sender_name(): void
    {
        $this->readyCart();

        $this->post('/checkout', $this->payload(['is_anonymous' => '1', 'sender_name' => 'Amanda']));

        $this->assertNull(session('checkout.sender_name'));
        $this->assertTrue(session('checkout.is_anonymous'));
        $this->get('/checkout/review')->assertSee('Anonim')->assertDontSee('Amanda');
    }

    // ------------------------------------------------- jadwal & same-day

    public function test_slots_for_helper(): void
    {
        $now = now();   // 08:00

        $this->assertSame(['afternoon', 'evening'], DeliverySchedule::slotsFor($now->copy(), $now));
        $this->assertSame(['morning', 'afternoon', 'evening'], DeliverySchedule::slotsFor($now->copy()->addDay(), $now));
        $this->assertSame([], DeliverySchedule::slotsFor($now->copy()->subDay(), $now));

        $late = Carbon::parse('2026-11-10 14:00:00');
        $this->assertSame([], DeliverySchedule::slotsFor($late->copy(), $late));
        $this->assertSame('2026-11-11', DeliverySchedule::earliestDate(true, $late)->toDateString());
    }

    public function test_today_needs_all_bouquets_to_be_same_day(): void
    {
        $this->readyCart(sameDay: false);
        $today = now()->toDateString();

        $this->post('/checkout', $this->payload(['delivery_date' => $today, 'delivery_slot' => 'afternoon']))
            ->assertSessionHasErrors('delivery_date');

        // satu bouquet non same-day sudah cukup menolak hari ini
        $this->addToCart($this->bouquet('Fast Rose', true));
        $this->post('/checkout', $this->payload(['delivery_date' => $today, 'delivery_slot' => 'afternoon']))
            ->assertSessionHasErrors('delivery_date');
    }

    public function test_same_day_allowed_with_same_day_bouquets_and_future_slots_only(): void
    {
        $this->actingAs($this->customer());
        $this->addToCart($this->bouquet('Fast Rose', true));
        $today = now()->toDateString();

        // 09.00 < 08.00 + 3 jam → ditolak
        $this->post('/checkout', $this->payload(['delivery_date' => $today, 'delivery_slot' => 'morning']))
            ->assertSessionHasErrors('delivery_slot');

        $this->post('/checkout', $this->payload(['delivery_date' => $today, 'delivery_slot' => 'afternoon']))
            ->assertRedirect(route('checkout.review'));
    }

    public function test_tomorrow_allows_every_slot(): void
    {
        $this->readyCart();
        $tomorrow = now()->addDay()->toDateString();

        foreach (array_keys(DeliverySchedule::SLOTS) as $slot) {
            $this->post('/checkout', $this->payload(['delivery_date' => $tomorrow, 'delivery_slot' => $slot]))
                ->assertRedirect(route('checkout.review'));
        }
    }

    public function test_stem_only_cart_can_pick_today(): void
    {
        $this->actingAs($this->customer());
        $f = Flower::create([
            'name' => 'Lily', 'slug' => 'lily', 'species' => 'lily', 'price_per_stem' => 25000,
            'stock' => 50, 'is_active' => true,
        ]);
        $this->post("/cart/stems/{$f->slug}", ['quantity' => 3]);

        $this->post('/checkout', $this->payload(['delivery_date' => now()->toDateString(), 'delivery_slot' => 'evening']))
            ->assertRedirect(route('checkout.review'));
    }

    public function test_stale_schedule_is_rejected_on_review(): void
    {
        $this->actingAs($this->customer());
        $this->addToCart($this->bouquet('Fast Rose', true));

        $this->post('/checkout', $this->payload(['delivery_date' => now()->toDateString(), 'delivery_slot' => 'afternoon']))
            ->assertRedirect(route('checkout.review'));

        Carbon::setTestNow(Carbon::parse('2026-11-11 08:00:00'));   // besoknya, tanggal tersimpan sudah lewat

        $this->get('/checkout/review')->assertRedirect(route('checkout.index'))->assertSessionHas('error');
    }
}