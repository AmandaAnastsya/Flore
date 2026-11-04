<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_admin_login(): void
    {
        $this->get('/admin/dashboard')->assertRedirect(route('admin.login'));
    }

    public function test_customer_gets_403_on_admin_pages(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $this->actingAs($customer)->get('/admin/dashboard')->assertForbidden();
    }

    public function test_admin_can_login_and_open_dashboard(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'password' => 'password']);

        $this->post('/admin/login', ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect(route('admin.dashboard'));

        $this->get('/admin/dashboard')->assertOk();
    }

    public function test_customer_cannot_login_through_admin_login(): void
    {
        $customer = User::factory()->create(['role' => 'customer', 'password' => 'password']);

        $this->post('/admin/login', ['email' => $customer->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }
}