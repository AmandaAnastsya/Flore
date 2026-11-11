<?php

namespace Tests\Feature;

use App\Models\Bouquet;
use App\Models\Category;
use App\Models\Order;
use App\Models\Reminder;
use App\Models\SpecialMoment;
use App\Models\User;
use App\Notifications\SpecialMomentReminder;
use App\Services\ReminderPlanner;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SmartReminderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-11 08:00:00'));
        Notification::fake();
    }

    private function customer(array $attrs = []): User
    {
        return User::factory()->create(array_merge(['role' => 'customer', 'phone' => '081234567890'], $attrs));
    }

    // ------------------------------------------------------------ profil

    public function test_profile_page_requires_login_and_shows_form(): void
    {
        $this->get('/profile')->assertRedirect('/login');

        $this->actingAs($this->customer())->get('/profile')->assertOk()
            ->assertSee('Profil & Momen Spesial')
            ->assertSee('name="date_of_birth"', false)
            ->assertSee('name="anniversary_date"', false)
            ->assertSee('Tambah Reminder Baru');
    }

    public function test_customer_saves_birthday_anniversary_and_preferences(): void
    {
        $user = $this->customer();

        $this->actingAs($user)->put('/profile', [
            'name' => 'Clarissa', 'phone' => '0812-3456-7890',   // tidak valid karena tanda hubung
        ])->assertSessionHasErrors('phone');

        $this->put('/profile', [
            'name' => 'Clarissa', 'phone' => '081298765432',
            'date_of_birth' => '1998-11-20', 'anniversary_date' => '2023-12-01',
            'reminder_days' => 3, 'reminders_enabled' => '1',
        ])->assertRedirect('/profile')->assertSessionHas('success');

        $user->refresh();
        $this->assertSame('1998-11-20', $user->date_of_birth->toDateString());
        $this->assertSame('2023-12-01', $user->anniversary_date->toDateString());
        $this->assertSame(3, $user->reminder_days);
        $this->assertTrue($user->reminders_enabled);

        // checkbox tidak dikirim = nonaktif
        $this->put('/profile', ['name' => 'Clarissa', 'phone' => '081298765432', 'reminder_days' => 7]);
        $this->assertFalse($user->fresh()->reminders_enabled);
    }

    public function test_profile_validation(): void
    {
        $this->actingAs($this->customer())->put('/profile', [
            'name' => 'X', 'phone' => '081234567890',
            'date_of_birth' => '2030-01-01', 'anniversary_date' => '2030-01-01', 'reminder_days' => 5,
        ])->assertSessionHasErrors(['date_of_birth', 'anniversary_date', 'reminder_days']);
    }

    public function test_add_and_delete_special_moment_only_own(): void
    {
        $user = $this->customer();

        $this->actingAs($user)->post('/profile/moments', ['type' => 'birthday', 'title' => '', 'date' => ''])
            ->assertSessionHasErrorsIn('moment', ['title', 'date']);

        $this->post('/profile/moments', ['type' => 'birthday', 'title' => 'Ulang Tahun Ibu', 'date' => '1965-10-04'])
            ->assertRedirect('/profile');
        $moment = SpecialMoment::firstOrFail();
        $this->assertSame($user->id, $moment->user_id);
        $this->get('/profile')->assertSee('Ulang Tahun Ibu');

        $this->actingAs($this->customer())->delete("/profile/moments/{$moment->id}")->assertNotFound();
        $this->actingAs($user)->delete("/profile/moments/{$moment->id}")->assertRedirect('/profile');
        $this->assertModelMissing($moment);
    }

    // ------------------------------------------------------------ planner

    public function test_next_occurrence_handles_this_year_next_year_and_leap_day(): void
    {
        $today = Carbon::parse('2026-11-11');

        $this->assertSame('2026-11-20', ReminderPlanner::nextOccurrence(Carbon::parse('1998-11-20'), $today)->toDateString());
        $this->assertSame('2026-11-11', ReminderPlanner::nextOccurrence(Carbon::parse('1998-11-11'), $today)->toDateString());
        $this->assertSame('2027-03-01', ReminderPlanner::nextOccurrence(Carbon::parse('1990-03-01'), $today)->toDateString());
        $this->assertSame('2027-02-28', ReminderPlanner::nextOccurrence(Carbon::parse('2000-02-29'), $today)->toDateString());
        $this->assertSame('2028-02-29', ReminderPlanner::nextOccurrence(Carbon::parse('2000-02-29'), Carbon::parse('2028-01-01'))->toDateString());
    }

    public function test_upcoming_lists_all_sources_sorted(): void
    {
        $user = $this->customer(['date_of_birth' => '1998-12-25', 'anniversary_date' => '2023-11-15']);
        $user->specialMoments()->create(['type' => 'birthday', 'title' => 'Ulang Tahun Ibu', 'date' => '1965-11-30']);

        $titles = app(ReminderPlanner::class)->upcomingFor($user->load('specialMoments'))->pluck('title')->all();

        $this->assertSame(['Anniversary-mu', 'Ulang Tahun Ibu', 'Ulang tahunmu'], $titles);
    }

    // ------------------------------------------------------------ scheduler + email

    public function test_command_sends_email_only_when_within_reminder_window(): void
    {
        $soon = $this->customer(['date_of_birth' => '1998-11-18']);      // 7 hari lagi → kirim (default H-7)
        $later = $this->customer(['date_of_birth' => '1998-12-20']);     // masih jauh → belum
        $off = $this->customer(['date_of_birth' => '1998-11-15', 'reminders_enabled' => false]);
        $short = $this->customer(['anniversary_date' => '2020-11-16', 'reminder_days' => 3]);   // 5 hari lagi, H-3 → belum

        $this->artisan('reminders:send')->expectsOutputToContain('1 terkirim')->assertSuccessful();

        Notification::assertSentTo($soon, SpecialMomentReminder::class,
            fn ($n) => $n->reminder->title === 'Ulang tahunmu' && $n->reminder->occasion_date->toDateString() === '2026-11-18');
        Notification::assertNotSentTo([$later, $off, $short], SpecialMomentReminder::class);

        $reminder = Reminder::firstOrFail();
        $this->assertSame('sent', $reminder->status);
        $this->assertNotNull($reminder->sent_at);
        $this->assertSame('2026-11-11', $reminder->remind_at->toDateString());

        // dijalankan lagi di hari yang sama → tidak ada email ganda
        $this->artisan('reminders:send')->expectsOutputToContain('0 terkirim');
        Notification::assertSentToTimes($soon, SpecialMomentReminder::class, 1);
        $this->assertSame(1, Reminder::count());

        // 2 hari kemudian giliran anniversary H-3
        $this->travelTo(Carbon::parse('2026-11-13 08:00'));
        $this->artisan('reminders:send');
        Notification::assertSentTo($short, SpecialMomentReminder::class);
    }

    public function test_special_moment_and_recurring_purchase_are_reminded(): void
    {
        $user = $this->customer();
        $user->specialMoments()->create(['type' => 'birthday', 'title' => 'Ulang Tahun Ibu', 'date' => '1965-11-14']);

        $cat = Category::create(['name' => 'Hand Bouquet', 'slug' => 'hand-bouquet', 'is_active' => true]);
        $b = Bouquet::create(['category_id' => $cat->id, 'name' => 'Sweet Blush', 'slug' => 'sweet-blush', 'price' => 300000, 'stock' => 3]);
        $order = Order::create([
            'user_id' => $user->id, 'recipient_name' => 'Clarissa', 'recipient_phone' => '081234567890',
            'delivery_city' => 'Jakarta', 'delivery_address' => 'Jl. Melati No. 1', 'delivery_date' => '2025-11-16',
            'delivery_slot' => 'morning', 'occasion' => 'anniversary', 'subtotal' => 300000, 'total_amount' => 300000,
            'status' => 'delivered', 'payment_status' => 'confirmed',
        ]);
        $order->items()->create(['bouquet_id' => $b->id, 'bouquet_name' => 'Sweet Blush', 'price' => 300000, 'quantity' => 1, 'subtotal' => 300000]);

        $this->artisan('reminders:send')->expectsOutputToContain('2 terkirim');

        $this->assertDatabaseHas('reminders', ['type' => 'birthday', 'title' => 'Ulang Tahun Ibu', 'status' => 'sent']);
        $this->assertDatabaseHas('reminders', [
            'type' => 'recurring_purchase', 'order_id' => $order->id, 'occasion_date' => '2026-11-16 00:00:00', 'status' => 'sent',
        ]);
        Notification::assertSentTo($user, SpecialMomentReminder::class,
            fn ($n) => $n->reminder->type === 'recurring_purchase' && $n->catalogOccasion === 'anniversary');
    }

    public function test_order_delivered_less_than_a_year_ago_is_not_reminded(): void
    {
        $user = $this->customer();
        Order::create([
            'user_id' => $user->id, 'recipient_name' => 'X', 'recipient_phone' => '081234567890',
            'delivery_city' => 'Jakarta', 'delivery_address' => 'Jl. Melati No. 1', 'delivery_date' => '2026-11-11',
            'subtotal' => 1, 'total_amount' => 1, 'status' => 'delivered', 'payment_status' => 'confirmed',
        ]);

        $this->artisan('reminders:send')->expectsOutputToContain('0 terkirim');
    }

    public function test_changing_dates_removes_stale_pending_reminders(): void
    {
        $user = $this->customer(['date_of_birth' => '1998-11-18']);
        app(ReminderPlanner::class)->plan();
        $this->assertSame(1, $user->reminders()->where('status', 'pending')->count());

        $this->actingAs($user)->put('/profile', [
            'name' => $user->name, 'phone' => '081234567890', 'date_of_birth' => '1998-05-05', 'reminder_days' => 7, 'reminders_enabled' => '1',
        ]);

        $this->assertSame(0, $user->reminders()->count());
    }

    public function test_email_content(): void
    {
        $user = $this->customer(['name' => 'Clarissa']);
        $reminder = Reminder::create([
            'user_id' => $user->id, 'type' => 'birthday', 'title' => 'Ulang Tahun Ibu',
            'occasion_date' => '2026-11-18', 'remind_at' => '2026-11-11', 'channel' => 'email', 'status' => 'pending',
        ]);

        $mail = (new SpecialMomentReminder($reminder, 'birthday'))->toMail($user);
        $html = (string) $mail->render();

        $this->assertStringContainsString('Ulang Tahun Ibu', $mail->subject);
        $this->assertStringContainsString('7 hari lagi', $mail->subject);
        $this->assertStringContainsString('Halo, Clarissa!', $html);
        $this->assertStringContainsString('Rabu, 18 November 2026', $html);
        $this->assertSame(route('catalog.index', ['occasion' => 'birthday']), $mail->actionUrl);
    }

    public function test_command_is_scheduled_daily_at_eight(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($e) => str_contains($e->command ?? '', 'reminders:send'));

        $this->assertNotNull($event, 'reminders:send belum dijadwalkan');
        $this->assertSame('0 8 * * *', $event->expression);
    }

    // ------------------------------------------------------------ admin

    public function test_admin_sees_logs_and_can_trigger_manually(): void
    {
        $this->customer(['name' => 'Budi', 'date_of_birth' => '1990-11-12']);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($this->customer())->get('/admin/reminders')->assertForbidden();

        $this->actingAs($admin)->get('/admin/reminders')->assertOk()->assertSee('Belum ada pengingat yang jatuh tempo');
        $this->post('/admin/reminders/run')->assertSessionHas('success');

        $this->get('/admin/reminders')->assertSee('Budi')->assertSee('Ulang tahunmu');
        $this->assertSame(1, Reminder::where('status', 'sent')->count());
    }

    public function test_admin_sees_upcoming_schedule_before_anything_is_due(): void
    {
        // kasus nyata: ulang tahun 3 Januari → email baru dikirim 27 Desember
        $this->customer(['name' => 'Amanda', 'date_of_birth' => '2006-01-03']);
        $this->customer(['name' => 'Tanpa Tanggal']);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get('/admin/reminders')->assertOk()
            ->assertSee('Jadwal Pengingat Mendatang')
            ->assertSee('Amanda')->assertSee('03 Jan 2027')->assertSee('27 Dec 2026')
            ->assertDontSee('Tanpa Tanggal')
            ->assertSee('Belum ada pengingat yang jatuh tempo');

        $this->assertSame(0, Reminder::count());   // melihat jadwal tidak membuat data
    }

    public function test_home_shows_upcoming_moments(): void
    {
        $user = $this->customer(['date_of_birth' => '1998-11-18']);

        $this->actingAs($user)->get('/home')->assertOk()
            ->assertSee('Ulang tahunmu')->assertSee('7 hari lagi')
            ->assertSee(route('profile.edit'), false);
    }
}
