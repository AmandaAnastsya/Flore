<?php

namespace Tests\Feature;

use App\Models\Bouquet;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminBouquetTest extends TestCase
{
    use RefreshDatabase;

    private function category(): Category
    {
        return Category::firstOrCreate(['slug' => 'hand-bouquet'], ['name' => 'Hand Bouquet']);
    }

    private function make(string $name, int $stock = 10, int $threshold = 5): Bouquet
    {
        return Bouquet::create([
            'category_id' => $this->category()->id, 'name' => $name,
            'slug' => str($name)->slug(), 'price' => 100000,
            'stock' => $stock, 'low_stock_threshold' => $threshold,
        ]);
    }

    private function payload(array $override = []): array
    {
        return array_merge([
            'category_id' => $this->category()->id, 'name' => 'Rose Love', 'price' => 250000,
            'stock' => 5, 'low_stock_threshold' => 3, 'size' => 'small',
            'occasion' => 'birthday', 'color' => 'pink', 'style' => 'modern', 'is_active' => 1,
        ], $override);
    }

    private function fakePng(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('a.png', base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
        ));
    }

    private function admin(): static
    {
        return $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    public function test_customer_cannot_access_bouquet_pages(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'customer']))
            ->get('/admin/bouquets')->assertForbidden();
    }

    public function test_stock_status_levels(): void
    {
        $this->assertSame('out', (new Bouquet(['stock' => 0, 'low_stock_threshold' => 5]))->stock_status);
        $this->assertSame('low', (new Bouquet(['stock' => 5, 'low_stock_threshold' => 5]))->stock_status);
        $this->assertSame('low', (new Bouquet(['stock' => 1, 'low_stock_threshold' => 5]))->stock_status);
        $this->assertSame('ok', (new Bouquet(['stock' => 6, 'low_stock_threshold' => 5]))->stock_status);
    }

    public function test_admin_can_create_bouquet_with_image(): void
    {
        Storage::fake('public');

        $this->admin()->post('/admin/bouquets', $this->payload(['image' => $this->fakePng()]))
            ->assertRedirect('/admin/bouquets');

        $bouquet = Bouquet::where('slug', 'rose-love')->firstOrFail();
        $this->assertSame(5, $bouquet->stock);
        Storage::disk('public')->assertExists($bouquet->image);
    }

    public function test_create_validates_input(): void
    {
        $this->admin();

        $this->post('/admin/bouquets', $this->payload(['size' => 'giant']))->assertSessionHasErrors('size');
        $this->post('/admin/bouquets', $this->payload(['stock' => -1]))->assertSessionHasErrors('stock');
        $this->post('/admin/bouquets', $this->payload(['category_id' => 999]))->assertSessionHasErrors('category_id');
        $this->post('/admin/bouquets', $this->payload(['name' => '']))->assertSessionHasErrors('name');
        $this->post('/admin/bouquets', $this->payload(['color' => 'green']))->assertSessionHasErrors('color');
    }

    public function test_same_name_gets_unique_slug(): void
    {
        $this->admin();

        $this->post('/admin/bouquets', $this->payload());
        $this->post('/admin/bouquets', $this->payload());

        $this->assertDatabaseHas('bouquets', ['slug' => 'rose-love']);
        $this->assertDatabaseHas('bouquets', ['slug' => 'rose-love-2']);
    }

    public function test_admin_can_update_and_replace_image(): void
    {
        Storage::fake('public');
        $this->admin();
        $this->post('/admin/bouquets', $this->payload(['image' => $this->fakePng()]));
        $bouquet = Bouquet::firstOrFail();
        $oldImage = $bouquet->image;

        $this->put("/admin/bouquets/{$bouquet->id}", $this->payload(['name' => 'Rose Forever', 'image' => $this->fakePng()]))
            ->assertRedirect('/admin/bouquets');

        $bouquet->refresh();
        $this->assertSame('rose-forever', $bouquet->slug);
        Storage::disk('public')->assertMissing($oldImage);
        Storage::disk('public')->assertExists($bouquet->image);
    }

    public function test_admin_can_adjust_stock_and_negative_is_rejected(): void
    {
        $bouquet = $this->make('Tes');
        $this->admin();

        $this->patch("/admin/bouquets/{$bouquet->id}/stock", ['stock' => 20])->assertSessionHasNoErrors();
        $this->assertSame(20, $bouquet->fresh()->stock);

        $this->patch("/admin/bouquets/{$bouquet->id}/stock", ['stock' => -3])->assertSessionHasErrors('stock');
        $this->assertSame(20, $bouquet->fresh()->stock);
    }

    public function test_filters_by_stock_and_search(): void
    {
        $this->make('Aman Bunga', 20, 5);
        $this->make('Tipis Bunga', 3, 5);
        $this->make('Kosong Bunga', 0, 5);
        $this->admin();

        $this->get('/admin/bouquets?stock=low')
            ->assertSee('Tipis Bunga')->assertDontSee('Aman Bunga')->assertDontSee('Kosong Bunga');
        $this->get('/admin/bouquets?stock=out')
            ->assertSee('Kosong Bunga')->assertDontSee('Tipis Bunga');
        $this->get('/admin/bouquets?q=Aman')
            ->assertSee('Aman Bunga')->assertDontSee('Tipis Bunga');
    }

    public function test_delete_is_soft_delete(): void
    {
        $bouquet = $this->make('Hapus Saya');
        $this->admin();

        $this->delete("/admin/bouquets/{$bouquet->id}")->assertRedirect('/admin/bouquets');
        $this->assertSoftDeleted($bouquet);
    }

    public function test_dashboard_shows_low_stock_warning(): void
    {
        $this->make('Hampir Habis', 2, 5);

        $this->admin()->get('/admin/dashboard')->assertOk()->assertSee('Hampir Habis');
    }
}