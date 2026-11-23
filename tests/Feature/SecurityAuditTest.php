<?php

namespace Tests\Feature;

use App\Models\Bouquet;
use App\Models\Category;
use App\Models\Order;
use App\Models\SpecialMoment;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Route as RouteDef;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Testing Phase — System Security Audit & Data Validation.
 *  A. CSRF Protection
 *  B. Authorization Middleware (role admin, login customer, kepemilikan data / IDOR)
 *  C. SQL Injection Prevention
 *  D. Data Validation (mass assignment, manipulasi harga, XSS, upload)
 *  E. Hardening (security headers, route storage)
 */
class SecurityAuditTest extends TestCase
{
    use RefreshDatabase;

    private const SQLI = ["' OR '1'='1", "1; DROP TABLE users; --", "x' UNION SELECT password FROM users --", '") OR 1=1 #'];

    private User $admin;
    private User $customer;
    private User $other;
    private Bouquet $bouquet;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->customer = User::factory()->create(['role' => 'customer', 'phone' => '081234567890']);
        $this->other = User::factory()->create(['role' => 'customer', 'phone' => '081298765432']);

        $cat = Category::create(['name' => 'Hand Bouquet', 'slug' => 'hand-bouquet', 'is_active' => true]);
        $this->bouquet = Bouquet::create(['category_id' => $cat->id, 'name' => 'Rose Love', 'slug' => 'rose-love', 'price' => 200000, 'stock' => 10]);
    }

    private function orderFor(User $user, array $attrs = []): Order
    {
        $order = Order::create(array_merge([
            'user_id' => $user->id, 'recipient_name' => 'Budi', 'recipient_phone' => '081234567890',
            'delivery_city' => 'Bandung', 'delivery_address' => 'Jl. Merdeka No. 10', 'delivery_date' => now()->addDays(2)->toDateString(),
            'delivery_slot' => 'morning', 'subtotal' => 200000, 'total_amount' => 200000,
            'status' => 'pending_payment', 'payment_status' => 'unpaid',
        ], $attrs));
        $order->items()->create(['bouquet_id' => $this->bouquet->id, 'bouquet_name' => 'Rose Love', 'price' => 200000, 'quantity' => 1, 'subtotal' => 200000]);

        return $order;
    }

    /** Semua route aplikasi (tanpa route bawaan framework). */
    private function appRoutes(): array
    {
        return collect(Route::getRoutes()->getRoutes())
            ->filter(fn (RouteDef $r) => str_starts_with($r->getActionName(), 'App\\') || $r->getActionName() === 'Closure')
            ->values()->all();
    }

    private function urlFor(RouteDef $route): string
    {
        return '/' . ltrim(preg_replace('/\{[^}]+\}/', '1', $route->uri()), '/');
    }

    // ================================================================ A. CSRF

    /** Laravel mematikan cek CSRF saat unit test; di sini dinyalakan seperti di production. */
    private function enableRealCsrf(): void
    {
        $this->app->bind(ValidateCsrfToken::class, fn ($app) => new class($app, $app['encrypter']) extends ValidateCsrfToken {
            protected function runningUnitTests()
            {
                return false;
            }
        });
    }

    public function test_every_state_changing_route_rejects_requests_without_csrf_token(): void
    {
        $this->enableRealCsrf();
        $checked = [];

        foreach ($this->appRoutes() as $route) {
            foreach (array_intersect($route->methods(), ['POST', 'PUT', 'PATCH', 'DELETE']) as $method) {
                $url = $this->urlFor($route);
                $status = $this->call($method, $url)->getStatusCode();
                $this->assertSame(419, $status, "{$method} {$url} tidak dilindungi CSRF (status {$status})");
                $checked[] = "{$method} {$url}";
            }
        }

        $this->assertGreaterThan(30, count($checked), 'Route POST/PUT/PATCH/DELETE yang diperiksa terlalu sedikit');
    }

    public function test_request_with_valid_csrf_token_is_accepted(): void
    {
        $this->enableRealCsrf();

        $this->get('/catalog');   // memulai sesi
        $token = session()->token();

        $this->post("/cart/items/{$this->bouquet->slug}", ['_token' => $token, 'quantity' => 1])
            ->assertRedirect()->assertSessionHas('success');

        // token palsu tetap ditolak
        $this->post("/cart/items/{$this->bouquet->slug}", ['_token' => 'token-palsu', 'quantity' => 1])->assertStatus(419);
    }

    public function test_every_form_in_views_has_csrf_field(): void
    {
        foreach (glob(resource_path('views/**/*.blade.php')) ?: [] as $file) {
            $html = file_get_contents($file);
            $forms = preg_match_all('/<form[^>]+method="POST"/i', $html);
            if (! $forms) {
                continue;
            }
            $tokens = preg_match_all('/@csrf|name="_token"/', $html) + (str_contains($html, "@include('admin.") ? 1 : 0);
            $this->assertGreaterThanOrEqual($forms, $tokens, basename($file) . ' punya form POST tanpa @csrf');
        }
    }

    // ================================================================ B. Authorization

    public function test_every_admin_controller_route_requires_login_and_admin_role(): void
    {
        $adminRoutes = collect($this->appRoutes())
            ->filter(fn (RouteDef $r) => str_starts_with($r->getActionName(), 'App\\Http\\Controllers\\Admin\\')
                && ! str_starts_with($r->getActionName(), 'App\\Http\\Controllers\\Admin\\AuthController'));

        $this->assertGreaterThan(25, $adminRoutes->count());

        foreach ($adminRoutes as $route) {
            $mw = $route->gatherMiddleware();
            $this->assertContains('auth', $mw, "{$route->uri()} tanpa middleware auth");
            $this->assertContains('role:admin', $mw, "{$route->uri()} tanpa middleware role:admin");
            $this->assertStringStartsWith('admin/', $route->uri(), "Route admin di luar prefix /admin: {$route->uri()}");
        }
    }

    public function test_guest_and_customer_are_blocked_from_every_admin_page(): void
    {
        $adminUris = collect($this->appRoutes())
            ->filter(fn (RouteDef $r) => str_starts_with($r->uri(), 'admin/') && ! str_starts_with($r->uri(), 'admin/login'));

        foreach ($adminUris as $route) {
            foreach (array_diff($route->methods(), ['HEAD']) as $method) {
                $url = $this->urlFor($route);

                $this->app['auth']->forgetGuards();
                $guest = $this->call($method, $url);
                $this->assertTrue($guest->isRedirect(route('admin.login')), "Tamu bisa {$method} {$url} (status {$guest->getStatusCode()})");

                $this->actingAs($this->customer);
                $this->assertSame(403, $this->call($method, $url)->getStatusCode(), "Customer bisa {$method} {$url}");
            }
        }
    }

    public function test_previously_unprotected_management_routes_no_longer_exist(): void
    {
        $this->post('/categories', ['name' => 'Hacked', 'slug' => 'hacked'])->assertNotFound();
        $this->delete('/categories/1')->assertNotFound();
        $this->put("/bouquet-images/{$this->bouquet->id}", ['image' => UploadedFile::fake()->create('x.jpg', 10, 'image/jpeg')])->assertNotFound();
        $this->delete("/bouquet-images/{$this->bouquet->id}")->assertNotFound();
        $this->get('/categories')->assertNotFound();

        $this->assertDatabaseMissing('categories', ['slug' => 'hacked']);
    }

    public function test_customer_pages_require_login(): void
    {
        $order = $this->orderFor($this->customer);

        foreach (['/home', '/profile', '/orders', "/orders/{$order->order_number}", "/orders/{$order->order_number}/proof", '/checkout', '/checkout/review'] as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }
        $this->post('/checkout/place')->assertRedirect(route('login'));
        $this->post("/orders/{$order->order_number}/cancel")->assertRedirect(route('login'));
    }

    public function test_customer_cannot_access_other_customers_data_idor(): void
    {
        $theirs = $this->orderFor($this->other, ['status' => 'delivered', 'payment_status' => 'confirmed', 'payment_proof' => 'payment-proofs/x.pdf']);
        Storage::disk('local')->put('payment-proofs/x.pdf', 'bukti');
        $moment = SpecialMoment::create(['user_id' => $this->other->id, 'type' => 'birthday', 'title' => 'Ibu', 'date' => '1965-01-01']);
        $n = $theirs->order_number;

        $this->actingAs($this->customer);
        $this->get("/orders/{$n}")->assertNotFound();
        $this->get("/orders/{$n}/proof")->assertNotFound();
        $this->post("/orders/{$n}/payment", ['payment_proof' => UploadedFile::fake()->create('b.pdf', 10, 'application/pdf')])->assertNotFound();
        $this->post("/orders/{$n}/cancel")->assertNotFound();
        $this->post("/orders/{$n}/testimonial", ['rating' => 5, 'content' => 'Bukan pesanan saya'])->assertNotFound();
        $this->delete("/profile/moments/{$moment->id}")->assertNotFound();
        $this->get('/orders')->assertDontSee($n);

        $this->assertSame('delivered', $theirs->fresh()->status);
        $this->assertModelExists($moment);
        $this->assertSame(0, Testimonial::count());
    }

    public function test_customer_credentials_cannot_log_into_admin_portal(): void
    {
        $user = User::factory()->create(['role' => 'customer', 'email' => 'c@example.com', 'password' => 'password']);

        $this->post('/admin/login', ['email' => 'c@example.com', 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    // ================================================================ C. SQL Injection

    public function test_sql_injection_payloads_in_search_and_filters_are_harmless(): void
    {
        $users = User::count();

        foreach (self::SQLI as $p) {
            $this->get('/catalog?' . http_build_query(['q' => $p, 'sort' => $p, 'occasion' => $p, 'category' => $p, 'price_min' => $p]))
                ->assertOk()->assertDontSee('Rose Love');   // payload tidak "mencocokkan semua" produk
            $this->getJson('/recommendations?' . http_build_query(['occasion' => $p, 'color' => $p, 'budget' => $p]))->assertOk();
        }

        $this->actingAs($this->admin);
        foreach (self::SQLI as $p) {
            $this->get('/admin/orders?' . http_build_query(['q' => $p, 'status' => $p, 'payment_status' => $p]))->assertOk();
            $this->get('/admin/reports/customers?' . http_build_query(['q' => $p, 'sort' => $p]))->assertOk()->assertSee('Tidak ada customer yang cocok');
            $this->get('/admin/reports/sales?' . http_build_query(['from' => $p, 'to' => $p, 'preset' => $p]))->assertOk();
            $this->get('/admin/bouquets?' . http_build_query(['q' => $p, 'category' => $p, 'stock' => $p]))->assertOk();
            $this->get('/admin/testimonials?' . http_build_query(['status' => $p]))->assertOk();
        }

        $this->assertTrue(Schema::hasTable('users'));
        $this->assertSame($users, User::count());
    }

    public function test_sql_injection_in_login_does_not_bypass_authentication(): void
    {
        foreach (self::SQLI as $p) {
            $this->post('/login', ['email' => $p, 'password' => $p]);
            $this->post('/login', ['email' => $this->customer->email, 'password' => $p]);
            $this->post('/admin/login', ['email' => $p, 'password' => $p]);
        }

        $this->assertGuest();
    }

    public function test_route_parameters_with_sql_payload_are_treated_as_plain_values(): void
    {
        $this->get('/catalog/' . rawurlencode("rose-love' OR '1'='1"))->assertNotFound();
        $this->actingAs($this->customer)->get('/orders/' . rawurlencode("x' OR 1=1 --"))->assertNotFound();
    }

    public function test_query_log_uses_bindings_for_user_input(): void
    {
        DB::enableQueryLog();
        $this->get('/catalog?q=' . urlencode("' OR '1'='1"));
        $queries = collect(DB::getQueryLog());

        $this->assertTrue($queries->contains(fn ($q) => in_array("%' OR '1'='1%", $q['bindings'], true)), 'Input pencarian tidak dikirim sebagai binding');
        $this->assertFalse($queries->contains(fn ($q) => str_contains($q['query'], "OR '1'='1")), 'Input pencarian masuk mentah ke SQL');
    }

    // ================================================================ D. Data Validation

    public function test_register_cannot_assign_admin_role(): void
    {
        $this->post('/register', [
            'name' => 'Penyusup', 'email' => 'p@example.com', 'phone' => '081234567890',
            'password' => 'password123', 'password_confirmation' => 'password123', 'role' => 'admin',
        ]);

        $this->assertSame('customer', User::where('email', 'p@example.com')->value('role'));
    }

    public function test_profile_update_ignores_role_and_email_fields(): void
    {
        $email = $this->customer->email;

        $this->actingAs($this->customer)->put('/profile', [
            'name' => 'Clarissa', 'phone' => '081234567890', 'reminder_days' => 7,
            'role' => 'admin', 'email' => 'ganti@example.com',
        ])->assertSessionHasNoErrors();

        $fresh = $this->customer->fresh();
        $this->assertSame('customer', $fresh->role);
        $this->assertSame($email, $fresh->email);
    }

    public function test_order_total_uses_database_price_not_request_input(): void
    {
        $this->actingAs($this->customer);
        $this->post("/cart/items/{$this->bouquet->slug}", ['quantity' => 2, 'price' => 1]);
        $this->post('/checkout', [
            'recipient_name' => 'Budi', 'recipient_phone' => '081234567890', 'delivery_city' => 'Bandung',
            'delivery_address' => 'Jl. Merdeka No. 10', 'delivery_date' => now()->addDays(2)->toDateString(),
            'delivery_slot' => 'morning', 'total_amount' => 1, 'subtotal' => 1, 'status' => 'delivered',
        ]);
        $this->post('/checkout/place', ['total_amount' => 1]);

        $order = Order::where('user_id', $this->customer->id)->firstOrFail();
        $this->assertEquals(400000, $order->total_amount);
        $this->assertSame('pending_payment', $order->status);
    }

    public function test_quantity_and_stock_limits_are_enforced(): void
    {
        $this->post("/cart/items/{$this->bouquet->slug}", ['quantity' => -5])->assertSessionHasErrors('quantity');
        $this->post("/cart/items/{$this->bouquet->slug}", ['quantity' => 'abc'])->assertSessionHasErrors('quantity');
        $this->post("/cart/items/{$this->bouquet->slug}", ['quantity' => 999])->assertSessionHasErrors('quantity');
    }

    public function test_user_input_is_html_escaped_xss(): void
    {
        $xss = '<script>alert("xss")</script>';
        $order = $this->orderFor($this->customer, ['status' => 'delivered', 'payment_status' => 'confirmed', 'card_message' => $xss, 'recipient_name' => $xss]);
        Testimonial::create(['user_id' => $this->customer->id, 'order_id' => $order->id, 'rating' => 5, 'content' => $xss, 'is_approved' => true, 'approved_at' => now()]);
        $this->customer->update(['name' => $xss]);

        $this->get('/')->assertDontSee($xss, false)->assertSee('&lt;script&gt;', false);
        $this->actingAs($this->customer)->get("/orders/{$order->order_number}")->assertDontSee($xss, false);
        $this->actingAs($this->admin)->get('/admin/reports/customers')->assertDontSee($xss, false);
        $this->get("/admin/orders/{$order->id}")->assertDontSee($xss, false);
        $this->get('/admin/testimonials?status=approved')->assertDontSee($xss, false);
    }

    public function test_dangerous_file_uploads_are_rejected(): void
    {
        $order = $this->orderFor($this->customer);
        $this->actingAs($this->customer);

        foreach ([['shell.php', 'application/x-php'], ['bukti.exe', 'application/octet-stream'], ['bukti.html', 'text/html']] as [$name, $mime]) {
            $this->post("/orders/{$order->order_number}/payment", ['payment_proof' => UploadedFile::fake()->create($name, 5, $mime)])
                ->assertSessionHasErrors('payment_proof');
        }
        $this->post("/orders/{$order->order_number}/payment", ['payment_proof' => UploadedFile::fake()->create('besar.pdf', 5000, 'application/pdf')])
            ->assertSessionHasErrors('payment_proof');   // > 3 MB

        $this->actingAs($this->admin);
        foreach ([['logo.svg', 'image/svg+xml'], ['shell.php', 'application/x-php']] as [$name, $mime]) {
            $this->put("/admin/bouquet-images/{$this->bouquet->id}", ['image' => UploadedFile::fake()->create($name, 5, $mime)])
                ->assertSessionHasErrors('image');
        }

        $this->assertSame('unpaid', $order->fresh()->payment_status);
        $this->assertNull($this->bouquet->fresh()->image);
    }

    // ================================================================ E. Hardening

    public function test_security_headers_are_sent(): void
    {
        $this->get('/')->assertOk()
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_private_storage_is_not_served_directly(): void
    {
        Storage::disk('local')->put('payment-proofs/rahasia.pdf', 'bukti');

        $this->get('/storage/payment-proofs/rahasia.pdf')->assertNotFound();
    }

    public function test_passwords_are_hashed(): void
    {
        $this->post('/register', [
            'name' => 'Baru', 'email' => 'baru@example.com', 'phone' => '081234567890',
            'password' => 'rahasia123', 'password_confirmation' => 'rahasia123',
        ]);

        $hash = User::where('email', 'baru@example.com')->value('password');
        $this->assertNotSame('rahasia123', $hash);
        $this->assertStringStartsWith('$2y$', $hash);   // bcrypt
    }
}
