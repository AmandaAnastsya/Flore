<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\PasswordChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Ganti password untuk semua user: customer (/account/password) & admin (/admin/account/password). */
class ChangePasswordTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    public function test_guest_must_login(): void
    {
        $this->get('/account/password')->assertRedirect('/login');
        $this->put('/account/password', [])->assertRedirect('/login');
        $this->get('/admin/account/password')->assertRedirect();
    }

    public function test_customer_changes_password(): void
    {
        $user = User::factory()->create(['password' => 'lama12345']);

        $this->actingAs($user)->get('/account/password')->assertOk()
            ->assertSee('Ganti Password')->assertSee('Minimal 8 karakter')->assertSee('autocomplete="current-password"', false);
        $this->get('/account/settings')->assertSee(route('account.password.edit'), false);
        $this->get('/account/settings')->assertSee(route('account.password.edit'), false);

        $this->put('/account/password', [
            'current_password' => 'lama12345', 'password' => 'mawarPink2026', 'password_confirmation' => 'mawarPink2026',
        ])->assertRedirect('/account/password')->assertSessionHas('success');

        $this->assertTrue(Hash::check('mawarPink2026', $user->fresh()->password));
        Notification::assertSentTo($user, PasswordChanged::class);

        // login dengan password baru berhasil, password lama ditolak
        $this->post('/logout');
        $this->post('/login', ['email' => $user->email, 'password' => 'lama12345'])->assertSessionHasErrors('email');
        $this->post('/login', ['email' => $user->email, 'password' => 'mawarPink2026'])->assertRedirect('/home');
    }

    public function test_validation_rules(): void
    {
        $user = User::factory()->create(['password' => 'lama12345']);
        $this->actingAs($user);
        $hash = $user->password;

        $cases = [
            [['current_password' => 'salah123', 'password' => 'mawarPink2026', 'password_confirmation' => 'mawarPink2026'], 'current_password', 'Password saat ini salah.'],
            [['current_password' => 'lama12345', 'password' => 'abc12', 'password_confirmation' => 'abc12'], 'password', null],
            [['current_password' => 'lama12345', 'password' => 'hanyahuruf', 'password_confirmation' => 'hanyahuruf'], 'password', 'Password baru harus mengandung minimal satu angka.'],
            [['current_password' => 'lama12345', 'password' => '1234567890', 'password_confirmation' => '1234567890'], 'password', 'Password baru harus mengandung minimal satu huruf.'],
            [['current_password' => 'lama12345', 'password' => 'mawarPink2026', 'password_confirmation' => 'beda2026'], 'password', 'Konfirmasi password baru tidak cocok.'],
            [['current_password' => 'lama12345', 'password' => 'lama12345', 'password_confirmation' => 'lama12345'], 'password', 'Password baru harus berbeda dari password saat ini.'],
        ];

        foreach ($cases as [$payload, $field, $message]) {
            $res = $this->from('/account/password')->put('/account/password', $payload)->assertRedirect('/account/password');
            $message ? $res->assertSessionHasErrors([$field => $message]) : $res->assertSessionHasErrors($field);
        }

        $this->assertSame($hash, $user->fresh()->password);
        Notification::assertNothingSent();
    }

    public function test_admin_uses_admin_page_with_longer_minimum(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'password' => 'adminLama2026!']);

        $this->actingAs($admin)->get('/account/password')->assertRedirect('/admin/account/password');
        $this->get('/admin/account/password')->assertOk()->assertSee('Akun & Password', false)->assertSee('Minimal 12 karakter');
        $this->get('/admin/dashboard')->assertSee(route('admin.password.edit'), false);

        $this->put('/admin/account/password', ['current_password' => 'adminLama2026!', 'password' => 'pendek12', 'password_confirmation' => 'pendek12'])
            ->assertSessionHasErrors('password');
        $this->put('/account/password', ['current_password' => 'adminLama2026!', 'password' => 'adminBaru2026xyz', 'password_confirmation' => 'adminBaru2026xyz'])
            ->assertForbidden();   // admin tidak lewat halaman customer

        $this->put('/admin/account/password', ['current_password' => 'adminLama2026!', 'password' => 'adminBaru2026xyz', 'password_confirmation' => 'adminBaru2026xyz'])
            ->assertRedirect('/admin/account/password')->assertSessionHas('success');
        $this->assertTrue(Hash::check('adminBaru2026xyz', $admin->fresh()->password));

        // customer tidak bisa membuka halaman admin
        $this->actingAs(User::factory()->create())->get('/admin/account/password')->assertForbidden();
    }

    public function test_other_sessions_and_remember_token_are_revoked(): void
    {
        config(['session.driver' => 'database']);
        $user = User::factory()->create(['password' => 'lama12345', 'remember_token' => 'token-lama']);
        $other = User::factory()->create();
        DB::table('sessions')->insert([
            ['id' => 'laptop-lain', 'user_id' => $user->id, 'ip_address' => '10.0.0.2', 'user_agent' => 'x', 'payload' => '', 'last_activity' => time()],
            ['id' => 'user-lain', 'user_id' => $other->id, 'ip_address' => '10.0.0.3', 'user_agent' => 'x', 'payload' => '', 'last_activity' => time()],
        ]);

        $this->actingAs($user)->put('/account/password', [
            'current_password' => 'lama12345', 'password' => 'mawarPink2026', 'password_confirmation' => 'mawarPink2026',
        ])->assertSessionHas('success');

        $this->assertFalse(DB::table('sessions')->where('id', 'laptop-lain')->exists());
        $this->assertTrue(DB::table('sessions')->where('id', 'user-lain')->exists());
        $this->assertNotSame('token-lama', $user->fresh()->remember_token);
    }

    public function test_update_is_rate_limited(): void
    {
        $this->actingAs(User::factory()->create(['password' => 'lama12345']));
        for ($i = 0; $i < 6; $i++) {
            $this->put('/account/password', ['current_password' => 'salah' . $i, 'password' => 'x', 'password_confirmation' => 'x']);
        }
        $this->put('/account/password', ['current_password' => 'lama12345', 'password' => 'mawarPink2026', 'password_confirmation' => 'mawarPink2026'])
            ->assertStatus(429);
    }
}
