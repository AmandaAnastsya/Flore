<?php

namespace Tests\Feature;

use App\Models\Bouquet;
use App\Models\Category;
use App\Models\Order;
use App\Models\Reminder;
use App\Models\Testimonial;
use App\Models\User;
use App\Notifications\SpecialMomentReminder;
use Database\Seeders\RecommendationRuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Functional Testing FR-01 s/d FR-12 (Proposal Tabel 3.1).
 * Setiap test = satu test case: ID, input → proses → output yang diharapkan.
 * Daftar lengkap & hasil: lihat laporan pengujian.
 */
class FunctionalRequirementsTest extends TestCase
{
    use RefreshDatabase;

    private Category $cat;
    private Bouquet $rose;
    private Bouquet $lily;
    private User $customer;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-23 08:00'));
        Storage::fake('local');
        Storage::fake('public');
        Notification::fake();

        $this->cat = Category::create(['name' => 'Hand Bouquet', 'slug' => 'hand-bouquet', 'is_active' => true]);
        $this->rose = $this->bouquet('Sweet Blush Romance', ['price' => 300000, 'stock' => 5, 'description' => 'Pink Spray Roses & Peonies']);
        $this->lily = $this->bouquet('Graceful Pure White', ['price' => 150000, 'stock' => 2, 'color' => 'white', 'description' => 'White Lily & Tulips']);

