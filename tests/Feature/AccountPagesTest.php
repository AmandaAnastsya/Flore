<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use App\Notifications\EmailChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Profil Saya & Pengaturan Akun untuk customer dan admin. */
class AccountPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Notification::fake();
    }

    private function customer(array $attrs = []): User
    {
        return User::factory()->create(array_merge(['name' => 'Clarissa Valery', 'phone' => '081234567890', 'password' => 'lama12345'], $attrs));
    }

    private function order(User $user, string $status): Order
    {
        return Order::create([
            'user_id' => $user->id, 'recipient_name' => 'Maya', 'recipient_phone' => '081234567890', 'delivery_city' => 'Jakarta',
            'delivery_address' => 'Jl. Mawar 1', 'delivery_date' => today()->addDay()->toDateString(), 'delivery_slot' => 'morning',
            'subtotal' => 450000, 'total_amount' => 450000, 'status' => $status,
            'payment_status' => $status === 'pending_payment' ? 'unpaid' : 'confirmed', 'paid_at' => $status === 'pending_payment' ? null : now(),
        ]);
    }

    public function test_guests_are_redirected_and_roles_use_their_own_area(): void
    {
        foreach (['/account', '/account/settings'] as $url) {
            $this->get($url)->assertRedirect('/login');
        }
        $this->get('/admin/account')->assertRedirect();

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get('/account')->assertRedirect('/admin/account');
        $this->get('/account/settings')->assertRedirect('/admin/account/settings');
        $this->put('/account', ['name' => 'X', 'email' => $admin->email])->assertForbidden();

        $this->actingAs($this->customer())->get('/admin/account')->assertForbidden();
    }

    public function test_customer_profile_page_and_update(): void
    {
        $user = $this->customer();
        $this->order($user, 'delivered');

        $this->actingAs($user)->get('/account')->assertOk()
            ->assertSee('Profil Saya')->assertSee('Member sejak')->assertSee('Rp 450.000')->assertSeeInOrder(['avatar-lg', 'CV'], false)
            ->assertSee('Momen Spesial &amp; Pengingat', false);
        $this->get('/home')->assertSee(route('account.profile'), false)->assertSee(route('account.settings'), false);

        $this->put('/account', ['name' => 'Clarissa V.', 'phone' => '0812-34', 'email' => $user->email])->assertSessionHasErrors('phone');
        $this->put('/account', ['name' => 'Clarissa V.', 'phone' => '081299990000', 'email' => $user->email])
            ->assertRedirect('/account')->assertSessionHas('success', 'Profil disimpan.');
        $this->assertSame(['Clarissa V.', '081299990000'], [$user->fresh()->name, $user->fresh()->phone]);
        Notification::assertNothingSent();
    }

    public function test_changing_email_requires_password_and_notifies_old_address(): void
    {
        $user = $this->customer(['email' => 'clarissa@lama.test']);
        $taken = User::factory()->create(['email' => 'sudah@dipakai.test']);
        $this->actingAs($user);
        $base = ['name' => $user->name, 'phone' => $user->phone];

        $this->put('/account', $base + ['email' => 'baru@flore.test'])->assertSessionHasErrors('current_password');
        $this->put('/account', $base + ['email' => 'baru@flore.test', 'current_password' => 'salah'])->assertSessionHasErrors('current_password');
        $this->put('/account', $base + ['email' => $taken->email, 'current_password' => 'lama12345'])->assertSessionHasErrors('email');
        $this->assertSame('clarissa@lama.test', $user->fresh()->email);

        $this->put('/account', $base + ['email' => 'Baru@Flore.test', 'current_password' => 'lama12345'])->assertSessionHas('success');
        $this->assertSame('baru@flore.test', $user->fresh()->email);
        Notification::assertSentOnDemand(EmailChanged::class, fn ($n, $channels, $notifiable) => $notifiable->routes['mail'] === 'clarissa@lama.test');

        $this->post('/logout');
        $this->post('/login', ['email' => 'baru@flore.test', 'password' => 'lama12345'])->assertRedirect('/home');
    }

    public function test_avatar_upload_replace_and_delete(): void
    {
        $user = $this->customer();
        $this->actingAs($user);

        $this->post('/account/avatar', ['avatar' => UploadedFile::fake()->create('cv.pdf', 10, 'application/pdf')])->assertSessionHasErrors('avatar');
        $this->post('/account/avatar', ['avatar' => UploadedFile::fake()->image('kecil.jpg', 20, 20)])->assertSessionHasErrors('avatar');

        $this->post('/account/avatar', ['avatar' => UploadedFile::fake()->image('foto.jpg', 900, 600)])->assertSessionHas('success');
        $first = $user->fresh()->avatar;
        Storage::disk('public')->assertExists($first);
        $this->assertSame([256, 256], array_slice(getimagesizefromstring(Storage::disk('public')->get($first)), 0, 2));
        $this->get('/account')->assertSee($user->fresh()->avatarUrl(), false);

        $this->post('/account/avatar', ['avatar' => UploadedFile::fake()->image('baru.png', 400, 400)]);
        Storage::disk('public')->assertMissing($first);   // foto lama dibuang

        $this->delete('/account/avatar')->assertSessionHas('success');
        $this->assertNull($user->fresh()->avatar);
    }

    public function test_settings_lists_sessions_and_revokes_them(): void
    {
        config(['session.driver' => 'database']);
        $user = $this->customer();
        $other = User::factory()->create();
        DB::table('sessions')->insert([
            ['id' => 'hp-android', 'user_id' => $user->id, 'ip_address' => '10.0.0.7', 'last_activity' => now()->subHour()->timestamp, 'payload' => '',
                'user_agent' => 'Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 Chrome/131.0 Mobile Safari/537.36'],
            ['id' => 'laptop-mac', 'user_id' => $user->id, 'ip_address' => '10.0.0.8', 'last_activity' => now()->subDay()->timestamp, 'payload' => '',
                'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_0) AppleWebKit/605.1.15 Version/17.0 Safari/605.1.15'],
            ['id' => 'milik-orang-lain', 'user_id' => $other->id, 'ip_address' => '10.0.0.9', 'last_activity' => now()->timestamp, 'payload' => '', 'user_agent' => 'x'],
        ]);

        $this->actingAs($user)->get('/account/settings')->assertOk()
            ->assertSee('Chrome di Android')->assertSee('Safari di macOS')->assertSee('10.0.0.7')->assertDontSee('10.0.0.9')
            ->assertSee(route('account.password.edit'), false)->assertSee('Hapus Akun');

        // tidak bisa mengeluarkan sesi milik user lain
        $this->delete('/account/sessions/milik-orang-lain')->assertSessionHas('error');
        $this->assertTrue(DB::table('sessions')->where('id', 'milik-orang-lain')->exists());

        $this->delete('/account/sessions/hp-android')->assertSessionHas('success');
        $this->assertFalse(DB::table('sessions')->where('id', 'hp-android')->exists());

        $this->post('/account/sessions/logout-others', ['password' => 'salah'])->assertSessionHasErrorsIn('sessions', 'password');
        $this->post('/account/sessions/logout-others', ['password' => 'lama12345'])->assertSessionHas('success');
        $this->assertFalse(DB::table('sessions')->where('id', 'laptop-mac')->exists());
        $this->assertTrue(DB::table('sessions')->where('id', 'milik-orang-lain')->exists());
    }

    public function test_customer_deletes_account_which_is_anonymized(): void
    {
        $user = $this->customer(['email' => 'clarissa@flore.test', 'date_of_birth' => '1999-05-01']);
        $user->specialMoments()->create(['type' => 'birthday', 'title' => 'Ulang Tahun Ibu', 'date' => '1965-12-05']);
        $active = $this->order($user, 'preparing');
        $this->actingAs($user);

        $this->get('/account/settings')->assertSee('1 pesanan yang sedang berjalan');
        $this->delete('/account', ['password' => 'lama12345', 'confirmation' => 'HAPUS'])->assertSessionHas('error');
        $this->assertSame('clarissa@flore.test', $user->fresh()->email);

        $active->update(['status' => 'delivered']);
        $this->delete('/account', ['password' => 'salah', 'confirmation' => 'hapus'])->assertSessionHasErrorsIn('deleteAccount', ['password', 'confirmation']);

        $this->delete('/account', ['password' => 'lama12345', 'confirmation' => 'HAPUS'])->assertRedirect('/')->assertSessionHas('success');
        $this->assertGuest();

        $user->refresh();
        $this->assertTrue($user->isDeleted());
        $this->assertSame('Pengguna dihapus', $user->name);
        $this->assertNull($user->phone);
        $this->assertNull($user->date_of_birth);
        $this->assertSame(0, $user->specialMoments()->count());
        $this->assertSame(1, $user->orders()->count());   // riwayat pesanan tetap ada untuk pembukuan
        $this->assertFalse(Hash::check('lama12345', $user->password));
        $this->post('/login', ['email' => 'clarissa@flore.test', 'password' => 'lama12345'])->assertSessionHasErrors('email');
        $this->assertTrue(User::where('email', 'clarissa@flore.test')->doesntExist());   // email bisa dipakai daftar lagi
    }

    public function test_admin_profile_and_settings(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'name' => 'Admin Floré', 'password' => 'adminLama2026!']);
        $this->actingAs($admin)->get('/admin/account')->assertOk()->assertSee('Profil Saya')->assertSee('Studio Settings (toko)');
        $this->get('/admin/dashboard')->assertSee(route('admin.account.profile'), false)->assertSee(route('admin.account.settings'), false);

        // no. HP admin opsional
        $this->put('/admin/account', ['name' => 'Elena Rostova', 'phone' => '', 'email' => $admin->email])->assertRedirect('/admin/account');
        $this->assertSame('Elena Rostova', $admin->fresh()->name);

        $this->post('/admin/account/avatar', ['avatar' => UploadedFile::fake()->image('a.jpg', 300, 300)])->assertSessionHas('success');
        $this->get('/admin/dashboard')->assertSee($admin->fresh()->avatarUrl(), false);

        $this->get('/admin/account/settings')->assertOk()->assertSee('Perangkat yang sedang login')->assertDontSee('Hapus Akun Permanen');
        $this->delete('/account', ['password' => 'adminLama2026!', 'confirmation' => 'HAPUS'])->assertForbidden();   // admin tidak bisa hapus akun
        $this->assertSame('admin', $admin->fresh()->role);
    }
}
