<?php

namespace Tests\Feature;

use App\Models\Bouquet;
use App\Models\Category;
use App\Models\Courier;
use App\Models\Order;
use App\Models\User;
use App\Notifications\OrderUpdate;
use App\Services\DeliveryDispatch;
use App\Services\OrderService;
use App\Services\OrderWorkflow;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Email invoice & notifikasi status pesanan ke customer. */
class OrderEmailTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;
    private Bouquet $bouquet;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Storage::fake('local');
        $this->customer = User::factory()->create(['name' => 'Clarissa Valery', 'email' => 'clarissa@flore.test']);
        $cat = Category::create(['name' => 'Hand Bouquet', 'slug' => 'hand-bouquet']);
        $this->bouquet = Bouquet::unguarded(fn () => Bouquet::create([
            'category_id' => $cat->id, 'name' => 'Sweet Blush Romance', 'slug' => 'sweet-blush', 'price' => 1150000, 'stock' => 10, 'is_active' => true,
        ]));
    }

    private function place(): Order
    {
        return app(OrderService::class)->place($this->customer, [
            'recipient_name' => 'Julian Santoso', 'recipient_phone' => '081298765432', 'delivery_city' => 'Jakarta Pusat',
            'delivery_address' => 'Plaza Indonesia Lt. 18', 'delivery_date' => today()->addDay()->toDateString(), 'delivery_slot' => 'afternoon',
        ], [$this->bouquet->id => 1], []);
    }

    /** @return string[] kejadian email yang terkirim ke customer, berurutan */
    private function sentEvents(): array
    {
        return collect(Notification::sent($this->customer, OrderUpdate::class))->map->event->all();
    }

    public function test_full_lifecycle_sends_invoice_and_every_status_email(): void
    {
        $order = $this->place();
        $this->assertSame(['placed'], $this->sentEvents());

        $workflow = app(OrderWorkflow::class);
        $workflow->uploadProof($order, UploadedFile::fake()->image('bukti.jpg'));
        $workflow->confirmPayment($order);
        $workflow->advance($order->fresh());                                  // → preparing

        $courier = Courier::create(['name' => 'Doni Pratama', 'phone' => '081277120001', 'vehicle' => 'motor']);
        app(DeliveryDispatch::class)->assign($order->fresh(), $courier, 30);
        app(DeliveryDispatch::class)->dispatch($order->fresh());               // → out_for_delivery
        app(DeliveryDispatch::class)->confirmDelivered($order->fresh(), 'Satpam Lobby');

        $this->assertSame(['placed', 'proof_uploaded', 'paid', 'preparing', 'out_for_delivery', 'delivered'], $this->sentEvents());
    }

    public function test_email_content_invoice_and_courier_details(): void
    {
        config(['flore.payment.bank' => 'Mandiri', 'flore.payment.account_number' => '123-000-777']);
        $order = $this->place();

        Notification::assertSentTo($this->customer, OrderUpdate::class, function (OrderUpdate $n) use ($order) {
            $mail = $n->toMail($this->customer);
            $html = (string) $mail->render();

            return $n->event === 'placed'
                && $mail->subject === "Invoice {$order->order_number} — menunggu pembayaran · Floré"
                && str_contains($html, 'INV/' . $order->order_number)
                && str_contains($html, 'BELUM DIBAYAR')
                && str_contains($html, 'Sweet Blush Romance')
                && str_contains($html, 'Rp 1.150.000')
                && str_contains($html, '123-000-777')
                && str_contains($html, 'Unggah Bukti Transfer')
                && str_contains($html, route('orders.invoice', $order->order_number));
        });

        // email "dikirim" berisi nama kurir & ETA
        app(OrderWorkflow::class)->uploadProof($order, UploadedFile::fake()->image('b.jpg'));
        app(OrderWorkflow::class)->confirmPayment($order);
        app(OrderWorkflow::class)->advance($order->fresh());
        $courier = Courier::create(['name' => 'Doni Pratama', 'phone' => '081277120001', 'vehicle' => 'motor']);
        app(DeliveryDispatch::class)->assign($order->fresh(), $courier, 30);
        app(DeliveryDispatch::class)->dispatch($order->fresh());

        Notification::assertSentTo($this->customer, OrderUpdate::class, function (OrderUpdate $n) {
            $html = (string) $n->toMail($this->customer)->render();

            return $n->event === 'out_for_delivery' && str_contains($html, 'Doni Pratama') && str_contains($html, now()->addMinutes(30)->format('H:i'));
        });
        Notification::assertSentTo($this->customer, OrderUpdate::class, function (OrderUpdate $n) {
            return $n->event === 'paid' && str_contains((string) $n->toMail($this->customer)->render(), 'LUNAS');
        });
    }

    public function test_rejected_and_cancelled_emails(): void
    {
        $order = $this->place();
        app(OrderWorkflow::class)->uploadProof($order, UploadedFile::fake()->image('b.jpg'));
        app(OrderWorkflow::class)->rejectPayment($order);
        app(OrderWorkflow::class)->cancel($order->fresh(), byAdmin: false);

        $this->assertSame(['placed', 'proof_uploaded', 'payment_rejected', 'cancelled'], $this->sentEvents());
    }

    public function test_opt_out_skips_status_updates_but_keeps_transactional_emails(): void
    {
        $this->actingAs($this->customer)->put('/account/notifications', ['order_updates_enabled' => '0'])->assertSessionHas('success');
        $this->assertFalse($this->customer->fresh()->order_updates_enabled);
        $this->get('/account/settings')->assertSee('Email update status pesanan');

        $order = $this->place();
        app(OrderWorkflow::class)->uploadProof($order, UploadedFile::fake()->image('b.jpg'));   // tidak wajib → dilewati
        app(OrderWorkflow::class)->confirmPayment($order);                                      // wajib
        app(OrderWorkflow::class)->advance($order->fresh());                                    // tidak wajib → dilewati

        $this->assertSame(['placed', 'paid'], $this->sentEvents());
    }

    public function test_no_email_when_transaction_rolls_back(): void
    {
        $order = $this->place();

        try {
            DB::transaction(function () use ($order) {
                $order->update(['status' => 'cancelled']);
                throw new \RuntimeException('gagal');
            });
        } catch (\RuntimeException) {
        }

        $this->assertSame(['placed'], $this->sentEvents());
        $this->assertSame('pending_payment', $order->fresh()->status);
    }

    public function test_invoice_page_for_owner_and_admin_only(): void
    {
        $order = $this->place();

        $this->actingAs($this->customer)->get("/orders/{$order->order_number}/invoice")->assertOk()
            ->assertSee('INV/' . $order->order_number)->assertSee('BELUM DIBAYAR')->assertSee('Cetak / Simpan PDF')
            ->assertSee('Julian Santoso')->assertSee('Rp 1.150.000');
        $this->get("/orders/{$order->order_number}")->assertSee('Lihat Invoice');

        $order->update(['status' => 'payment_confirmed', 'payment_status' => 'confirmed', 'paid_at' => now()]);
        $this->get("/orders/{$order->order_number}/invoice")->assertSee('LUNAS')->assertDontSee('Cara pembayaran');
        $this->get("/orders/{$order->order_number}")->assertSee('Unduh Kuitansi (LUNAS)');

        $this->actingAs(User::factory()->create())->get("/orders/{$order->order_number}/invoice")->assertNotFound();
        $this->get('/admin/orders/' . $order->id . '/invoice')->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/admin/orders/' . $order->id . '/invoice')->assertOk()->assertSee('LUNAS');
    }

    public function test_demo_seeder_and_deleted_accounts_send_nothing(): void
    {
        $this->seed();
        $this->seed(DemoSeeder::class);
        Notification::assertNothingSent();

        $this->customer->forceFill(['email' => 'deleted-1-abc@deleted.flore.invalid'])->save();
        $this->place();
        Notification::assertNothingSent();
    }
}
