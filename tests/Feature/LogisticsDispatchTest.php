<?php

namespace Tests\Feature;

use App\Models\Bouquet;
use App\Models\Category;
use App\Models\Courier;
use App\Models\Order;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Logistics & Courier Dispatch Dashboard (proposal hal. 50). */
class LogisticsDispatchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->travelTo(now()->setTime(13, 0));   // di tengah slot siang
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function courier(array $attrs = []): Courier
    {
        return Courier::create(array_merge(['name' => 'Doni Pratama', 'phone' => '081277120001', 'vehicle' => 'motor', 'plate_number' => 'B 4321 KZT'], $attrs));
    }

    private function order(string $status = 'preparing', array $attrs = []): Order
    {
        $cat = Category::firstOrCreate(['slug' => 'hand-bouquet'], ['name' => 'Hand Bouquet']);
        $b = Bouquet::unguarded(fn () => Bouquet::firstOrCreate(['slug' => 'rose-love'],
            ['category_id' => $cat->id, 'name' => 'Rose Love', 'price' => 100000, 'stock' => 8, 'is_active' => true]));

        $order = Order::create(array_merge([
            'user_id' => User::factory()->create()->id, 'recipient_name' => 'Clarissa Valery', 'recipient_phone' => '081298765432',
            'delivery_city' => 'Jakarta Pusat', 'delivery_address' => 'Plaza Indonesia Lt. 18', 'delivery_slot' => 'afternoon',
            'delivery_date' => today()->toDateString(), 'subtotal' => 100000, 'total_amount' => 100000,
            'status' => $status, 'payment_status' => 'confirmed', 'paid_at' => now()->subHours(3),
        ], $attrs));
        $order->items()->create(['bouquet_id' => $b->id, 'bouquet_name' => 'Rose Love', 'price' => 100000, 'quantity' => 1, 'subtotal' => 100000]);

        return $order;
    }

    // ------------------------------------------------------------ akses & tampilan

    public function test_logistics_pages_are_admin_only(): void
    {
        $order = $this->order();

        $this->get('/admin/logistics')->assertRedirect();
        $this->actingAs(User::factory()->create())->get('/admin/logistics')->assertForbidden();
        $this->actingAs(User::factory()->create())->get('/admin/couriers')->assertForbidden();
        $this->actingAs(User::factory()->create())->post("/admin/logistics/{$order->id}/dispatch")->assertForbidden();
        $this->actingAs($this->admin())->get('/admin/logistics')->assertOk()->assertSee('Logistics & Courier Dispatch Dashboard', false);
    }

    public function test_dashboard_shows_queue_states_kpis_and_monitoring(): void
    {
        $doni = $this->courier();
        $waiting = $this->order('preparing', ['recipient_name' => 'Tanpa Kurir']);
        $ready = $this->order('preparing', ['recipient_name' => 'Siap Ambil']);
        $ready->forceFill(['courier_id' => $doni->id, 'travel_minutes' => 30])->save();
        $onWay = $this->order('out_for_delivery', ['recipient_name' => 'Di Jalan']);
        $onWay->forceFill(['courier_id' => $doni->id, 'dispatched_at' => now()->subMinutes(10), 'eta_at' => now()->addMinutes(20)])->save();
        $late = $this->order('out_for_delivery', ['recipient_name' => 'Telat']);
        $late->forceFill(['courier_id' => $doni->id, 'dispatched_at' => now()->subHour(), 'eta_at' => now()->subMinutes(5)])->save();
        $this->order('pending_payment', ['recipient_name' => 'Belum Bayar', 'payment_status' => 'unpaid']);

        $res = $this->actingAs($this->admin())->get('/admin/logistics')->assertOk();

        $res->assertSee('Menunggu Kurir')->assertSee('Siap Pick-up')->assertSee('Dalam Perjalanan')->assertSee('Melewati ETA')
            ->assertDontSee('Belum Bayar')
            ->assertSee('2 Paket')            // siap penugasan (preparing)
            ->assertSee('1 Kurir Aktif')      // kurir unik yang sedang di jalan
            ->assertSee('1 Insiden')          // melewati ETA
            ->assertSee('Monitoring Kurir: Doni Pratama (#' . $late->order_number . ')', false)   // yang terlambat dipilih paling atas
            ->assertSee('Sudah melewati ETA');

        // pesanan yang masih sesuai jadwal: posisi diestimasi dari ETA, dan jujur bukan GPS
        $this->get("/admin/logistics?order={$onWay->id}")
            ->assertSee('Monitoring Kurir: Doni Pratama (#' . $onWay->order_number . ')', false)
            ->assertSee('Estimasi posisi dari ETA (33%)', false)->assertSee('bukan GPS live')
            ->assertSee('sisa 20 menit')->assertSee('Hubungi Kurir (WhatsApp)')->assertSee('wa.me/6281277120001', false);

        // sidebar: menunggu kurir + melewati ETA
        $this->assertMatchesRegularExpression('/Logistik & Kurir\s*<span class="count"[^>]*>2<\/span>/', $res->getContent());
    }

    // ------------------------------------------------------------ alur dispatch

    public function test_full_flow_assign_dispatch_deliver_with_pod_photo(): void
    {
        $admin = $this->admin();
        $doni = $this->courier();
        $order = $this->order();

        $this->actingAs($admin)->post("/admin/logistics/{$order->id}/assign", ['courier_id' => $doni->id, 'travel_minutes' => 45])
            ->assertRedirect(route('admin.logistics.index', ['order' => $order->id]))->assertSessionHas('success');
        $this->assertSame($doni->id, $order->fresh()->courier_id);
        $this->assertSame('ready_pickup', $order->fresh()->dispatchState());

        $this->post("/admin/logistics/{$order->id}/dispatch")->assertSessionHas('success');
        $order->refresh();
        $this->assertSame('out_for_delivery', $order->status);
        $this->assertTrue($order->dispatched_at->equalTo(now()));
        $this->assertTrue($order->eta_at->equalTo(now()->addMinutes(45)));

        // customer melihat kurir & perkiraan tiba
        $this->actingAs($order->user)->get("/orders/{$order->order_number}")
            ->assertSee('Doni Pratama')->assertSee('perkiraan tiba')->assertSee(now()->addMinutes(45)->format('H:i'));

        $this->travel(30)->minutes();
        $this->actingAs($admin)->post("/admin/logistics/{$order->id}/deliver", [
            'received_by' => 'Satpam Lobby', 'delivery_photo' => UploadedFile::fake()->image('pod.jpg', 800, 600),
        ])->assertSessionHas('success');

        $order->refresh();
        $this->assertSame('delivered', $order->status);
        $this->assertSame('Satpam Lobby', $order->received_by);
        $this->assertTrue($order->deliveredOnTime());   // 13:30, slot siang berakhir 15:00
        Storage::disk('local')->assertExists($order->delivery_photo);

        $this->get("/admin/logistics/{$order->id}/photo")->assertOk();
        $this->actingAs($order->user)->get("/orders/{$order->order_number}")->assertSee('Diterima oleh Satpam Lobby')->assertSee('Lihat foto serah terima');
        $this->get("/orders/{$order->order_number}/delivery-photo")->assertOk();
        $this->actingAs(User::factory()->create())->get("/orders/{$order->order_number}/delivery-photo")->assertNotFound();

        $this->actingAs($admin)->get('/admin/logistics?tab=delivered')->assertSee('1 Pesanan')->assertSee('100% tepat waktu', false);
    }

    public function test_dispatch_rules_are_enforced(): void
    {
        $admin = $this->admin();
        $doni = $this->courier();

        // belum ada kurir
        $order = $this->order();
        $this->actingAs($admin)->post("/admin/logistics/{$order->id}/dispatch")->assertSessionHas('error', 'Tugaskan kurir terlebih dahulu.');
        $this->assertSame('preparing', $order->fresh()->status);

        // bouquet belum dirangkai (payment_confirmed) boleh ditugaskan, tapi belum boleh berangkat
        $confirmed = $this->order('payment_confirmed');
        $this->post("/admin/logistics/{$confirmed->id}/assign", ['courier_id' => $doni->id, 'travel_minutes' => 30])->assertSessionHas('success');
        $this->post("/admin/logistics/{$confirmed->id}/dispatch")->assertSessionHas('error');

        // belum dibayar & sudah terkirim: tidak bisa ditugaskan
        $unpaid = $this->order('pending_payment', ['payment_status' => 'unpaid']);
        $this->post("/admin/logistics/{$unpaid->id}/assign", ['courier_id' => $doni->id, 'travel_minutes' => 30])->assertSessionHas('error');
        $this->assertNull($unpaid->fresh()->courier_id);

        // konfirmasi terkirim hanya saat di jalan
        $this->post("/admin/logistics/{$order->id}/deliver", ['received_by' => 'X'])->assertSessionHas('error');
        $this->assertSame('preparing', $order->fresh()->status);
    }

    public function test_validation_and_inactive_courier(): void
    {
        $admin = $this->admin();
        $off = $this->courier(['name' => 'Kurir Cuti', 'phone' => '081277120009', 'is_active' => false]);
        $order = $this->order();

        $this->actingAs($admin)->post("/admin/logistics/{$order->id}/assign", ['courier_id' => $off->id, 'travel_minutes' => 30])
            ->assertSessionHasErrors('courier_id');
        $this->post("/admin/logistics/{$order->id}/assign", ['courier_id' => $this->courier()->id, 'travel_minutes' => 7])
            ->assertSessionHasErrors('travel_minutes');
        $this->assertNull($order->fresh()->courier_id);

        $order->forceFill(['status' => 'out_for_delivery', 'courier_id' => Courier::active()->value('id'), 'dispatched_at' => now(), 'eta_at' => now()->addHour()])->save();
        $this->post("/admin/logistics/{$order->id}/deliver", ['received_by' => ''])->assertSessionHasErrors('received_by');
        $this->post("/admin/logistics/{$order->id}/deliver", ['received_by' => 'Budi', 'delivery_photo' => UploadedFile::fake()->create('pod.pdf', 100, 'application/pdf')])
            ->assertSessionHasErrors('delivery_photo');
        $this->assertSame('out_for_delivery', $order->fresh()->status);
    }

    public function test_reroute_and_issue_reporting(): void
    {
        $admin = $this->admin();
        $doni = $this->courier();
        $rian = $this->courier(['name' => 'Rian Hidayat', 'phone' => '081277120002', 'vehicle' => 'mobil']);
        $order = $this->order('out_for_delivery');
        $order->forceFill(['courier_id' => $doni->id, 'dispatched_at' => now()->subHour(), 'eta_at' => now()->subMinutes(10)])->save();
        $this->assertTrue($order->fresh()->isLate());

        $this->actingAs($admin)->post("/admin/logistics/{$order->id}/issue", ['delivery_issue' => 'Ban bocor di Jl. Sudirman'])->assertSessionHas('success');
        $this->get("/admin/logistics?order={$order->id}")->assertSee('Ban bocor di Jl. Sudirman')->assertSee('Tandai Kendala Selesai');

        $this->post("/admin/logistics/{$order->id}/reroute", ['courier_id' => $rian->id, 'travel_minutes' => 30])->assertSessionHas('success');
        $order->refresh();
        $this->assertSame($rian->id, $order->courier_id);
        $this->assertTrue($order->eta_at->equalTo(now()->addMinutes(30)));
        $this->assertFalse($order->isLate());

        $this->post("/admin/logistics/{$order->id}/issue/resolve")->assertSessionHas('success');
        $this->assertNull($order->fresh()->delivery_issue);

        // re-route hanya untuk yang di jalan
        $preparing = $this->order();
        $this->post("/admin/logistics/{$preparing->id}/reroute", ['courier_id' => $rian->id, 'travel_minutes' => 30])->assertSessionHas('error');
    }

    public function test_estimated_progress_is_time_based_and_never_claims_arrival(): void
    {
        $order = $this->order('out_for_delivery');
        $order->forceFill(['dispatched_at' => now()->subMinutes(15), 'eta_at' => now()->addMinutes(15)])->save();
        $this->assertEqualsWithDelta(0.5, $order->estimatedProgress(), 0.01);

        $order->forceFill(['eta_at' => now()->subMinute()])->save();
        $this->assertSame(0.95, $order->fresh()->estimatedProgress());

        $this->assertSame(0.0, $this->order('preparing')->estimatedProgress());
    }

    public function test_order_management_advance_keeps_logistics_timestamps(): void
    {
        $order = $this->order();
        $order->forceFill(['travel_minutes' => 30])->save();

        $this->actingAs($this->admin())->post("/admin/orders/{$order->id}/advance");
        $order->refresh();
        $this->assertSame('out_for_delivery', $order->status);
        $this->assertTrue($order->eta_at->equalTo(now()->addMinutes(30)));

        $this->post("/admin/orders/{$order->id}/advance");
        $this->assertNotNull($order->fresh()->delivered_at);
    }

    // ------------------------------------------------------------ data kurir

    public function test_courier_crud_and_deactivation_guard(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/couriers', ['name' => '', 'phone' => '12345', 'vehicle' => 'pesawat'])
            ->assertSessionHasErrors(['name', 'phone', 'vehicle']);

        $this->post('/admin/couriers', ['name' => 'Bagas Kurniawan', 'phone' => '081277120003', 'vehicle' => 'motor', 'plate_number' => 'b 5560 tqa', 'is_active' => '1'])
            ->assertRedirect('/admin/couriers');
        $bagas = Courier::where('name', 'Bagas Kurniawan')->firstOrFail();
        $this->assertSame('B 5560 TQA', $bagas->plate_number);
        $this->assertSame('6281277120003', $bagas->whatsappNumber());
        $this->get('/admin/couriers')->assertSee('Bagas Kurniawan')->assertSee('wa.me/6281277120003', false);

        // sedang membawa pesanan → tidak bisa dinonaktifkan
        $order = $this->order('out_for_delivery');
        $order->forceFill(['courier_id' => $bagas->id, 'dispatched_at' => now(), 'eta_at' => now()->addHour()])->save();
        $this->put("/admin/couriers/{$bagas->id}", ['name' => 'Bagas Kurniawan', 'phone' => '081277120003', 'vehicle' => 'motor', 'is_active' => '0'])
            ->assertSessionHas('error');
        $this->assertTrue($bagas->fresh()->is_active);

        $order->forceFill(['status' => 'delivered'])->save();
        $this->put("/admin/couriers/{$bagas->id}", ['name' => 'Bagas K.', 'phone' => '081277120003', 'vehicle' => 'mobil', 'is_active' => '0'])
            ->assertRedirect('/admin/couriers');
        $this->assertFalse($bagas->fresh()->is_active);
        $this->assertSame('mobil', $bagas->fresh()->vehicle);
    }

    public function test_demo_seeder_fills_logistics_page(): void
    {
        $this->seed();
        $this->seed(DemoSeeder::class);
        $this->seed(DemoSeeder::class);   // aman dijalankan ulang

        $this->assertSame(4, Courier::count());
        $this->assertSame(0, Order::where('status', 'delivered')->whereNull('delivered_at')->count());

        $this->actingAs(User::where('role', 'admin')->first())->get('/admin/logistics')->assertOk()
            ->assertSee('Menunggu Kurir')->assertSee('Siap Pick-up')->assertSee('Dalam Perjalanan')->assertSee('Doni Pratama');
    }
}