        $this->customer = User::factory()->create(['role' => 'customer', 'name' => 'Clarissa', 'email' => 'clarissa@example.com', 'password' => 'password', 'phone' => '081234567890']);
        $this->admin = User::factory()->create(['role' => 'admin', 'email' => 'admin@example.com', 'password' => 'password']);
    }

    private function bouquet(string $name, array $attrs = []): Bouquet
    {
        return Bouquet::unguarded(fn () => Bouquet::create(array_merge([
            'category_id' => $this->cat->id, 'name' => $name, 'slug' => str($name)->slug(), 'price' => 100000, 'stock' => 5,
            'low_stock_threshold' => 1, 'is_active' => true, 'size' => 'medium',
            'occasion' => 'anniversary', 'color' => 'pink', 'style' => 'soft-romantic',
        ], $attrs)));
    }

    private function checkoutData(array $override = []): array
    {
        return array_merge([
            'recipient_name' => 'Budi Santoso', 'recipient_phone' => '081234567890', 'delivery_city' => 'Jakarta',
            'delivery_address' => 'Jl. Gunawarman No. 42, Kebayoran Baru', 'delivery_date' => '2026-11-25',
            'delivery_slot' => 'afternoon', 'occasion' => 'anniversary', 'card_message' => 'Happy anniversary!',
        ], $override);
    }

    /** Customer membuat pesanan lewat UI; mengembalikan order. */
    private function placeOrder(array $items = [['rose', 1]]): Order
    {
        $this->actingAs($this->customer);
        foreach ($items as [$b, $qty]) {
            $this->post(route('cart.store', $this->{$b}), ['quantity' => $qty]);
        }
        $this->post('/checkout', $this->checkoutData());
        $this->post('/checkout/place');

        return Order::where('user_id', $this->customer->id)->latest('id')->firstOrFail();
    }

    private function payAndConfirm(Order $order): void
    {
        $this->actingAs($this->customer)->post(route('orders.payment', $order->order_number), [
            'payment_proof' => UploadedFile::fake()->create('bukti.pdf', 50, 'application/pdf'),
        ]);
        $this->actingAs($this->admin)->post(route('admin.orders.confirm', $order));
    }

    // ================================================================ FR-01 User Registration

    /** TC-FR01-01 | Input: data lengkap & valid → akun customer dibuat, langsung login, diarahkan ke /home. */
    public function test_TC_FR01_01_register_with_valid_data(): void
    {
        $this->post('/register', [
            'name' => 'Amanda', 'email' => 'amanda@example.com', 'phone' => '081298765432', 'date_of_birth' => '2006-01-03',
            'password' => 'rahasia123', 'password_confirmation' => 'rahasia123',
        ])->assertRedirect('/home');

        $user = User::where('email', 'amanda@example.com')->firstOrFail();
        $this->assertSame('customer', $user->role);
        $this->assertSame('2006-01-03', $user->date_of_birth->toDateString());
        $this->assertAuthenticatedAs($user);
    }

    /** TC-FR01-02 | Input: email yang sudah terdaftar → ditolak dengan pesan error. */
    public function test_TC_FR01_02_register_with_duplicate_email(): void
    {
        $this->post('/register', [
            'name' => 'X', 'email' => 'clarissa@example.com', 'phone' => '081298765432',
            'password' => 'rahasia123', 'password_confirmation' => 'rahasia123',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    /** TC-FR01-03 | Input: field kosong, no. HP salah, password pendek/tidak sama, tanggal lahir di masa depan → semua ditolak. */
    public function test_TC_FR01_03_register_validation_errors(): void
    {
        $this->post('/register', [])->assertSessionHasErrors(['name', 'email', 'phone', 'password']);

        $this->post('/register', [
            'name' => 'X', 'email' => 'bukan-email', 'phone' => '12345', 'date_of_birth' => '2030-01-01',
            'password' => 'pendek', 'password_confirmation' => 'beda',
        ])->assertSessionHasErrors(['email', 'phone', 'date_of_birth', 'password']);

        $this->assertSame(2, User::count());
    }

    // ================================================================ FR-02 User Login

    /** TC-FR02-01 | Input: email & password customer benar → masuk ke dashboard customer (/home). */
    public function test_TC_FR02_01_customer_login_success(): void
    {
        $this->post('/login', ['email' => 'clarissa@example.com', 'password' => 'password'])->assertRedirect('/home');
        $this->assertAuthenticatedAs($this->customer);
        $this->get('/home')->assertOk()->assertSee('Halo, Clarissa!');
    }

    /** TC-FR02-02 | Input: akun admin → diarahkan ke dashboard admin. */
    public function test_TC_FR02_02_admin_login_redirects_to_admin_dashboard(): void
    {
        $this->post('/login', ['email' => 'admin@example.com', 'password' => 'password'])->assertRedirect(route('admin.dashboard'));
    }

    /** TC-FR02-03 | Input: password salah / email tidak terdaftar → pesan error, tetap tamu. */
    public function test_TC_FR02_03_login_with_wrong_credentials(): void
    {
        $this->post('/login', ['email' => 'clarissa@example.com', 'password' => 'salah'])->assertSessionHasErrors('email');
        $this->post('/login', ['email' => 'tidakada@example.com', 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    /** TC-FR02-04 | Input: 5x password salah → percobaan berikutnya dikunci sementara (brute-force protection). */
    public function test_TC_FR02_04_login_is_throttled_after_repeated_failures(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'clarissa@example.com', 'password' => 'salah']);
        }

        $this->post('/login', ['email' => 'clarissa@example.com', 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();   // password benar pun ditolak selama terkunci
    }

    /** TC-FR02-05 | Logout → sesi berakhir, halaman akun tidak bisa dibuka. */
    public function test_TC_FR02_05_logout(): void
    {
        $this->actingAs($this->customer)->post('/logout')->assertRedirect('/');
        $this->assertGuest();
        $this->get('/home')->assertRedirect(route('login'));
    }

    // ================================================================ FR-03 Browse & Search Catalogue

    /** TC-FR03-01 | Buka katalog → daftar bouquet dengan nama, harga, gambar & status stok. */
    public function test_TC_FR03_01_catalogue_lists_bouquets_with_price_image_and_stock(): void
    {
        $this->lily->update(['stock' => 1]);

        $this->get('/catalog')->assertOk()
            ->assertSee('Sweet Blush Romance')->assertSee('Rp 300.000')
            ->assertSee('Graceful Pure White')->assertSee('Sisa 1')
            ->assertSee($this->rose->image_url, false);
    }

    /** TC-FR03-02 | Input: kata kunci (nama atau jenis bunga) → hanya bouquet yang cocok. */
    public function test_TC_FR03_02_keyword_search_matches_name_and_flower_type(): void
    {
        $this->get('/catalog?q=blush')->assertSee('Sweet Blush Romance')->assertDontSee('Graceful Pure White');
        $this->get('/catalog?q=lily')->assertSee('Graceful Pure White')->assertDontSee('Sweet Blush Romance');
        $this->get('/catalog?q=anggrek')->assertOk()->assertSee('Tidak ada bouquet yang cocok');
    }

    /** TC-FR03-03 | Input: pilih kategori & filter → hasil sesuai kategori/atribut. */
    public function test_TC_FR03_03_filter_by_category_and_attributes(): void
    {
        $box = Category::create(['name' => 'Bloom Box', 'slug' => 'bloom-box', 'is_active' => true]);
        $this->bouquet('Velvet Box', ['category_id' => $box->id, 'color' => 'red']);

        $this->get("/catalog?category={$box->id}")->assertSee('Velvet Box')->assertDontSee('Sweet Blush Romance');
        $this->get('/catalog?color[]=white')->assertSee('Graceful Pure White')->assertDontSee('Velvet Box')->assertDontSee('Sweet Blush Romance');
    }

    /** TC-FR03-04 | Bouquet/kategori nonaktif tidak tampil di katalog maupun halaman detail. */
    public function test_TC_FR03_04_inactive_products_are_hidden(): void
    {
        $this->rose->update(['is_active' => false]);

        $this->get('/catalog')->assertDontSee('Sweet Blush Romance');
        $this->get('/catalog/sweet-blush-romance')->assertNotFound();
    }

    /** TC-FR03-05 | Halaman detail → informasi lengkap + ketersediaan stok. */
    public function test_TC_FR03_05_product_detail_page(): void
    {
        $this->get('/catalog/sweet-blush-romance')->assertOk()
            ->assertSee('Sweet Blush Romance')->assertSee('Rp 300.000')->assertSee('Pink Spray Roses &amp; Peonies', false)
            ->assertSee('Tersedia')->assertSee('name="quantity"', false);
    }

    // ================================================================ FR-04 Cart Management

    /** TC-FR04-01 | Tambah item → ringkasan keranjang dengan subtotal benar. */
    public function test_TC_FR04_01_add_items_and_see_subtotal(): void
    {
        $this->post(route('cart.store', $this->rose), ['quantity' => 2])->assertSessionHas('success');
        $this->post(route('cart.store', $this->lily), ['quantity' => 1]);

        $this->get('/cart')->assertOk()->assertSee('Sweet Blush Romance')->assertSee('Graceful Pure White')->assertSee('Rp 750.000');
    }

    /** TC-FR04-02 | Ubah jumlah → subtotal dihitung ulang; jumlah melebihi stok ditolak. */
    public function test_TC_FR04_02_update_quantity_with_stock_check(): void
    {
        $this->post(route('cart.store', $this->rose), ['quantity' => 1]);

        $this->patch(route('cart.update', $this->rose), ['quantity' => 3]);
        $this->get('/cart')->assertSee('Rp 900.000');

        $this->patch(route('cart.update', $this->rose), ['quantity' => 6])->assertSessionHas('error', 'Stok Sweet Blush Romance hanya tersisa 5.');
        $this->get('/cart')->assertSee('Rp 900.000');   // tidak berubah
    }

    /** TC-FR04-03 | Tambah melebihi stok / produk habis → ditolak dengan pesan. */
    public function test_TC_FR04_03_cannot_add_more_than_stock(): void
    {
        $this->post(route('cart.store', $this->lily), ['quantity' => 2])->assertSessionHas('success');
        $this->post(route('cart.store', $this->lily), ['quantity' => 1])->assertSessionHas('error');

        $this->rose->update(['stock' => 0]);
        $this->post(route('cart.store', $this->rose), ['quantity' => 1])->assertSessionHas('error', 'Sweet Blush Romance sedang habis.');
    }

    /** TC-FR04-04 | Hapus satu item & kosongkan keranjang. */
    public function test_TC_FR04_04_remove_item_and_clear_cart(): void
    {
        $this->post(route('cart.store', $this->rose), ['quantity' => 1]);
        $this->post(route('cart.store', $this->lily), ['quantity' => 1]);

        $this->delete(route('cart.destroy', $this->rose))->assertSessionHas('success', 'Sweet Blush Romance dihapus dari keranjang.');
        $this->get('/cart')->assertDontSee(route('cart.update', $this->rose), false)->assertSee(route('cart.update', $this->lily), false);

        $this->delete(route('cart.clear'));
        $this->get('/cart')->assertSee('Keranjangmu masih kosong');
    }

    /** TC-FR04-05 | Tambah dari katalog, detail produk & landing → pesan berhasil/gagal TAMPIL di halaman itu (bug UI-01). */
    public function test_TC_FR04_05_add_to_cart_feedback_is_visible_on_every_page(): void
    {
        foreach (['/catalog', '/catalog/sweet-blush-romance', '/'] as $page) {
            $this->from($page)->post(route('cart.store', $this->rose), ['quantity' => 1])->assertRedirect($page);
            $this->get($page)->assertSee('Sweet Blush Romance ditambahkan ke keranjang.');
        }

        $this->from('/catalog/sweet-blush-romance')->post(route('cart.store', $this->rose), ['quantity' => 10]);
        $this->get('/catalog/sweet-blush-romance')->assertSee('alert-danger', false);
    }

    /** TC-FR04-06 | Tautan "Lihat keranjang" hanya di pesan halaman belanja, bukan di pesanan/admin (bug UI-02). */
    public function test_TC_FR04_06_cart_link_only_on_shopping_messages(): void
    {
        $this->from('/catalog')->post(route('cart.store', $this->rose), ['quantity' => 1]);
        $this->get('/catalog')->assertSee('Lihat keranjang');

        $order = $this->placeOrder();
        $this->post(route('orders.payment', $order->order_number), ['payment_proof' => UploadedFile::fake()->create('b.pdf', 10, 'application/pdf')]);
        $this->get(route('orders.show', $order->order_number))->assertSee('Bukti pembayaran terkirim')->assertDontSee('Lihat keranjang');

        $this->actingAs($this->admin)->from(route('admin.orders.show', $order))->post(route('admin.orders.confirm', $order));
        $this->get(route('admin.orders.show', $order))->assertSee('alert-success', false)->assertDontSee('Lihat keranjang');
    }

    // ================================================================ FR-05 Checkout & Place Order

    /** TC-FR05-01 | Input: data pengiriman + occasion → pesanan tersimpan, nomor pesanan tampil, stok berkurang. */
    public function test_TC_FR05_01_checkout_creates_order_and_decrements_stock(): void
    {
        $order = $this->placeOrder([['rose', 2]]);

        $this->assertMatchesRegularExpression('/^FLR-\d{6}-[A-Z0-9]{5}$/', $order->order_number);
        $this->assertEquals(600000, $order->total_amount);
        $this->assertSame('anniversary', $order->occasion);
        $this->assertSame(3, $this->rose->fresh()->stock);
        $this->get(route('orders.show', $order->order_number))->assertOk()->assertSee($order->order_number)->assertSee('Pesanan berhasil dibuat');
        $this->get('/cart')->assertSee('Keranjangmu masih kosong');
    }

    /** TC-FR05-02 | Input: data pengiriman kosong / tanggal lewat → ditolak, tidak ada pesanan. */
    public function test_TC_FR05_02_checkout_validation(): void
    {
        $this->actingAs($this->customer)->post(route('cart.store', $this->rose), ['quantity' => 1]);

        $this->post('/checkout', [])->assertSessionHasErrors(['recipient_name', 'recipient_phone', 'delivery_city', 'delivery_address', 'delivery_date', 'delivery_slot']);
        $this->post('/checkout', $this->checkoutData(['delivery_date' => '2026-11-01']))->assertSessionHasErrors('delivery_date');
        $this->post('/checkout', $this->checkoutData(['recipient_phone' => '123']))->assertSessionHasErrors('recipient_phone');

        $this->assertSame(0, Order::count());
    }

    /** TC-FR05-03 | Tamu mengisi keranjang → diminta login → keranjang tetap ada setelah login. */
    public function test_TC_FR05_03_guest_cart_survives_login(): void
    {
        $this->post(route('cart.store', $this->rose), ['quantity' => 2]);
        $this->get('/checkout')->assertRedirect(route('login'));

        $this->post('/login', ['email' => 'clarissa@example.com', 'password' => 'password']);

        $this->get('/checkout')->assertOk()->assertSee('Sweet Blush Romance')->assertSee('Rp 600.000');
    }

    /** TC-FR05-04 | Checkout dengan keranjang kosong → dikembalikan ke keranjang. */
    public function test_TC_FR05_04_checkout_with_empty_cart(): void
    {
        $this->actingAs($this->customer)->get('/checkout')->assertRedirect(route('cart.index'));
        $this->post('/checkout/place')->assertRedirect(route('cart.index'));
        $this->assertSame(0, Order::count());
    }

    // ================================================================ FR-06 Recommendation Wizard

    /** TC-FR06-01 | Input: occasion, warna, budget, gaya, ukuran → daftar bouquet terurut skor. */
    public function test_TC_FR06_01_wizard_returns_scored_recommendations(): void
    {
        $this->seed(RecommendationRuleSeeder::class);

        $res = $this->getJson('/recommendations?occasion=anniversary&color=pink&style=soft-romantic&size=medium&budget=200-350')->assertOk();

        $this->assertSame('Sweet Blush Romance', $res->json('items.0.name'));
        $this->assertSame(100, $res->json('items.0.percent'));
        $this->assertContains('Sesuai budget', $res->json('items.0.reasons'));
    }

    /** TC-FR06-02 | Tidak ada yang cocok di budget → alternatif terdekat, tidak kosong. */
    public function test_TC_FR06_02_wizard_fallback_when_budget_does_not_match(): void
    {
        $this->seed(RecommendationRuleSeeder::class);

        $res = $this->getJson('/recommendations?occasion=anniversary&budget=over-750')->assertOk();

        // stok anniversary ada (Rp 150–300 rb) tapi tidak ada yang ≥ Rp 750 rb → tetap ditawari, bukan jalan buntu
        $this->assertSame('outside_budget', $res->json('mode'));
        $this->assertSame('Belum ada bouquet di budget itu. Ini pilihan paling cocok yang tersedia.', $res->json('message'));
        $this->assertCount(2, $res->json('items'));
        $this->assertContains('Di luar budget', $res->json('items.0.reasons'));
        $this->assertNotContains('Sedikit di luar budget', $res->json('items.0.reasons'));
    }

    /** TC-FR06-03 | Hasil wizard bisa langsung dimasukkan ke keranjang. */
    public function test_TC_FR06_03_recommended_item_can_be_added_to_cart(): void
    {
        $this->seed(RecommendationRuleSeeder::class);
        $top = $this->getJson('/recommendations?occasion=anniversary')->json('items.0');

        $this->post($top['cart_url'], ['quantity' => 1])->assertSessionHas('success');
        $this->get('/cart')->assertSee($top['name']);
    }

    // ================================================================ FR-07 Track / Cancel Order

    /** TC-FR07-01 | Customer melihat daftar & status pesanan; status mengikuti update admin. */
    public function test_TC_FR07_01_track_order_status(): void
    {
        $order = $this->placeOrder();

        $this->get('/orders')->assertSee($order->order_number)->assertSee('Menunggu pembayaran');

        $this->payAndConfirm($order);
        $this->actingAs($this->admin)->post(route('admin.orders.advance', $order));

        $this->actingAs($this->customer)->get(route('orders.show', $order->order_number))->assertSee('Sedang disiapkan')->assertSee('Lunas');
    }

    /** TC-FR07-02 | Batalkan pesanan yang belum dibayar → dibatalkan, stok kembali. */
    public function test_TC_FR07_02_cancel_unpaid_order_restores_stock(): void
    {
        $order = $this->placeOrder([['rose', 2]]);

        $this->post(route('orders.cancel', $order->order_number))->assertSessionHas('success');

        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame(5, $this->rose->fresh()->stock);
    }

    /** TC-FR07-03 | Pesanan yang sudah dibayar/dikonfirmasi tidak bisa dibatalkan customer. */
    public function test_TC_FR07_03_cannot_cancel_paid_order(): void
    {
        $order = $this->placeOrder();
        $this->payAndConfirm($order);

        $this->actingAs($this->customer)->post(route('orders.cancel', $order->order_number))->assertSessionHas('error');
        $this->assertSame('payment_confirmed', $order->fresh()->status);
    }

    // ================================================================ FR-08 Receive Reminder

    /** TC-FR08-01 | Tanggal momen H-7 → email pengingat terkirim & tercatat. */
    public function test_TC_FR08_01_due_reminder_is_emailed(): void
    {
        $this->customer->update(['date_of_birth' => '1998-11-30']);

        $this->artisan('reminders:send')->assertSuccessful();

        Notification::assertSentTo($this->customer, SpecialMomentReminder::class);
        $this->assertDatabaseHas('reminders', ['user_id' => $this->customer->id, 'status' => 'sent']);
    }

    /** TC-FR08-02 | Momen belum masuk jadwal / pengingat dinonaktifkan → tidak ada email. */
    public function test_TC_FR08_02_no_reminder_when_not_due_or_disabled(): void
    {
        $this->customer->update(['date_of_birth' => '1998-12-25']);
        User::factory()->create(['role' => 'customer', 'date_of_birth' => '1998-11-28', 'reminders_enabled' => false]);

        $this->artisan('reminders:send');

        Notification::assertNothingSent();
        $this->assertSame(0, Reminder::where('status', 'sent')->count());
    }

    // ================================================================ FR-09 Submit Testimonial

    /** TC-FR09-01 | Input: rating & isi untuk pesanan terkirim → tersimpan menunggu persetujuan admin. */
    public function test_TC_FR09_01_submit_testimonial(): void
    {
        $order = $this->placeOrder();
        $order->update(['status' => 'delivered', 'payment_status' => 'confirmed']);

        $this->actingAs($this->customer)->post(route('orders.testimonial', $order->order_number), ['rating' => 5, 'content' => 'Bunganya segar dan cantik!'])
            ->assertSessionHas('success');

        $this->assertSame('pending', Testimonial::sole()->status());
        $this->get('/')->assertDontSee('Bunganya segar dan cantik!');
    }

    /** TC-FR09-02 | Rating di luar 1–5 / isi terlalu pendek / pesanan belum terkirim → ditolak. */
    public function test_TC_FR09_02_testimonial_validation(): void
    {
        $order = $this->placeOrder();
        $url = route('orders.testimonial', $order->order_number);

        $this->post($url, ['rating' => 5, 'content' => 'Belum diterima tapi'])->assertSessionHas('error');

        $order->update(['status' => 'delivered']);
        $this->post($url, ['rating' => 0, 'content' => 'ok'])->assertSessionHasErrorsIn('testimonial', ['rating', 'content']);
        $this->assertSame(0, Testimonial::count());
    }

    // ================================================================ FR-10 Manage Bouquet, Category & Stock (Admin)

    /** TC-FR10-01 | Admin menambah kategori & bouquet → langsung tampil di katalog customer. */
    public function test_TC_FR10_01_admin_adds_category_and_bouquet(): void
    {
        $this->actingAs($this->admin);
        $this->post('/admin/categories', ['name' => 'Bloom Box', 'is_active' => '1'])->assertSessionHasNoErrors();
        $box = Category::where('slug', 'bloom-box')->firstOrFail();

        $this->post('/admin/bouquets', [
            'category_id' => $box->id, 'name' => 'Sunlit Peach Bloom', 'description' => 'Peach Roses', 'price' => 680000,
            'stock' => 4, 'low_stock_threshold' => 1, 'size' => 'large', 'occasion' => 'birthday', 'color' => 'peach',
            'style' => 'modern', 'is_active' => '1', 'is_same_day' => '1',
            'image' => UploadedFile::fake()->image('peach.jpg', 800, 600),
        ])->assertSessionHasNoErrors();

        $bouquet = Bouquet::where('name', 'Sunlit Peach Bloom')->firstOrFail();
        Storage::disk('public')->assertExists($bouquet->image);

        $this->get('/catalog?q=peach')->assertSee('Sunlit Peach Bloom')->assertSee('Rp 680.000');
    }

    /** TC-FR10-02 | Data tidak valid (harga/stok negatif, nama kategori ganda) → ditolak. */
    public function test_TC_FR10_02_admin_validation(): void
    {
        $this->actingAs($this->admin);

        $this->post('/admin/categories', ['name' => 'Hand Bouquet'])->assertSessionHasErrors('name');
        $this->post('/admin/bouquets', [
            'category_id' => 999, 'name' => '', 'price' => -1, 'stock' => -5, 'low_stock_threshold' => 0, 'size' => 'jumbo',
        ])->assertSessionHasErrors(['category_id', 'name', 'price', 'stock', 'size']);

        $this->assertSame(2, Bouquet::count());
    }

    /** TC-FR10-03 | Edit harga & update stok → tercermin di katalog customer. */
    public function test_TC_FR10_03_edit_price_and_stock_reflected_in_catalogue(): void
    {
        $this->actingAs($this->admin)->put("/admin/bouquets/{$this->rose->id}", [
            'category_id' => $this->cat->id, 'name' => 'Sweet Blush Romance', 'price' => 350000, 'stock' => 5,
            'low_stock_threshold' => 1, 'size' => 'medium', 'is_active' => '1',
        ])->assertSessionHasNoErrors();

        $this->patch("/admin/bouquets/{$this->rose->id}/stock", ['stock' => 0])->assertSessionHas('success');

        $this->get('/catalog/sweet-blush-romance')->assertSee('Rp 350.000')->assertSee('Stok habis');
    }

    /** TC-FR10-04 | Ganti nama bouquet → slug/URL katalog ikut diperbarui & tetap bisa dibuka. */
    public function test_TC_FR10_04_renamed_bouquet_is_still_reachable(): void
    {
        $this->actingAs($this->admin)->put("/admin/bouquets/{$this->rose->id}", [
            'category_id' => $this->cat->id, 'name' => 'Blush Romance Deluxe', 'price' => 300000, 'stock' => 5,
            'low_stock_threshold' => 1, 'size' => 'medium', 'is_active' => '1',
        ])->assertSessionHasNoErrors();

        $slug = $this->rose->fresh()->slug;
        $this->get("/catalog/{$slug}")->assertOk()->assertSee('Blush Romance Deluxe');
        $this->get('/catalog')->assertSee(route('catalog.show', $slug), false);
    }

    /** TC-FR10-05 | Hapus bouquet → hilang dari katalog; kategori yang masih dipakai tidak bisa dihapus. */
    public function test_TC_FR10_05_delete_bouquet_and_protected_category(): void
    {
        $this->actingAs($this->admin)->delete("/admin/bouquets/{$this->rose->id}")->assertSessionHas('success');
        $this->get('/catalog')->assertDontSee('Sweet Blush Romance');

        $this->delete("/admin/categories/{$this->cat->id}")->assertSessionHas('error');
        $this->assertModelExists($this->cat);
    }

    // ================================================================ FR-11 Manage Order, Payment Status & Customer (Admin)

    /** TC-FR11-01 | Verifikasi bukti bayar → status "Pembayaran dikonfirmasi"; majukan status sampai terkirim. */
    public function test_TC_FR11_01_verify_payment_and_advance_status(): void
    {
        $order = $this->placeOrder();
        $this->payAndConfirm($order);
        $this->assertSame('payment_confirmed', $order->fresh()->status);

        foreach (['preparing', 'out_for_delivery', 'delivered'] as $next) {
            $this->post(route('admin.orders.advance', $order));
            $this->assertSame($next, $order->fresh()->status);
        }

        $this->get('/admin/orders?status=delivered')->assertSee($order->order_number);
    }

    /** TC-FR11-02 | Tolak bukti bayar → customer diminta upload ulang. */
    public function test_TC_FR11_02_reject_payment_proof(): void
    {
        $order = $this->placeOrder();
        $this->post(route('orders.payment', $order->order_number), ['payment_proof' => UploadedFile::fake()->create('salah.pdf', 10, 'application/pdf')]);

        $this->actingAs($this->admin)->post(route('admin.orders.reject', $order))->assertSessionHas('success');

        $this->assertSame('rejected', $order->fresh()->payment_status);
        $this->actingAs($this->customer)->get(route('orders.show', $order->order_number))->assertSee('Bukti sebelumnya ditolak');
    }

    /** TC-FR11-03 | Admin membatalkan pesanan yang sudah dibayar → stok kembali & tidak dihitung pendapatan. */
    public function test_TC_FR11_03_admin_cancels_paid_order(): void
    {
        $order = $this->placeOrder([['rose', 2]]);
        $this->payAndConfirm($order);

        $this->actingAs($this->admin)->post(route('admin.orders.cancel', $order))->assertSessionHas('success');

        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame(5, $this->rose->fresh()->stock);
        $this->getJson('/admin/dashboard/metrics')->assertJsonPath('revenue_total', 0);
    }

    /** TC-FR11-04 | Admin melihat data customer & detail riwayat pesanannya. */
    public function test_TC_FR11_04_admin_views_customer_records(): void
    {
        $order = $this->placeOrder();

        $this->actingAs($this->admin)->get('/admin/reports/customers')->assertOk()->assertSee('Clarissa')->assertSee('clarissa@example.com')
            ->assertSee(route('admin.customers.show', $this->customer), false);
        $this->get(route('admin.customers.show', $this->customer))->assertOk()
            ->assertSee('Clarissa')->assertSee('081234567890')->assertSee($order->order_number);
        $this->get(route('admin.customers.show', $this->admin))->assertNotFound();   // bukan data customer

        $this->actingAs($this->customer)->get(route('admin.customers.show', $this->customer))->assertForbidden();
    }

    // ================================================================ FR-12 Dashboard & Reports (Admin)

    /** TC-FR12-01 | Dashboard menampilkan ringkasan penjualan & pesanan aktif yang benar. */
    public function test_TC_FR12_01_dashboard_summary(): void
    {
        $order = $this->placeOrder([['rose', 1]]);
        $this->payAndConfirm($order);

        $this->getJson('/admin/dashboard/metrics')->assertJsonPath('revenue_total', 300000)->assertJsonPath('active_orders', 1);
        $this->get('/admin/dashboard')->assertOk()->assertSee('Rp 300.000')->assertSee($order->order_number);
    }

    /** TC-FR12-02 | Laporan penjualan per periode + produk terlaris + ekspor CSV. */
    public function test_TC_FR12_02_sales_and_best_seller_reports(): void
    {
        $order = $this->placeOrder([['rose', 2], ['lily', 1]]);
        $this->payAndConfirm($order);

        $this->actingAs($this->admin)->get('/admin/reports/sales?preset=7d')->assertOk()->assertSee($order->order_number)->assertSee('Rp 750.000');
        $this->get('/admin/reports/products?preset=7d')->assertSeeInOrder(['Sweet Blush Romance', 'Graceful Pure White']);
        $this->assertStringContainsString($order->order_number, $this->get('/admin/reports/sales?preset=7d&export=csv')->streamedContent());
    }
}
