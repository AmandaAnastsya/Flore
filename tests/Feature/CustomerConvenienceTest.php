<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Bouquet;
use App\Models\Category;
use App\Models\ContactMessage;
use App\Models\Flower;
use App\Models\Order;
use App\Models\User;
use App\Notifications\ContactReceived;
use App\Notifications\OrderUpdate;
use App\Services\OrderService;
use App\Services\OrderWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Batal otomatis pesanan belum dibayar, Buku Alamat, Contact Us, Re-Order. */
class CustomerConvenienceTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;
    private Bouquet $bouquet;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Storage::fake('local');
        $this->customer = User::factory()->create(['name' => 'Clarissa Valery', 'phone' => '081234567890']);
        $cat = Category::create(['name' => 'Hand Bouquet', 'slug' => 'hand-bouquet']);
        $this->bouquet = Bouquet::unguarded(fn () => Bouquet::create([
            'category_id' => $cat->id, 'name' => 'Sweet Blush', 'slug' => 'sweet-blush', 'price' => 450000, 'stock' => 10, 'is_active' => true, 'is_same_day' => true,
        ]));
    }

    private function place(array $bouquets = null, array $stems = []): Order
    {
        return app(OrderService::class)->place($this->customer, [
            'recipient_name' => 'Julian Santoso', 'recipient_phone' => '081298765432', 'delivery_city' => 'Jakarta Pusat',
            'delivery_address' => 'Plaza Indonesia Lt. 18', 'delivery_date' => today()->addDay()->toDateString(), 'delivery_slot' => 'afternoon',
            'occasion' => 'anniversary', 'card_message' => 'Happy anniversary!', 'sender_name' => 'Clarissa',
        ], $bouquets ?? [$this->bouquet->id => 2], $stems);
    }

    // ================================================================ 1. Batal otomatis

    public function test_unpaid_order_is_cancelled_after_timeout_and_stock_returned(): void
    {
        config(['flore.payment.timeout_hours' => 24]);
        $order = $this->place();
        $this->assertSame(8, $this->bouquet->fresh()->stock);
        $this->assertTrue($order->paymentDeadline()->equalTo($order->created_at->copy()->addHours(24)));

        $this->actingAs($this->customer)->get("/orders/{$order->order_number}")->assertSee('Bayar sebelum')->assertSee('dibatalkan otomatis');

        $this->travel(23)->hours();
        $this->artisan('orders:cancel-unpaid')->assertSuccessful();
        $this->assertSame('pending_payment', $order->fresh()->status);

        $this->travel(2)->hours();
        $this->artisan('orders:cancel-unpaid --dry-run')->expectsOutputToContain($order->order_number)->assertSuccessful();
        $this->assertSame('pending_payment', $order->fresh()->status);   // dry-run tidak membatalkan

        $this->artisan('orders:cancel-unpaid')->expectsOutputToContain('1 pesanan')->assertSuccessful();
        $order->refresh();
        $this->assertSame('cancelled', $order->status);
        $this->assertSame('Dibatalkan otomatis karena belum dibayar dalam 24 jam.', $order->cancel_reason);
        $this->assertSame(10, $this->bouquet->fresh()->stock);   // stok kembali

        Notification::assertSentTo($this->customer, OrderUpdate::class, fn ($n) => $n->event === 'cancelled'
            && str_contains((string) $n->toMail($this->customer)->render(), 'belum dibayar dalam 24 jam'));
        $this->get("/orders/{$order->order_number}")->assertSee('Dibatalkan otomatis karena belum dibayar');

        // dijalankan lagi: tidak dobel mengembalikan stok
        $this->artisan('orders:cancel-unpaid')->assertSuccessful();
        $this->assertSame(10, $this->bouquet->fresh()->stock);
    }

    public function test_orders_awaiting_verification_or_paid_are_never_auto_cancelled(): void
    {
        $proof = $this->place();
        app(OrderWorkflow::class)->uploadProof($proof, UploadedFile::fake()->image('b.jpg'));
        $paid = $this->place([$this->bouquet->id => 1]);
        app(OrderWorkflow::class)->uploadProof($paid, UploadedFile::fake()->image('b.jpg'));
        app(OrderWorkflow::class)->confirmPayment($paid);

        $this->travel(3)->days();
        $this->artisan('orders:cancel-unpaid')->assertSuccessful();

        $this->assertSame('pending_payment', $proof->fresh()->status);
        $this->assertSame('payment_confirmed', $paid->fresh()->status);
        $this->assertNull($proof->fresh()->paymentDeadline());
    }

    public function test_rejected_proof_gets_a_fresh_deadline_and_schedule_is_registered(): void
    {
        config(['flore.payment.timeout_hours' => 12]);
        $order = $this->place();
        app(OrderWorkflow::class)->uploadProof($order, UploadedFile::fake()->image('b.jpg'));

        $this->travel(10)->hours();
        app(OrderWorkflow::class)->rejectPayment($order);   // waktu 12 jam dihitung ulang sejak penolakan
        $this->travel(5)->hours();
        $this->artisan('orders:cancel-unpaid');
        $this->assertSame('pending_payment', $order->fresh()->status);

        $this->travel(8)->hours();
        $this->artisan('orders:cancel-unpaid');
        $this->assertSame('cancelled', $order->fresh()->status);

        $events = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())->map->command->implode(' ');
        $this->assertStringContainsString('orders:cancel-unpaid', $events);
    }

    // ================================================================ 2. Buku Alamat

    public function test_address_book_crud_default_and_ownership(): void
    {
        $this->actingAs($this->customer)->get('/account/addresses')->assertOk()->assertSee('Buku Alamat')->assertSee('Belum ada alamat tersimpan');

        $payload = ['label' => 'Rumah Orang Tua', 'recipient_name' => 'Ibu Ratna', 'recipient_phone' => '0811-9876-5432', 'city' => 'Jakarta Selatan', 'address' => 'Jl. Wijaya II No. 45, Kebayoran Baru'];
        $this->post('/account/addresses', array_merge($payload, ['address' => 'pendek']))->assertSessionHasErrors('address');
        $this->post('/account/addresses', $payload)->assertRedirect('/account/addresses');

        $home = Address::firstOrFail();
        $this->assertTrue($home->is_default);                 // alamat pertama otomatis utama
        $this->assertSame('081198765432', $home->recipient_phone);

        $this->post('/account/addresses', array_merge($payload, ['label' => 'Kantor', 'recipient_name' => 'Julian', 'is_default' => 1]));
        $office = Address::where('label', 'Kantor')->firstOrFail();
        $this->assertTrue($office->is_default);
        $this->assertFalse($home->fresh()->is_default);

        $this->put("/account/addresses/{$home->id}", array_merge($payload, ['label' => 'Rumah Ibu']))->assertRedirect();
        $this->assertSame('Rumah Ibu', $home->fresh()->label);
        $this->post("/account/addresses/{$home->id}/default")->assertSessionHas('success');
        $this->assertTrue($home->fresh()->is_default);

        // menghapus alamat utama → alamat lain menjadi utama
        $this->delete("/account/addresses/{$home->id}");
        $this->assertTrue($office->fresh()->is_default);

        // alamat milik orang lain tidak bisa diakses
        $this->actingAs(User::factory()->create())->get("/account/addresses/{$office->id}/edit")->assertNotFound();
        $this->delete("/account/addresses/{$office->id}")->assertNotFound();
        $this->assertNotNull($office->fresh());
    }

    public function test_checkout_uses_default_address_picker_and_can_save_new_one(): void
    {
        $this->customer->addresses()->create(['label' => 'Rumah', 'recipient_name' => 'Ibu Ratna', 'recipient_phone' => '081198765432',
            'city' => 'Jakarta Selatan', 'address' => 'Jl. Wijaya II No. 45', 'is_default' => true]);
        $this->actingAs($this->customer)->post("/cart/items/{$this->bouquet->slug}", ['quantity' => 1]);

        $this->get('/checkout')->assertOk()
            ->assertSee('value="Ibu Ratna"', false)                    // alamat utama langsung terisi
            ->assertSee('Pilih alamat tersimpan')->assertSee('Rumah (utama)');

        $form = [
            'recipient_name' => 'Rafi Ananda', 'recipient_phone' => '081277000011', 'delivery_city' => 'Jakarta Barat',
            'delivery_address' => 'Jl. Kebon Jeruk Raya No. 7', 'delivery_date' => today()->addDay()->toDateString(), 'delivery_slot' => 'afternoon',
            'save_address' => 1, 'address_label' => 'Kos Rafi',
        ];
        $this->post('/checkout', $form)->assertRedirect('/checkout/review');
        $this->post('/checkout', $form);   // dikirim lagi → tidak dobel

        $this->assertSame(1, Address::where('label', 'Kos Rafi')->count());
        $this->assertArrayNotHasKey('save_address', session('checkout'));
        $this->post('/checkout/place')->assertRedirect();
        $this->assertSame('Rafi Ananda', Order::latest('id')->value('recipient_name'));
    }

    // ================================================================ 3. Contact Us

    public function test_guest_sends_contact_message_and_admin_handles_it(): void
    {
        config(['flore.store.email' => 'cs@flore.test']);

        $this->get('/contact')->assertOk()->assertSee('Contact Us');
        $this->post('/contact', ['name' => '', 'email' => 'bukan-email', 'topic' => 'x', 'order_number' => 'ABC', 'message' => 'hai'])
            ->assertSessionHasErrors(['name', 'email', 'topic', 'order_number', 'message']);

        $this->post('/contact', [
            'name' => 'Nadia', 'email' => 'nadia@example.com', 'phone' => '0812 7700 0012', 'topic' => 'custom',
            'message' => 'Bisa buat bouquet tulip ungu untuk wisuda tanggal 20?',
        ])->assertRedirect('/contact')->assertSessionHas('success');

        $msg = ContactMessage::firstOrFail();
        $this->assertSame(['new', '081277000012', null], [$msg->status, $msg->phone, $msg->user_id]);
        Notification::assertSentOnDemand(ContactReceived::class, fn ($n, $c, $notifiable) => $notifiable->routes['mail'] === 'nadia@example.com' && ! $n->forStore);
        Notification::assertSentOnDemand(ContactReceived::class, fn ($n, $c, $notifiable) => $notifiable->routes['mail'] === 'cs@flore.test' && $n->forStore);

        // jebakan bot: tidak tersimpan
        $this->post('/contact', ['name' => 'Bot', 'email' => 'bot@spam.test', 'topic' => 'other', 'message' => 'Buy cheap followers now!!!', 'website' => 'http://spam'])
            ->assertSessionHas('success');
        $this->assertSame(1, ContactMessage::count());

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get('/admin/dashboard')->assertSee('Pesan Masuk');
        $this->get('/admin/messages')->assertOk()->assertSee('Nadia')->assertSee('tulip ungu')->assertSee('mailto:nadia@example.com', false)->assertSee('wa.me/6281277000012', false);
        $this->patch("/admin/messages/{$msg->id}", ['status' => 'replied', 'admin_note' => 'Sudah dibalas, kirim katalog tulip'])->assertSessionHas('success');
        $this->assertSame('replied', $msg->fresh()->status);
        $this->assertNotNull($msg->fresh()->handled_at);

        $this->actingAs($this->customer)->get('/admin/messages')->assertForbidden();
    }

    public function test_logged_in_customer_contact_links_own_order(): void
    {
        $order = $this->place();
        $this->actingAs($this->customer)->get("/contact?order={$order->order_number}")
            ->assertSee('value="Clarissa Valery"', false)->assertSee('value="' . $order->order_number . '"', false);
        $this->get("/orders/{$order->order_number}")->assertSee(route('contact.show', ['order' => $order->order_number]), false);

        $this->post('/contact', ['name' => 'Clarissa', 'email' => $this->customer->email, 'topic' => 'order', 'order_number' => strtolower($order->order_number),
            'message' => 'Bisakah alamat pengiriman diganti?']);
        $msg = ContactMessage::firstOrFail();
        $this->assertSame($this->customer->id, $msg->user_id);
        $this->assertSame($order->id, $msg->order()?->id);
    }

    // ================================================================ 4. Re-Order

    public function test_reorder_refills_cart_and_checkout_details(): void
    {
        $flower = Flower::create(['name' => 'Rose Merah', 'slug' => 'rose-merah', 'species' => 'rose', 'price_per_stem' => 8000, 'stock' => 100, 'reorder_level' => 30, 'is_active' => true]);
        $order = $this->place([$this->bouquet->id => 2], [$flower->id => 10]);
        $order->update(['status' => 'delivered', 'payment_status' => 'confirmed']);

        $this->actingAs($this->customer)->get('/orders')->assertSee('Pesan Lagi');
        $this->post("/orders/{$order->order_number}/reorder")->assertRedirect('/cart')
            ->assertSessionHas('success', 'Ditambahkan ke keranjang: 2× Sweet Blush, 10 tangkai Rose Merah.');

        $this->get('/checkout')->assertSee('value="Julian Santoso"', false)->assertSee('Happy anniversary!');
    }

    public function test_reorder_respects_current_stock_and_availability(): void
    {
        $order = $this->place([$this->bouquet->id => 5]);
        $order->update(['status' => 'delivered']);

        $this->bouquet->update(['stock' => 3]);
        $this->actingAs($this->customer)->post("/orders/{$order->order_number}/reorder")
            ->assertSessionHas('success', fn ($m) => str_contains($m, '3× Sweet Blush') && str_contains($m, 'hanya tersedia 3'));

        $this->bouquet->update(['is_active' => false]);
        $this->post('/cart', [])->assertStatus(405);   // sanity
        session()->forget('cart');
        $this->post("/orders/{$order->order_number}/reorder")->assertSessionHas('error');

        $this->actingAs(User::factory()->create())->post("/orders/{$order->order_number}/reorder")->assertNotFound();
    }
}
