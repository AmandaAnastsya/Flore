<?php

namespace Tests\Feature;

use App\Models\Bouquet;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_login_via_customer_form_goes_to_dashboard(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'password' => 'password']);

        $this->post('/login', ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_customer_login_still_goes_to_home(): void
    {
        $customer = User::factory()->create(['role' => 'customer', 'password' => 'password']);

        $this->post('/login', ['email' => $customer->email, 'password' => 'password'])
            ->assertRedirect('/home');
    }

    public function test_admin_returns_to_the_admin_page_they_originally_wanted(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'password' => 'password']);

        $this->get('/admin/bouquets');   // tamu ditolak, tujuan asal disimpan
        $this->post('/login', ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect('/admin/bouquets');
    }

    public function test_home_redirects_admin_to_dashboard_but_not_customer(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get('/home')->assertRedirect(route('admin.dashboard'));

        $this->actingAs(User::factory()->create(['role' => 'customer']))
            ->get('/home')->assertSuccessful()->assertDontSee('Dashboard Admin');
    }

    public function test_every_admin_page_has_a_back_to_dashboard_link(): void
    {
        $category = Category::create(['name' => 'Hand Bouquet', 'slug' => 'hand-bouquet']);
        $bouquet = Bouquet::create([
            'category_id' => $category->id, 'name' => 'Rose Love', 'slug' => 'rose-love', 'price' => 100000,
        ]);

        $this->actingAs(User::factory()->create(['role' => 'admin']));

        foreach ([
            '/admin/categories', '/admin/categories/create', "/admin/categories/{$category->id}/edit",
            '/admin/bouquets', '/admin/bouquets/create', "/admin/bouquets/{$bouquet->id}/edit",
            '/admin/bouquet-images', "/admin/bouquet-images/{$bouquet->id}/edit",
        ] as $url) {
            $this->get($url)->assertOk()
                ->assertSee('&larr; Dashboard', false)
                ->assertSee('href="' . route('admin.dashboard') . '"', false);
        }
    }

    public function test_navbar_shows_admin_dashboard_link_only_for_admins(): void
    {
        $this->get('/catalog')->assertDontSee('Dashboard Admin');

        $this->actingAs(User::factory()->create(['role' => 'customer']))
            ->get('/catalog')->assertDontSee('Dashboard Admin');

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get('/catalog')->assertSee('Dashboard Admin');
    }
}