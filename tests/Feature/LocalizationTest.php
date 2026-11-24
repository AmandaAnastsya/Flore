<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Temuan UAT: pesan error login & validasi harus berbahasa Indonesia, sama seperti UI. */
class LocalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_failed_login_message_is_indonesian(): void
    {
        User::factory()->create(['email' => 'sarah@example.com']);

        $this->from('/login')->post('/login', ['email' => 'sarah@example.com', 'password' => 'salah-password'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['email' => 'Email atau password yang Anda masukkan salah.']);
    }

    /** Temuan UAT A01: admin tidak boleh dilempar ke halaman toko (mis. /checkout) yang tadi diminta sebagai tamu. */
    public function test_admin_login_ignores_storefront_intended_url_but_keeps_admin_one(): void
    {
        User::factory()->create(['email' => 'owner@flore.test', 'role' => 'admin']);

        $this->get('/checkout')->assertRedirect('/login');
        $this->post('/admin/login', ['email' => 'owner@flore.test', 'password' => 'password'])
            ->assertRedirect(route('admin.dashboard'));
        $this->post('/logout');

        $this->get('/admin/reports/sales')->assertRedirect();
        $this->post('/admin/login', ['email' => 'owner@flore.test', 'password' => 'password'])
            ->assertRedirect(url('/admin/reports/sales'));
        $this->post('/logout');

        $this->get('/checkout');
        $this->post('/login', ['email' => 'owner@flore.test', 'password' => 'password'])
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_registration_validation_messages_are_indonesian(): void
    {
        $this->from('/register')->post('/register', ['name' => '', 'email' => 'bukan-email', 'password' => '123', 'password_confirmation' => '456'])
            ->assertSessionHasErrors([
                'name' => 'Nama wajib diisi.',
                'email' => 'Email harus berupa alamat email yang valid.',
            ]);

        $this->followingRedirects()->from('/register')->post('/register', ['email' => 'bukan-email'])
            ->assertSee('wajib diisi')
            ->assertDontSee('field is required');
    }
}
