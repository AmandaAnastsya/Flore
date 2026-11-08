<?php

namespace Tests\Feature;

use App\Models\Bouquet;
use App\Models\Category;
use App\Models\Flower;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PaymentAndTrackingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function customer(): User
    {
        return User::factory()->create();
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function bouquet(string $name = 'Rose Love', int $stock = 8): Bouquet
{
    $cat = Category::firstOrCreate(['slug' => 'hand-bouquet'], ['name' => 'Hand Bouquet']);

    return Bouquet::unguarded(fn () => Bouquet::firstOrCreate(
        ['slug' => str($name)->slug()],
        [
            'category_id' => $cat->id, 'name' => $name, 'price' => 100000,
            'stock' => $stock, 'is_same_day' => false, 'is_active' => true,
        ]
    ));
}

    private function flower(int $stock = 90): Flower
    {
        return Flower::create([
            'name' => 'Rose Merah', 'slug' => 'rose-merah', 'species' => 'rose', 'price_per_stem' => 8000,
            'stock' => $stock, 'is_active' => true,
        ]);
    }

    /** Order yang stoknya "sudah dikurangi": bouquet x2 (+ opsional bunga x10). */
    private function order(User $user, array $attrs = [], ?Bouquet $b = null, ?Flower $f = null): Order
    {
        $order = Order::create(array_merge([
            'user_id' => $user->id, 'recipient_name' => 'Budi Santoso', 'recipient_phone' => '081234567890',
            'delivery_city' => 'Bandung', 'delivery_address' => 'Jl. Merdeka No. 10', 'delivery_slot' => 'morning',
            'delivery_date' => now()->addDays(2)->toDateString(),
            'subtotal' => 200000, 'total_amount' => 200000,
            'status' => 'pending_payment', 'payment_status' => 'unpaid',
        ], $attrs));

        $b ??= $this->bouquet();
        $order->items()->create([
            'bouquet_id' => $b->id, 'bouquet_name' => $b->name, 'price' => 100000, 'quantity' => 2, 'subtotal' => 200000,
        ]);

        if ($f) {
            $order->items()->create([
                'flower_id' => $f->id, 'flower_name' => $f->name, 'price' => 8000, 'quantity' => 10, 'subtotal' => 80000,
            ]);
        }

        return $order;
    }

    private function proofFile(string $name = 'bukti.jpg', int $kb = 200, string $mime = 'image/jpeg'): UploadedFile
    {
        return UploadedFile::fake()->create($name, $kb, $mime);
    }

    // ----------------------------------------------- riwayat pesanan customer

    public function test_order_history_lists_only_own_orders_with_status(): void
    {
        $me = $this->customer();
        $other = $this->customer();
        $mine = $this->order($me);
        $theirs = $this->order($other);

        $this->actingAs($me)->get('/orders')->assertOk()
            ->assertSee($mine->order_number)->assertSee('Menunggu pembayaran')
            ->assertDontSee($theirs->order_number);
    }

    public function test_order_history_filter_empty_state_and_login(): void
    {
        $me = $this->customer();
        $a = $this->order($me);
        $b = $this->order($me, ['status' => 'delivered', 'payment_status' => 'confirmed']);

        $this->actingAs($me)->get('/orders?status=delivered')
            ->assertSee($b->order_number)->assertDontSee($a->order_number);
        $this->get('/orders?status=ngawur')->assertOk()->assertSee($a->order_number);   // filter tak valid diabaikan

        $this->actingAs($this->customer())->get('/orders')->assertSee('Belum ada pesanan');
    }

    public function test_guest_is_sent_to_login_for_order_pages(): void
    {
        $order = $this->order($this->customer());

        $this->get('/orders')->assertRedirect(route('login'));
        $this->get("/orders/{$order->order_number}")->assertRedirect(route('login'));
        $this->post("/orders/{$order->order_number}/payment")->assertRedirect(route('login'));
        $this->get("/orders/{$order->order_number}/proof")->assertRedirect(route('login'));
    }

    public function test_navbar_shows_my_orders_only_for_logged_in_users(): void
    {
        $this->get('/catalog')->assertDontSee('Pesanan Saya');
        $this->actingAs($this->customer())->get('/catalog')->assertSee('Pesanan Saya');
    }

    // ------------------------------------------------------- unggah bukti

    public function test_customer_uploads_proof_and_waits_for_verification(): void
    {
        $me = $this->customer();
        $order = $this->order($me);

        $this->actingAs($me)->get("/orders/{$order->order_number}")
            ->assertSee('Kirim bukti pembayaran')->assertSee('Rp 200.000');

        $this->post("/orders/{$order->order_number}/payment", ['payment_proof' => $this->proofFile()])
            ->assertSessionHasNoErrors()->assertSessionHas('success');

        $order->refresh();
        $this->assertSame('proof_uploaded', $order->payment_status);
        $this->assertSame('pending_payment', $order->status);
        Storage::disk('local')->assertExists($order->payment_proof);
        $this->assertStringStartsWith('payment-proofs/', $order->payment_proof);

        $this->get("/orders/{$order->order_number}")
            ->assertSee('Menunggu verifikasi admin')->assertDontSee('Kirim bukti pembayaran');
    }

    public function test_proof_validation_rejects_bad_files(): void
    {
        $me = $this->customer();
        $order = $this->order($me);
        $this->actingAs($me);

        $bad = [
            [],                                                               // tanpa file
            ['payment_proof' => $this->proofFile('bukti.txt', 10, 'text/plain')],
            ['payment_proof' => $this->proofFile('bukti.svg', 10, 'image/svg+xml')],
            ['payment_proof' => $this->proofFile('bukti.php', 10, 'application/x-php')],
            ['payment_proof' => $this->proofFile('besar.jpg', 4000, 'image/jpeg')],   // > 3 MB
        ];

        foreach ($bad as $payload) {
            $this->post("/orders/{$order->order_number}/payment", $payload)->assertSessionHasErrors('payment_proof');
        }

        $this->assertSame('unpaid', $order->fresh()->payment_status);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_pdf_and_png_are_accepted(): void
    {
        $me = $this->customer();
        $a = $this->order($me);
        $b = $this->order($me);
        $this->actingAs($me);

        $this->post("/orders/{$a->order_number}/payment", ['payment_proof' => $this->proofFile('b.pdf', 100, 'application/pdf')])
            ->assertSessionHasNoErrors();
        $this->post("/orders/{$b->order_number}/payment", ['payment_proof' => $this->proofFile('b.png', 100, 'image/png')])
            ->assertSessionHasNoErrors();
    }

    public function test_reupload_after_rejection_replaces_old_file(): void
    {
        $me = $this->customer();
        Storage::disk('local')->put('payment-proofs/lama.jpg', 'x');
        $order = $this->order($me, ['payment_status' => 'rejected', 'payment_proof' => 'payment-proofs/lama.jpg']);

        $this->actingAs($me)->get("/orders/{$order->order_number}")->assertSee('Bukti sebelumnya ditolak');

        $this->post("/orders/{$order->order_number}/payment", ['payment_proof' => $this->proofFile()])
            ->assertSessionHasNoErrors();

        $order->refresh();
        $this->assertSame('proof_uploaded', $order->payment_status);
        $this->assertNotSame('payment-proofs/lama.jpg', $order->payment_proof);
        Storage::disk('local')->assertMissing('payment-proofs/lama.jpg');
        Storage::disk('local')->assertExists($order->payment_proof);
    }

    public function test_cannot_upload_when_not_allowed_or_for_someone_elses_order(): void
    {
        $me = $this->customer();
        $this->actingAs($me);

        foreach ([
            ['payment_status' => 'proof_uploaded'],
            ['status' => 'payment_confirmed', 'payment_status' => 'confirmed'],
            ['status' => 'cancelled'],
        ] as $state) {
            $order = $this->order($me, $state);
            $this->post("/orders/{$order->order_number}/payment", ['payment_proof' => $this->proofFile()])
                ->assertSessionHas('error');
            $this->assertSame($state['payment_status'] ?? 'unpaid', $order->fresh()->payment_status);
        }

        $theirs = $this->order($this->customer());
        $this->post("/orders/{$theirs->order_number}/payment", ['payment_proof' => $this->proofFile()])->assertNotFound();

        $this->assertSame([], Storage::disk('local')->allFiles());   // tidak ada file yatim
    }

    public function test_proof_file_is_visible_to_owner_and_admin_only(): void
    {
        $me = $this->customer();
        $order = $this->order($me);
        $this->actingAs($me)->post("/orders/{$order->order_number}/payment", ['payment_proof' => $this->proofFile()]);
        $url = "/orders/{$order->order_number}/proof";

        $this->get($url)->assertOk();
        $this->actingAs($this->admin())->get($url)->assertOk();
        $this->actingAs($this->customer())->get($url)->assertNotFound();

        $noProof = $this->order($me);
        $this->actingAs($me)->get("/orders/{$noProof->order_number}/proof")->assertNotFound();
    }

    // ---------------------------------------------------------- pembatalan

    public function test_customer_cancel_restocks_bouquet_and_stems_once(): void
    {
        $me = $this->customer();
        $b = $this->bouquet('Rose Love', 8);
        $f = $this->flower(90);
        $order = $this->order($me, [], $b, $f);

        $this->actingAs($me)->post("/orders/{$order->order_number}/cancel")->assertSessionHas('success');

        $order->refresh();
        $this->assertSame('cancelled', $order->status);
        $this->assertNotNull($order->cancelled_at);
        $this->assertSame(10, $b->fresh()->stock);    // 8 + 2
        $this->assertSame(100, $f->fresh()->stock);   // 90 + 10

        $this->post("/orders/{$order->order_number}/cancel")->assertSessionHas('error');   // tidak dobel
        $this->assertSame(10, $b->fresh()->stock);
        $this->assertSame(100, $f->fresh()->stock);

        $this->get("/orders/{$order->order_number}")->assertSee('Pesanan dibatalkan');
    }

    public function test_customer_cannot_cancel_after_proof_or_for_others(): void
    {
        $me = $this->customer();
        $b = $this->bouquet('Rose Love', 8);
        $waiting = $this->order($me, ['payment_status' => 'proof_uploaded'], $b);

        $this->actingAs($me)->post("/orders/{$waiting->order_number}/cancel")->assertSessionHas('error');
        $this->assertSame('pending_payment', $waiting->fresh()->status);
        $this->assertSame(8, $b->fresh()->stock);

        $theirs = $this->order($this->customer(), [], $b);
        $this->post("/orders/{$theirs->order_number}/cancel")->assertNotFound();
    }

    public function test_cancel_restocks_even_if_bouquet_was_soft_deleted(): void
    {
        $me = $this->customer();
        $b = $this->bouquet('Rose Love', 8);
        $order = $this->order($me, [], $b);
        $b->delete();

        $this->actingAs($me)->post("/orders/{$order->order_number}/cancel");

        $this->assertSame(10, Bouquet::withTrashed()->find($b->id)->stock);
    }

    // --------------------------------------------------------- admin akses

    public function test_admin_order_pages_require_admin(): void
    {
        $order = $this->order($this->customer());

        $this->get('/admin/orders')->assertRedirect();   // tamu dialihkan

        // customer biasa tidak boleh masuk (kode status mengikuti RoleMiddleware-mu)
        $response = $this->actingAs($this->customer())->get('/admin/orders');
        $this->assertNotSame(200, $response->getStatusCode());
        $response->assertDontSee($order->order_number);

        $response = $this->post("/admin/orders/{$order->id}/confirm");
        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertSame('unpaid', $order->fresh()->payment_status);
    }

    public function test_admin_lists_filters_and_searches_orders(): void
    {
        $waiting = $this->order($this->customer(), ['payment_status' => 'proof_uploaded', 'recipient_name' => 'Siti Aminah']);
        $plain = $this->order($this->customer(), ['recipient_name' => 'Joko Widodo']);

        $this->actingAs($this->admin());

        $this->get('/admin/orders')->assertOk()->assertSee($waiting->order_number)->assertSee($plain->order_number);
        $this->get('/admin/orders?payment_status=proof_uploaded')
            ->assertSee($waiting->order_number)->assertDontSee($plain->order_number);
        $this->get('/admin/orders?q=Joko')->assertSee($plain->order_number)->assertDontSee($waiting->order_number);
        $this->get("/admin/orders?q={$waiting->order_number}")->assertSee($waiting->order_number)->assertDontSee($plain->order_number);
        $this->get('/admin/orders?q=tidakada')->assertSee('Tidak ada pesanan yang cocok');
    }

    // ---------------------------------------------------- verifikasi admin

    public function test_admin_confirms_payment(): void
    {
        $order = $this->order($this->customer(), ['payment_status' => 'proof_uploaded', 'payment_proof' => 'payment-proofs/a.jpg']);

        $this->actingAs($this->admin())->get("/admin/orders/{$order->id}")
            ->assertOk()->assertSee('Konfirmasi pembayaran')->assertSee('Tolak bukti');

        $this->post("/admin/orders/{$order->id}/confirm")->assertSessionHas('success');

        $order->refresh();
        $this->assertSame('payment_confirmed', $order->status);
        $this->assertSame('confirmed', $order->payment_status);
        $this->assertNotNull($order->paid_at);
    }

    public function test_admin_rejects_payment_and_customer_can_retry(): void
    {
        $me = $this->customer();
        $order = $this->order($me, ['payment_status' => 'proof_uploaded', 'payment_proof' => 'payment-proofs/a.jpg']);

        $this->actingAs($this->admin())->post("/admin/orders/{$order->id}/reject")->assertSessionHas('success');

        $order->refresh();
        $this->assertSame('rejected', $order->payment_status);
        $this->assertSame('pending_payment', $order->status);
        $this->assertNull($order->paid_at);

        $this->actingAs($me)->post("/orders/{$order->order_number}/payment", ['payment_proof' => $this->proofFile()])
            ->assertSessionHasNoErrors();
        $this->assertSame('proof_uploaded', $order->fresh()->payment_status);
    }

    public function test_confirm_and_reject_need_a_proof_awaiting_verification(): void
    {
        $this->actingAs($this->admin());

        foreach ([
            ['payment_status' => 'unpaid'],
            ['status' => 'payment_confirmed', 'payment_status' => 'confirmed'],
            ['status' => 'cancelled', 'payment_status' => 'proof_uploaded'],
        ] as $state) {
            $order = $this->order($this->customer(), $state);

            $this->post("/admin/orders/{$order->id}/confirm")->assertSessionHas('error');
            $this->post("/admin/orders/{$order->id}/reject")->assertSessionHas('error');

            $fresh = $order->fresh();
            $this->assertSame($state['status'] ?? 'pending_payment', $fresh->status);
            $this->assertSame($state['payment_status'], $fresh->payment_status);
        }
    }

    // ------------------------------------------------------------ tracking

    public function test_admin_advances_status_step_by_step_and_customer_sees_it(): void
    {
        $me = $this->customer();
        $order = $this->order($me, ['status' => 'payment_confirmed', 'payment_status' => 'confirmed']);
        $admin = $this->admin();

        foreach (['preparing', 'out_for_delivery', 'delivered'] as $expected) {
            $this->actingAs($admin)->post("/admin/orders/{$order->id}/advance")->assertSessionHas('success');
            $this->assertSame($expected, $order->fresh()->status);
        }

        $this->post("/admin/orders/{$order->id}/advance")->assertSessionHas('error');   // sudah mentok
        $this->assertSame('delivered', $order->fresh()->status);

        $this->actingAs($me)->get("/orders/{$order->order_number}")->assertSee('Terkirim');
    }

    public function test_cannot_advance_unpaid_order(): void
    {
        $order = $this->order($this->customer());

        $this->actingAs($this->admin())->post("/admin/orders/{$order->id}/advance")->assertSessionHas('error');
        $this->assertSame('pending_payment', $order->fresh()->status);
    }

    public function test_customer_timeline_shows_current_stage(): void
    {
        $me = $this->customer();
        $order = $this->order($me, ['status' => 'out_for_delivery', 'payment_status' => 'confirmed']);

        $this->actingAs($me)->get("/orders/{$order->order_number}")
            ->assertSee('Dalam pengiriman')->assertSee('Sedang disiapkan')->assertSee('Lunas');
    }

    // ---------------------------------------------------- batal oleh admin

    public function test_admin_cancel_restocks_until_delivery(): void
    {
        $b = $this->bouquet('Rose Love', 8);
        $f = $this->flower(90);
        $admin = $this->admin();

        $prep = $this->order($this->customer(), ['status' => 'preparing', 'payment_status' => 'confirmed'], $b, $f);
        $this->actingAs($admin)->post("/admin/orders/{$prep->id}/cancel")->assertSessionHas('success');

        $this->assertSame('cancelled', $prep->fresh()->status);
        $this->assertSame(10, $b->fresh()->stock);
        $this->assertSame(100, $f->fresh()->stock);

        $this->post("/admin/orders/{$prep->id}/cancel")->assertSessionHas('error');   // tidak dobel
        $this->assertSame(10, $b->fresh()->stock);

        foreach (['out_for_delivery', 'delivered'] as $status) {
            $late = $this->order($this->customer(), ['status' => $status, 'payment_status' => 'confirmed'], $b);
            $this->post("/admin/orders/{$late->id}/cancel")->assertSessionHas('error');
            $this->assertSame($status, $late->fresh()->status);
        }
        $this->assertSame(10, $b->fresh()->stock);
    }
}