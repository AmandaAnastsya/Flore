<?php

namespace Tests\Feature;

use App\Models\Bouquet;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Alur lintas modul: perubahan oleh admin harus langsung terlihat
 * di katalog, halaman detail, keranjang, dan dashboard.
 */
class StockIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function make(string $name, array $attrs = []): Bouquet
    {
        $cat = Category::firstOrCreate(['slug' => 'hand-bouquet'], ['name' => 'Hand Bouquet']);

        return Bouquet::create(array_merge([
            'category_id' => $cat->id, 'name' => $name, 'slug' => str($name)->slug(),
            'price' => 100000, 'stock' => 10, 'low_stock_threshold' => 5, 'is_active' => true,
        ], $attrs))->fresh();
    }

    /** Payload form admin yang valid; is_active=1 kecuali di-override. */
    private function payload(Bouquet $b, array $override = []): array
    {
        return array_merge([
            'category_id' => $b->category_id, 'name' => $b->name, 'price' => (int) $b->price,
            'stock' => $b->stock, 'low_stock_threshold' => $b->low_stock_threshold,
            'size' => $b->size, 'occasion' => $b->occasion, 'is_active' => 1,
        ], $override);
    }

    private function addToCart(Bouquet $b, int $qty = 1)
    {
        return $this->post("/cart/items/{$b->slug}", ['quantity' => $qty]);
    }

    private function fakePng(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('a.png', base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
        ));
    }

    public function test_stock_lifecycle_flows_to_catalog_cart_and_dashboard(): void
    {
        $b = $this->make('Rose Love');
        $this->get('/catalog')->assertSee('Rose Love')->assertDontSee('Sisa ')->assertDontSee('<span class="tag tag-out">', false);

        $this->actingAs($this->admin());

        // admin menurunkan stok ke 3 (batas menipis 5)
        $this->patch("/admin/bouquets/{$b->id}/stock", ['stock' => 3]);
        $this->get('/catalog')->assertSee('Sisa 3');
        $this->get('/catalog/rose-love')->assertSee('Sisa 3');
        $this->get('/admin/dashboard')->assertSee('Peringatan stok')->assertSee('Rose Love');

        // admin menghabiskan stok
        $this->patch("/admin/bouquets/{$b->id}/stock", ['stock' => 0]);
        $this->get('/catalog')->assertSee('<span class="tag tag-out">Habis</span>', false);
        $this->get('/catalog?in_stock=1')->assertDontSee('Rose Love');
        $this->get('/catalog/rose-love')->assertSee('Stok habis');
        $this->addToCart($b)->assertSessionHas('error');
        $this->assertEmpty(session('cart', []));

        // admin menambah stok lagi
        $this->patch("/admin/bouquets/{$b->id}/stock", ['stock' => 20]);
        $this->get('/catalog')->assertDontSee('<span class="tag tag-out">', false)->assertDontSee('Sisa ');
        $this->addToCart($b)->assertSessionHas('success');
        $this->get('/admin/dashboard')->assertDontSee('Peringatan stok');
    }

    public function test_cart_follows_stock_changes_made_by_admin(): void
    {
        $b = $this->make('Rose Love', ['stock' => 10]);
        $this->addToCart($b, 5);

        $this->actingAs($this->admin());

        $this->patch("/admin/bouquets/{$b->id}/stock", ['stock' => 2]);
        $this->get('/cart')->assertSee('disesuaikan menjadi 2')->assertSee('Rp 200.000');
        $this->assertSame([$b->id => 2], session('cart'));

        $this->patch("/admin/bouquets/{$b->id}/stock", ['stock' => 0]);
        $this->get('/cart')->assertSee('sedang habis')->assertSee('Keranjangmu masih kosong');
    }

    public function test_deactivating_bouquet_hides_it_everywhere(): void
    {
        $b = $this->make('Rose Love');
        $this->addToCart($b);

        $this->actingAs($this->admin());
        $this->put("/admin/bouquets/{$b->id}", $this->payload($b, ['is_active' => 0]));

        $this->get('/catalog')->assertDontSee('Rose Love');
        $this->get('/catalog/rose-love')->assertNotFound();
        $this->addToCart($b)->assertNotFound();
        $this->get('/cart')->assertSee('tidak lagi tersedia');
        $this->assertEmpty(session('cart', []));
    }

    public function test_deactivating_category_hides_all_its_bouquets(): void
    {
        $a = $this->make('Aaa Bunga');
        $this->make('Bbb Bunga');
        $this->addToCart($a);

        $this->actingAs($this->admin());
        // is_active tidak dikirim = dinonaktifkan
        $this->put("/admin/categories/{$a->category_id}", ['name' => 'Hand Bouquet']);

        $this->get('/catalog')->assertDontSee('Aaa Bunga')->assertDontSee('Bbb Bunga');
        $this->get('/catalog/aaa-bunga')->assertNotFound();
        $this->get('/cart')->assertSee('tidak lagi tersedia');
        $this->assertEmpty(session('cart', []));
    }

    public function test_soft_deleted_bouquet_disappears_but_can_still_be_removed_from_cart(): void
    {
        $b = $this->make('Rose Love');
        $this->addToCart($b);

        $this->actingAs($this->admin());
        $this->delete("/admin/bouquets/{$b->id}");

        $this->get('/catalog')->assertDontSee('Rose Love');
        $this->delete("/cart/items/{$b->slug}")->assertSessionHas('success');
        $this->assertEmpty(session('cart', []));
    }

    public function test_price_change_updates_catalog_and_cart_subtotal(): void
    {
        $b = $this->make('Rose Love', ['price' => 100000]);
        $this->addToCart($b, 2);
        $this->get('/cart')->assertSee('Rp 200.000');

        $this->actingAs($this->admin());
        $this->put("/admin/bouquets/{$b->id}", $this->payload($b, ['price' => 150000]));

        $this->get('/cart')->assertSee('Rp 300.000');
        $this->get('/catalog')->assertSee('Rp 150.000');
    }

    public function test_image_upload_shows_in_catalog_and_cart(): void
    {
        Storage::fake('public');
        $b = $this->make('Rose Love');
        $this->addToCart($b);
        $this->get('/catalog')->assertDontSee('/storage/bouquets/', false);

        $this->actingAs($this->admin());
        $this->put("/admin/bouquet-images/{$b->id}", ['image' => $this->fakePng()]);

        $this->get('/catalog')->assertSee('/storage/bouquets/', false);
        $this->get('/cart')->assertSee('/storage/bouquets/', false);
    }

    public function test_attribute_and_category_edits_reflect_in_catalog_filters_and_cart(): void
    {
        $b = $this->make('Rose Love', ['occasion' => 'anniversary']);
        $this->addToCart($b);
        $this->get('/catalog?occasion[]=birthday')->assertDontSee('Rose Love');

        $this->actingAs($this->admin());
        $this->put("/admin/bouquets/{$b->id}", $this->payload($b, ['occasion' => 'birthday']));
        $this->get('/catalog?occasion[]=birthday')->assertSee('Rose Love');

        $this->put("/admin/categories/{$b->category_id}", ['name' => 'Bloom Premium', 'is_active' => 1]);
        $this->get('/catalog')->assertSee('Bloom Premium');
        $this->get('/cart')->assertSee('Bloom Premium');
    }

    public function test_new_bouquet_created_by_admin_appears_in_catalog_filters(): void
    {
        $existing = $this->make('Lama Bunga');

        $this->actingAs($this->admin());
        $this->post('/admin/bouquets', [
            'category_id' => $existing->category_id, 'name' => 'Baru Bunga', 'price' => 400000,
            'stock' => 4, 'low_stock_threshold' => 5, 'size' => 'small', 'occasion' => 'wedding', 'is_active' => 1,
        ])->assertRedirect('/admin/bouquets');

        $this->get('/catalog?occasion[]=wedding')
            ->assertSee('Baru Bunga')->assertSee('Sisa 4')->assertDontSee('Lama Bunga');
    }

    public function test_low_stock_threshold_change_flips_the_badge(): void
    {
        $b = $this->make('Rose Love', ['stock' => 8, 'low_stock_threshold' => 10]);
        $this->get('/catalog')->assertSee('Sisa 8');

        $this->actingAs($this->admin());
        $this->put("/admin/bouquets/{$b->id}", $this->payload($b, ['low_stock_threshold' => 5]));

        $this->get('/catalog')->assertDontSee('Sisa 8');
    }

    public function test_renaming_a_bouquet_does_not_break_an_existing_cart(): void
    {
        $b = $this->make('Rose Love');
        $this->addToCart($b, 2);

        $this->actingAs($this->admin());
        $this->put("/admin/bouquets/{$b->id}", $this->payload($b, ['name' => 'Rose Forever']));

        $this->get('/cart')->assertSee('Rose Forever');
        $slug = $b->fresh()->slug;
        $this->patch("/cart/items/{$slug}", ['quantity' => 3])->assertSessionHas('success');
        $this->assertSame([$b->id => 3], session('cart'));
    }

    public function test_admin_stock_endpoint_is_protected_but_public_pages_stay_open(): void
    {
        $b = $this->make('Rose Love');

        $this->patch("/admin/bouquets/{$b->id}/stock", ['stock' => 99])->assertRedirect(route('admin.login'));

        $this->actingAs(User::factory()->create(['role' => 'customer']));
        $this->patch("/admin/bouquets/{$b->id}/stock", ['stock' => 99])->assertForbidden();
        $this->assertSame(10, $b->fresh()->stock);

        $this->get('/catalog')->assertOk();
        $this->get('/cart')->assertOk();
    }
}