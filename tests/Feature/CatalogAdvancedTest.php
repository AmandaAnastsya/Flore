<?php

namespace Tests\Feature;

use App\Models\Bouquet;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogAdvancedTest extends TestCase
{
    use RefreshDatabase;

    private function category(string $name = 'Hand Bouquet', bool $active = true): Category
    {
        return Category::firstOrCreate(['slug' => str($name)->slug()], ['name' => $name, 'is_active' => $active]);
    }

    private function make(string $name, array $attrs = []): Bouquet
    {
        return Bouquet::create(array_merge([
            'category_id' => $this->category()->id, 'name' => $name, 'slug' => str($name)->slug(),
            'price' => 500000, 'stock' => 10, 'is_active' => true,
        ], $attrs));
    }

    public function test_multi_select_occasion_color_and_size(): void
    {
        $this->make('Anniv Bunga', ['occasion' => 'anniversary', 'color' => 'red', 'size' => 'large']);
        $this->make('Bday Bunga', ['occasion' => 'birthday', 'color' => 'pink', 'size' => 'small']);
        $this->make('Wedding Bunga', ['occasion' => 'wedding', 'color' => 'white', 'size' => 'medium']);

        $this->get('/catalog?occasion[]=anniversary&occasion[]=birthday')
            ->assertSee('Anniv Bunga')->assertSee('Bday Bunga')->assertDontSee('Wedding Bunga');
        $this->get('/catalog?color[]=red&color[]=white')
            ->assertSee('Anniv Bunga')->assertSee('Wedding Bunga')->assertDontSee('Bday Bunga');
        $this->get('/catalog?size[]=small')
            ->assertSee('Bday Bunga')->assertDontSee('Anniv Bunga')->assertDontSee('Wedding Bunga');
    }

    public function test_custom_price_range(): void
    {
        $this->make('Murah Bunga', ['price' => 200000]);
        $this->make('Sedang Bunga', ['price' => 600000]);
        $this->make('Mahal Bunga', ['price' => 1600000]);

        $this->get('/catalog?price_min=500000&price_max=1000000')
            ->assertSee('Sedang Bunga')->assertDontSee('Murah Bunga')->assertDontSee('Mahal Bunga');
        // min dan maks terbalik tetap ditangani
        $this->get('/catalog?price_min=1000000&price_max=500000')
            ->assertSee('Sedang Bunga')->assertDontSee('Mahal Bunga');
        // batas bawah bersifat inklusif
        $this->get('/catalog?price_min=600000')
            ->assertSee('Sedang Bunga')->assertSee('Mahal Bunga')->assertDontSee('Murah Bunga');
        // nilai sampah diabaikan
        $this->get('/catalog?price_min=abc&price_max[]=1&price_min2=-5')
            ->assertOk()->assertSee('Murah Bunga')->assertSee('Mahal Bunga');
    }

    public function test_same_day_and_in_stock_toggles(): void
    {
        $this->make('Same Bunga', ['is_same_day' => true]);
        $this->make('Biasa Bunga', ['is_same_day' => false]);
        $this->make('Kosong Bunga', ['is_same_day' => true, 'stock' => 0]);

        $this->get('/catalog?same_day=1')
            ->assertSee('Same Bunga')->assertSee('Kosong Bunga')->assertDontSee('Biasa Bunga');
        $this->get('/catalog?in_stock=1')
            ->assertSee('Same Bunga')->assertSee('Biasa Bunga')->assertDontSee('Kosong Bunga');
        $this->get('/catalog?same_day=1&in_stock=1')
            ->assertSee('Same Bunga')->assertDontSee('Biasa Bunga')->assertDontSee('Kosong Bunga');
    }

    public function test_detail_page_shows_bouquet_info(): void
    {
        $this->make('Rose Love', [
            'description' => 'Pink Roses', 'price' => 1150000, 'occasion' => 'anniversary',
            'color' => 'pink', 'style' => 'soft-romantic', 'size' => 'large', 'is_same_day' => true,
        ]);

        $this->get('/catalog/rose-love')->assertOk()
            ->assertSee('Rose Love')->assertSee('Pink Roses')->assertSee('Rp 1.150.000')
            ->assertSee('Anniversary')->assertSee('Soft romantic')->assertSee('Large')
            ->assertSee('Same day delivery tersedia')->assertSee('Tersedia');
    }

    public function test_detail_page_stock_messages(): void
    {
        $this->make('Kosong Bunga', ['stock' => 0]);
        $this->make('Tipis Bunga', ['stock' => 3, 'low_stock_threshold' => 5]);

        $this->get('/catalog/kosong-bunga')->assertSee('Stok habis');
        $this->get('/catalog/tipis-bunga')->assertSee('Sisa 3');
    }

    public function test_hidden_or_missing_bouquets_return_404(): void
    {
        $this->make('Nonaktif Bunga', ['is_active' => false]);
        $this->make('Arsip Bunga', ['category_id' => $this->category('Arsip', false)->id]);
        $this->make('Terhapus Bunga')->delete();

        $this->get('/catalog/nonaktif-bunga')->assertNotFound();
        $this->get('/catalog/arsip-bunga')->assertNotFound();
        $this->get('/catalog/terhapus-bunga')->assertNotFound();
        $this->get('/catalog/tidak-ada')->assertNotFound();
    }

    public function test_related_bouquets_exclude_self_inactive_and_unrelated(): void
    {
        $this->make('Utama Bunga');
        $this->make('Mirip Bunga');
        $this->make('Mati Bunga', ['is_active' => false]);
        $this->make('Beda Bunga', ['category_id' => $this->category('Bloom Box')->id, 'occasion' => 'wedding']);

        $this->get('/catalog/utama-bunga')->assertOk()
            ->assertSee('Mungkin kamu suka')->assertSee('Mirip Bunga')
            ->assertDontSee('Mati Bunga')->assertDontSee('Beda Bunga');
    }

    public function test_catalog_cards_link_to_detail(): void
    {
        $bouquet = $this->make('Rose Love');

        $this->get('/catalog')->assertSee(route('catalog.show', $bouquet), false);
    }

    public function test_back_link_keeps_filters_only_from_catalog(): void
    {
        $this->make('Rose Love');

        $this->withHeader('Referer', url('/catalog?occasion=birthday'))
            ->get('/catalog/rose-love')->assertSee('occasion=birthday', false);

        $this->withHeader('Referer', 'https://example.com/lain')
            ->get('/catalog/rose-love')->assertDontSee('example.com');
    }
}