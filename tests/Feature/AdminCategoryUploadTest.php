<?php

namespace Tests\Feature;

use App\Models\Bouquet;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminCategoryUploadTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function bouquet(): Bouquet
    {
        $cat = Category::create(['name' => 'Hand Bouquet', 'slug' => 'hand-bouquet']);

        return Bouquet::create([
            'category_id' => $cat->id, 'name' => 'Tes', 'slug' => 'tes', 'price' => 1000,
        ]);
    }

    public function test_customer_cannot_access_category_pages(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $this->actingAs($customer)->get('/admin/categories')->assertForbidden();
    }

    public function test_admin_can_create_update_and_delete_category(): void
    {
        $this->actingAs($this->admin());

        $this->post('/admin/categories', ['name' => 'Bloom Box', 'is_active' => 1])
            ->assertRedirect('/admin/categories');
        $category = Category::where('slug', 'bloom-box')->firstOrFail();

        $this->put("/admin/categories/{$category->id}", ['name' => 'Bloom Box Premium'])
            ->assertRedirect('/admin/categories');
        $this->assertDatabaseHas('categories', ['slug' => 'bloom-box-premium', 'is_active' => 0]);

        $this->delete("/admin/categories/{$category->id}")->assertRedirect('/admin/categories');
        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    public function test_category_name_must_be_unique(): void
    {
        $this->actingAs($this->admin());
        Category::create(['name' => 'Bloom Box', 'slug' => 'bloom-box']);

        $this->post('/admin/categories', ['name' => 'Bloom Box'])->assertSessionHasErrors('name');
    }

    public function test_category_with_bouquets_cannot_be_deleted(): void
    {
        $this->actingAs($this->admin());
        $bouquet = $this->bouquet();

        $this->delete("/admin/categories/{$bouquet->category_id}")->assertSessionHas('error');
        $this->assertDatabaseHas('categories', ['id' => $bouquet->category_id]);
    }

    public function test_admin_can_upload_and_replace_bouquet_image(): void
    {
        Storage::fake('public');
        $this->actingAs($this->admin());
        $bouquet = $this->bouquet();

        $this->put("/admin/bouquet-images/{$bouquet->id}", ['image' => UploadedFile::fake()->image('a.jpg')]);
        $first = $bouquet->fresh()->image;
        Storage::disk('public')->assertExists($first);

        $this->put("/admin/bouquet-images/{$bouquet->id}", ['image' => UploadedFile::fake()->image('b.png')]);
        Storage::disk('public')->assertMissing($first);   // file lama terhapus
        Storage::disk('public')->assertExists($bouquet->fresh()->image);
    }

    public function test_upload_rejects_non_image_and_oversized_file(): void
    {
        Storage::fake('public');
        $this->actingAs($this->admin());
        $bouquet = $this->bouquet();

        $this->put("/admin/bouquet-images/{$bouquet->id}", ['image' => UploadedFile::fake()->create('x.pdf', 100)])
            ->assertSessionHasErrors('image');
        $this->put("/admin/bouquet-images/{$bouquet->id}", ['image' => UploadedFile::fake()->image('big.jpg')->size(3000)])
            ->assertSessionHasErrors('image');
    }
}