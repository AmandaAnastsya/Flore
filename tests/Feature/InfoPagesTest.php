<?php

namespace Tests\Feature;

use App\Models\Flower;
use App\Models\Florist;
use App\Models\Order;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Our Story, Care Guide, FAQ (mockup hal. 53–55) + tabel Fresh Floral Inventory di dashboard (hal. 44). */
class InfoPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_pages_are_public_and_linked_from_navbar_and_footer(): void
    {
        foreach (['/our-story' => 'Our Story &amp; Philosophy', '/care-guide' => 'Flower Care Guide', '/faq' => 'FAQ &amp; Shipping Policy'] as $url => $title) {
            $this->get($url)->assertOk()->assertSee($title, false);
        }

        $this->get('/')->assertSee(route('pages.story'), false)->assertSee(route('pages.care'), false)->assertSee(route('pages.faq'), false)
            ->assertSee('Tentang Kami');
    }

    public function test_our_story_uses_real_florists_and_approved_testimonial(): void
    {
        Florist::create(['name' => 'Maya Sari', 'specialty' => 'Wedding & hand bouquet']);
        Florist::create(['name' => 'Cuti Panjang', 'is_active' => false]);

        $user = User::factory()->create(['name' => 'Ananya Rahma']);
        $order = Order::create([
            'user_id' => $user->id, 'recipient_name' => 'X', 'recipient_phone' => '081234567890', 'delivery_city' => 'Bandung',
            'delivery_address' => 'Jl. Mawar 1', 'delivery_date' => today()->toDateString(), 'delivery_slot' => 'morning',
            'subtotal' => 1, 'total_amount' => 1, 'status' => 'delivered', 'payment_status' => 'confirmed',
        ]);
        Testimonial::create(['user_id' => $user->id, 'order_id' => $order->id, 'rating' => 5, 'content' => 'Rangkaiannya punya jiwa!', 'is_approved' => true, 'approved_at' => now()]);

        $this->get('/our-story')->assertOk()
            ->assertSee('Meet Our Florists')->assertSee('Maya Sari')->assertSee('Wedding &amp; hand bouquet', false)->assertDontSee('Cuti Panjang')
            ->assertSee('Rangkaiannya punya jiwa!')->assertSee('— Ananya Rahma', false)
            ->assertDontSee('Elena Rostova');   // nama contoh dari mockup tidak ditampilkan
    }

    public function test_faq_reflects_real_settings(): void
    {
        config([
            'flore.payment.bank' => 'Mandiri', 'flore.payment.account_number' => '123-000-777',
            'flore.delivery.lead_hours' => 4, 'flore.delivery.max_days' => 45, 'flore.store.phone' => '081299990000',
        ]);

        $this->get('/faq')->assertOk()
            ->assertSee('Mandiri 123-000-777')->assertSee('minimal 4 jam')->assertSee('hingga 45 hari')
            ->assertSee('Pagi (09.00–12.00)')->assertSee('wa.me/6281299990000', false)
            ->assertSee('toleransi keterlambatan maksimal 45 menit')->assertSee('garansi 100% replacement');

        config(['flore.store.phone' => null, 'flore.store.email' => 'halo@flore.id']);
        $this->get('/faq')->assertSee('mailto:halo@flore.id', false)->assertDontSee('wa.me');
    }

    public function test_care_guide_lists_steps_and_flower_tips(): void
    {
        $this->get('/care-guide')->assertOk()
            ->assertSeeInOrder(['Potong Batang 45°', 'Gunakan Air Bersih', 'Bersihkan Daun Bawah', 'Hindari Sinar Langsung'])
            ->assertSee('Mawar (Rose)')->assertSee('7 – 10 hari')->assertSee('Petik serbuk sari');
    }

    public function test_dashboard_fresh_floral_inventory_table(): void
    {
        $make = fn ($name, $stock, $daysAgo) => tap(new Flower([
            'name' => $name, 'slug' => str($name)->slug(), 'species' => 'rose', 'origin' => 'Lokal (Bandung)',
            'price_per_stem' => 8000, 'stock' => $stock, 'reorder_level' => 30, 'shelf_life_days' => 6, 'is_active' => true,
        ]), function ($f) use ($daysAgo) { $f->last_restocked_at = now()->subDays($daysAgo); $f->save(); });

        $make('Rose Segar', 200, 0);
        $make('Peony Kritis', 12, 1);
        $make('Lily Layu', 90, 7);

        $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/admin/dashboard')->assertOk()
            ->assertSee('Fresh Floral Inventory & Cold-Room Stock', false)
            ->assertSeeInOrder(['Peony Kritis', 'Lily Layu', 'Rose Segar'])   // paling mendesak di atas
            ->assertSee('Kritis (Restock)')->assertSee('Lewat umur segar')->assertSee('Segar · 100%')
            ->assertSee('Cek &amp; Buang', false);
    }
}
