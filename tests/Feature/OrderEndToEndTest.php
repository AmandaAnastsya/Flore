<?php

namespace Tests\Feature;

use App\Models\Bouquet;
use App\Models\Category;
use App\Models\Order;
use App\Models\User;
use Database\Seeders\RecommendationRuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Review Minggu 3 — pengujian end-to-end alur pemesanan lewat HTTP, seperti pengguna sungguhan:
 * Landing/Wizard → Katalog → Keranjang → Checkout → Tinjau → Pesan → Upload bukti
 * → Admin verifikasi → Diproses → Dikirim → Terkirim.
 */
class OrderEndToEndTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;
    private User $admin;
    private Bouquet $blush;    // cocok sempurna dengan jawaban wizard
    private Bouquet $white;    // dibeli dari katalog
    private Bouquet $wedding;  // tidak cocok

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(RecommendationRuleSeeder::class);

        $cat = Category::create(['name' => 'Hand Bouquet', 'slug' => 'hand-bouquet', 'is_active' => true]);
        $make = fn (string $name, array $attrs) => Bouquet::unguarded(fn () => Bouquet::create(array_merge([
            'category_id' => $cat->id, 'name' => $name, 'slug' => str($name)->slug(),
            'stock' => 5, 'low_stock_threshold' => 1, 'is_active' => true, 'is_same_day' => false,
            'occasion' => 'anniversary', 'color' => 'pink', 'style' => 'soft-romantic', 'size' => 'medium',
        ], $attrs)));

        $this->blush = $make('Sweet Blush Romance', ['price' => 300000]);
        $this->white = $make('Graceful Pure White', ['price' => 150000, 'color' => 'white', 'stock' => 3]);
        $this->wedding = $make('Velvet Wedding Red', ['price' => 320000, 'occasion' => 'wedding', 'color' => 'red', 'style' => 'luxury', 'size' => 'large']);

        $this->customer = User::factory()->create(['role' => 'customer', 'name' => 'Clarissa Valery']);
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    private function checkoutData(array $override = []): array
    {
        return array_merge([
            'recipient_name' => 'Clarissa Valery',
            'recipient_phone' => '0812-3456-7890',
            'delivery_city' => 'Jakarta Selatan',
            'delivery_address' => 'Jl. Gunawarman No. 42, Kebayoran Baru',
            'delivery_date' => now()->addDays(2)->toDateString(),
            'delivery_slot' => 'afternoon',
            'occasion' => 'anniversary',
            'card_message' => 'Happy 3rd Anniversary, my love!',
            'sender_name' => 'Julian',
        ], $override);
    }

    /** Customer: wizard + katalog sampai pesanan terbentuk. Mengembalikan order yang dibuat. */
    private function customerPlacesOrder(): Order
    {
        $this->actingAs($this->customer);

        // 1. Home menampilkan Smart Wizard
        $this->get('/home')->assertOk()->assertSee('id="wizardModal"', false);

        // 2. Wizard merekomendasikan bouquet yang paling cocok
        $rec = $this->getJson(route('wizard.recommend', [
            'occasion' => 'anniversary', 'color' => 'pink', 'style' => 'soft-romantic',
            'size' => 'medium', 'budget' => '200-350',
        ]))->assertOk()->assertJsonPath('mode', 'exact');

        $top = $rec->json('items.0');
        $this->assertSame('Sweet Blush Romance', $top['name']);
        $this->assertTrue($top['is_perfect']);
        $this->assertNotContains('Velvet Wedding Red', array_column($rec->json('items'), 'name'));

        // 3. "Tambah ke Keranjang" dari kartu hasil wizard
        $this->post($top['cart_url'], ['quantity' => 1])->assertSessionHas('success');

        // 4. Tambah bouquet lain dari katalog
        $this->get('/catalog')->assertOk()->assertSee('Graceful Pure White');
        $this->get('/catalog/graceful-pure-white')->assertOk();
        $this->post(route('cart.store', $this->white), ['quantity' => 2])->assertSessionHas('success');

        // 5. Keranjang
        $this->get('/cart')->assertOk()
            ->assertSee('Sweet Blush Romance')->assertSee('Graceful Pure White')
            ->assertSee('Rp 600.000');

        // 6. Checkout → Tinjau
        $this->get('/checkout')->assertOk()->assertSee('Data Penerima');
        $this->post('/checkout', $this->checkoutData())->assertRedirect(route('checkout.review'));
        $this->get('/checkout/review')->assertOk()
            ->assertSee('Clarissa Valery')->assertSee('Jl. Gunawarman No. 42')
            ->assertSee('Happy 3rd Anniversary, my love!')->assertSee('Rp 600.000');

        // 7. Buat pesanan
        $response = $this->post('/checkout/place');
        $order = Order::where('user_id', $this->customer->id)->latest('id')->firstOrFail();
        $response->assertRedirect(route('orders.show', $order->order_number));

        return $order;
    }

    public function test_full_order_flow_from_wizard_to_delivered(): void
    {
        $order = $this->customerPlacesOrder();

        // Pesanan tersimpan benar, stok berkurang, keranjang kosong
        $this->assertSame('pending_payment', $order->status);
        $this->assertSame('unpaid', $order->payment_status);
        $this->assertEquals(600000, $order->total_amount);
        $this->assertSame('081234567890', $order->recipient_phone);
        $this->assertSame('anniversary', $order->occasion);
        $this->assertCount(2, $order->items);
        $this->assertSame(4, $this->blush->fresh()->stock);
        $this->assertSame(1, $this->white->fresh()->stock);
        $this->assertSame(5, $this->wedding->fresh()->stock);
        $this->get('/cart')->assertSee('Keranjangmu masih kosong');

        // 8. Halaman pesanan + upload bukti transfer
        $this->get(route('orders.show', $order->order_number))->assertOk()
            ->assertSee('Menunggu pembayaran')->assertSee('Kirim bukti pembayaran')->assertSee('Rp 600.000');

        $this->post(route('orders.payment', $order->order_number), [
            'payment_proof' => UploadedFile::fake()->create('bukti-transfer.pdf', 120, 'application/pdf'),
        ])->assertSessionHasNoErrors();

        $order->refresh();
        $this->assertSame('proof_uploaded', $order->payment_status);
        Storage::disk('local')->assertExists($order->payment_proof);
        $this->get(route('orders.show', $order->order_number))->assertSee('Menunggu verifikasi admin');

        // 9. Admin: dashboard & daftar pesanan menampilkan pesanan yang perlu diverifikasi
        $this->actingAs($this->admin);
        $this->get('/admin/dashboard')->assertOk()
            ->assertSee($order->order_number)->assertSee('1 pembayaran perlu diverifikasi');
        $this->get('/admin/orders?payment_status=proof_uploaded')->assertOk()->assertSee($order->order_number);
        $this->get(route('admin.orders.show', $order))->assertOk()
            ->assertSee('Konfirmasi pembayaran')->assertSee('Tolak bukti');
        $this->get(route('orders.proof', $order->order_number))->assertOk();   // admin bisa membuka bukti transfer
        $this->actingAs(User::factory()->create(['role' => 'customer']))
            ->get(route('orders.proof', $order->order_number))->assertNotFound();   // customer lain tidak
        $this->actingAs($this->admin);

        // 10. Admin verifikasi pembayaran
        $this->post(route('admin.orders.confirm', $order))->assertSessionHasNoErrors();
        $order->refresh();
        $this->assertSame('payment_confirmed', $order->status);
        $this->assertSame('confirmed', $order->payment_status);
        $this->assertNotNull($order->paid_at);

        // 11. Admin memajukan status sampai terkirim
        foreach (['preparing', 'out_for_delivery', 'delivered'] as $expected) {
            $this->post(route('admin.orders.advance', $order));
            $this->assertSame($expected, $order->fresh()->status);
        }

        // Tidak bisa dimajukan lagi
        $this->post(route('admin.orders.advance', $order))->assertSessionHas('error');
        $this->assertSame('delivered', $order->fresh()->status);

        // Pendapatan masuk dashboard
        $this->get('/admin/dashboard')->assertSee('Rp 600.000')->assertDontSee('pembayaran perlu diverifikasi');

        // 12. Customer melihat pesanan terkirim & tidak bisa membatalkan
        $this->actingAs($this->customer);
        $this->get(route('orders.show', $order->order_number))->assertOk()->assertSee('Terkirim')->assertSee('Lunas');
        $this->post(route('orders.cancel', $order->order_number))->assertSessionHas('error');
        $this->assertSame('delivered', $order->fresh()->status);
        $this->get('/home')->assertSee($order->order_number);
    }

    public function test_rejected_proof_can_be_reuploaded_then_confirmed(): void
    {
        $order = $this->customerPlacesOrder();

        $this->post(route('orders.payment', $order->order_number), [
            'payment_proof' => UploadedFile::fake()->create('salah.pdf', 50, 'application/pdf'),
        ]);
        $firstProof = $order->fresh()->payment_proof;

        $this->actingAs($this->admin)->post(route('admin.orders.reject', $order));
        $this->assertSame('rejected', $order->fresh()->payment_status);
        $this->assertSame('pending_payment', $order->fresh()->status);

        $this->actingAs($this->customer)->get(route('orders.show', $order->order_number))
            ->assertSee('Bukti sebelumnya ditolak')->assertSee('Kirim bukti pembayaran');

        $this->post(route('orders.payment', $order->order_number), [
            'payment_proof' => UploadedFile::fake()->create('benar.pdf', 50, 'application/pdf'),
        ]);
        $order->refresh();
        $this->assertSame('proof_uploaded', $order->payment_status);
        Storage::disk('local')->assertMissing($firstProof);   // file lama dibersihkan

        $this->actingAs($this->admin)->post(route('admin.orders.confirm', $order));
        $this->assertSame('payment_confirmed', $order->fresh()->status);
    }

    public function test_customer_cancel_returns_stock(): void
    {
        $order = $this->customerPlacesOrder();
        $this->assertSame(1, $this->white->fresh()->stock);

        $this->post(route('orders.cancel', $order->order_number));

        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame(5, $this->blush->fresh()->stock);
        $this->assertSame(3, $this->white->fresh()->stock);
        $this->get(route('orders.show', $order->order_number))->assertSee('Pesanan dibatalkan');
    }

    public function test_admin_cannot_confirm_unpaid_order_and_customer_cannot_access_admin(): void
    {
        $order = $this->customerPlacesOrder();

        // customer tidak boleh memakai aksi admin
        $this->post(route('admin.orders.confirm', $order))->assertForbidden();

        // admin tidak bisa konfirmasi sebelum ada bukti
        $this->actingAs($this->admin)->post(route('admin.orders.confirm', $order))->assertSessionHas('error');
        $this->assertSame('unpaid', $order->fresh()->payment_status);
    }

    public function test_second_customer_cannot_overbuy_the_last_stock(): void
    {
        $this->white->update(['stock' => 2]);
        $other = User::factory()->create(['role' => 'customer']);

        // pelanggan lain (browser/sesi sendiri) sudah memasukkan 2 ke keranjang & sedang di halaman tinjau
        $this->actingAs($other);
        $this->post(route('cart.store', $this->white), ['quantity' => 2])->assertSessionHas('success');
        $this->post('/checkout', $this->checkoutData())->assertRedirect(route('checkout.review'));
        $otherSession = session()->except(['_token', '_flash', '_previous']);
        $this->flushSession();

        // customer utama (sesi lain) membeli lebih dulu
        $this->customerPlacesOrder();
        $this->assertSame(0, $this->white->fresh()->stock);

        // pelanggan lain kembali ke sesinya & menekan "Buat Pesanan": gagal dengan pesan jelas
        $this->flushSession();
        $this->actingAs($other)->withSession($otherSession)
            ->post('/checkout/place')->assertRedirect(route('cart.index'))->assertSessionHas('error');
        $this->assertSame(0, Order::where('user_id', $other->id)->count());
        $this->assertSame(0, $this->white->fresh()->stock);
    }
}
