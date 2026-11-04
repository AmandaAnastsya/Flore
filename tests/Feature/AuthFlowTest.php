<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthFlowTest extends TestCase
{
    use RefreshDatabase;

    private function validData(array $override = []): array
    {
        return array_merge([
            'name' => 'Amanda',
            'email' => 'amanda@example.com',
            'phone' => '081234567890',
            'date_of_birth' => '2000-05-17',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
        ], $override);
    }

    public function test_customer_can_register_with_bcrypt_password(): void
    {
        $this->post('/register', $this->validData())->assertRedirect();

        $user = User::where('email', 'amanda@example.com')->first();
        $this->assertNotNull($user);
        $this->assertSame('customer', $user->role);
        $this->assertSame('081234567890', $user->phone);
        $this->assertStringStartsWith('$2y$', $user->password);
        $this->assertTrue(Hash::check('rahasia123', $user->password));
        $this->assertAuthenticatedAs($user);
    }

    public function test_role_cannot_be_injected_during_register(): void
    {
        $this->post('/register', $this->validData(['role' => 'admin']));

        $this->assertSame('customer', User::first()->role);
    }

    public function test_register_rejects_duplicate_email_and_short_password(): void
    {
        User::factory()->create(['email' => 'amanda@example.com']);

        $this->post('/register', $this->validData())->assertSessionHasErrors('email');
        $this->post('/register', $this->validData(['email' => 'baru@example.com', 'password' => '123', 'password_confirmation' => '123']))
            ->assertSessionHasErrors('password');
    }

    public function test_register_rejects_invalid_phone(): void
    {
        $this->post('/register', $this->validData(['phone' => 'abc']))->assertSessionHasErrors('phone');
    }

    public function test_customer_can_login_and_wrong_password_fails(): void
    {
        $user = User::factory()->create(['password' => 'rahasia123']);

        $this->post('/login', ['email' => $user->email, 'password' => 'salah'])->assertSessionHasErrors();
        $this->assertGuest();

        $this->post('/login', ['email' => $user->email, 'password' => 'rahasia123'])->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }
}