<?php

namespace Tests\Feature;

use App\Models\Bouquet;
use App\Models\Category;
use App\Models\Florist;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Florist Assignment & tahap fulfillment + cetak label & kartu (mockup hal. 48). */
class FulfillmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function order(string $status = 'payment_confirmed', array $attrs = []): Order
    {
        $cat = Category::firstOrCreate(['slug' => 'hand-bouquet'], ['name' => 'Hand Bouquet']);
        $b = Bouquet::unguarded(fn () => Bouquet::firstOrCreate(['slug' => 'sweet-blush'],
            ['category_id' => $cat->id, 'name' => 'Sweet Blush', 'price' => 450000, 'stock' => 8, 'is_active' => true]));

        $order = Order::create(array_merge([
            'user_id' => User::factory()->create()->id, 'recipient_name' => 'Clarissa Valery', 'recipient_phone' => '081298765432',
            'delivery_city' => 'Jakarta Selatan', 'delivery_address' => 'Jl. Gunawarman No. 42', 'delivery_slot' => 'afternoon',
            'delivery_date' => today()->toDateString(), 'subtotal' => 450000, 'total_amount' => 450000,
            'occasion' => 'anniversary', 'card_message' => 'Happy 3rd anniversary, my love!', 'sender_name' => 'Julian',
            'status' => $status, 'payment_status' => 'confirmed', 'paid_at' => now(),
        ], $attrs));
        $order->items()->create(['bouquet_id' => $b->id, 'bouquet_name' => 'Sweet Blush', 'price' => 450000, 'quantity' => 1, 'subtotal' => 450000]);

        return $order;
    }

    public function test_pages_are_admin_only(): void
    {
        $order = $this->order();
        $this->get('/admin/florists')->assertRedirect();
        $this->actingAs($order->user)->get('/admin/florists')->assertForbidden();
        $this->actingAs($order->user)->get("/admin/orders/{$order->id}/print")->assertForbidden();
        $this->actingAs($order->user)->post("/admin/orders/{$order->id}/stage")->assertForbidden();
    }

    public function test_full_production_flow_with_qc_photo_and_customer_snapshot(): void
    {
        $admin = $this->admin();
        $maya = Florist::create(['name' => 'Maya Sari', 'specialty' => 'Wedding & hand bouquet']);
        $order = $this->order();

        $this->actingAs($admin)->post("/admin/orders/{$order->id}/florist", ['florist_id' => $maya->id, 'production_note' => 'Tambahkan kartu emas'])
            ->assertSessionHas('success');
        $this->assertSame($maya->id, $order->fresh()->florist_id);

        // mulai rangkai: status → Sedang disiapkan, tahap 1
        $this->post("/admin/orders/{$order->id}/stage")->assertSessionHas('success');
        $order->refresh();
        $this->assertSame(['preparing', 'arranging'], [$order->status, $order->fulfillment_stage]);

        $this->post("/admin/orders/{$order->id}/stage");
        $this->assertSame('qc', $order->fresh()->fulfillment_stage);

        // lulus QC wajib foto
        $this->post("/admin/orders/{$order->id}/stage")->assertSessionHas('error');
        $this->assertSame('qc', $order->fresh()->fulfillment_stage);
        $this->post("/admin/orders/{$order->id}/stage", ['qc_photo' => UploadedFile::fake()->create('x.pdf', 50, 'application/pdf')])
            ->assertSessionHasErrors('qc_photo');

        $this->post("/admin/orders/{$order->id}/stage", ['qc_photo' => UploadedFile::fake()->image('rangkaian.jpg', 800, 600)])->assertSessionHas('success');
        $order->refresh();
        $this->assertSame('packing', $order->fulfillment_stage);
        $this->assertNotNull($order->qc_passed_at);
        Storage::disk('local')->assertExists($order->qc_photo);

        $this->post("/admin/orders/{$order->id}/stage");
        $this->assertSame('ready', $order->fresh()->fulfillment_stage);
        $this->post("/admin/orders/{$order->id}/stage")->assertSessionHas('error');   // tahap terakhir

        $this->get("/admin/orders/{$order->id}")->assertSee('Produksi & Fulfillment', false)->assertSee('Siap diserahkan ke kurir');
        $this->get('/admin/florists')->assertOk()->assertSee('Siap Handover Kurir')->assertSee('#' . $order->order_number)->assertSee('Tambahkan kartu emas');
        $this->get('/admin/logistics?order=' . $order->id)->assertSee('Studio: <span class="text-success fw-semibold">Siap Handover Kurir</span>', false);

        // customer melihat foto rangkaian sebelum dikirim
        $this->actingAs($order->user)->get("/orders/{$order->order_number}")
            ->assertSee('Live Studio Arrangement Snapshot')->assertSee('Lulus QC')->assertSee('Siap Handover Kurir');
        $this->get("/orders/{$order->order_number}/qc-photo")->assertOk();
        $this->actingAs(User::factory()->create())->get("/orders/{$order->order_number}/qc-photo")->assertNotFound();
    }

    public function test_assignment_rules(): void
    {
        $this->actingAs($this->admin());
        $off = Florist::create(['name' => 'Cuti', 'is_active' => false]);
        $on = Florist::create(['name' => 'Rina']);

        $order = $this->order();
        $this->post("/admin/orders/{$order->id}/florist", ['florist_id' => $off->id])->assertSessionHasErrors('florist_id');

        $delivered = $this->order('delivered');
        $this->post("/admin/orders/{$delivered->id}/florist", ['florist_id' => $on->id])->assertSessionHas('error');
        $this->assertNull($delivered->fresh()->florist_id);

        $unpaid = $this->order('pending_payment', ['payment_status' => 'unpaid']);
        $this->post("/admin/orders/{$unpaid->id}/stage")->assertSessionHas('error');
        $this->assertSame('pending_payment', $unpaid->fresh()->status);

        // tombol status lama tetap jalan & ikut mengisi tahap pertama
        $this->post("/admin/orders/{$order->id}/advance");
        $this->assertSame('arranging', $order->fresh()->fulfillment_stage);
    }

    public function test_print_label_and_greeting_card(): void
    {
        config(['flore.store.name' => 'Floré Studio', 'flore.studio.address' => 'Jl. Senopati 8']);
        $order = $this->order();

        $this->actingAs($this->admin())->get("/admin/orders/{$order->id}/print")->assertOk()
            ->assertSee($order->order_number)->assertSee('Untuk: Clarissa Valery')->assertSee('Jl. Gunawarman No. 42')
            ->assertSee('1× Sweet Blush')->assertSee('“Happy 3rd anniversary, my love!”', false)->assertSee('— Julian')
            ->assertSee('Cetak / Simpan PDF')->assertSee('Jl. Senopati 8');

        $order->update(['is_anonymous' => true]);
        $this->get("/admin/orders/{$order->id}/print")->assertDontSee('— Julian')->assertSee('Seseorang yang menyayangimu');
    }

    public function test_florist_crud(): void
    {
        $this->actingAs($this->admin());
        $this->post('/admin/florists', ['name' => '', 'phone' => '123'])->assertSessionHasErrors(['name', 'phone']);
        $this->post('/admin/florists', ['name' => 'Maya Sari', 'phone' => '081277000111', 'specialty' => 'Wedding', 'is_active' => 1])->assertRedirect('/admin/florists');
        $maya = Florist::firstOrFail();
        $this->put("/admin/florists/{$maya->id}", ['name' => 'Maya S.', 'is_active' => 0])->assertRedirect('/admin/florists');
        $this->assertFalse($maya->fresh()->is_active);
        $this->get('/admin/florists')->assertSee('Maya S.')->assertSee('Nonaktif');
    }
}
