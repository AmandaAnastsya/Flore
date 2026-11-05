<?php

namespace Tests\Feature;

use App\Models\Bouquet;
use App\Models\Category;
use App\Services\Cart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartTest extends TestCase
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
            'price' => 100000, 'stock' => 10, 'is_active' => true,
        ], $attrs));
    }

    public function test_guest_can_add_to_cart_and_default_quantity_is_one(): void
    {
        $b = $this->make('Rose Love');

        $this->post("/cart/items/{$b->slug}")
            ->assertRedirect()->assertSessionHas('success');

        $this->assertSame([$b->id => 1], session('cart'));
        $this->get('/cart')->assertOk()->assertSee('Rose Love');
    }

    public function test_adding_twice_accumulates_quantity(): void
    {
        $b = $this->make('Rose Love');

        $this->post("/cart/items/{$b->slug}", ['quantity' => 2]);
        $this->post("/cart/items/{$b->slug}", ['quantity' => 3]);

        $this->assertSame([$b->id => 5], session('cart'));
    }

    public function test_cannot_add_out_of_stock_bouquet(): void
    {
        $b = $this->make('Kosong Bunga', ['stock' => 0]);

        $this->post("/cart/items/{$b->slug}")->assertSessionHas('error');
        $this->assertEmpty(session('cart', []));
    }

    public function test_cannot_exceed_available_stock(): void
    {
        $b = $this->make('Tipis Bunga', ['stock' => 3]);

        $this->post("/cart/items/{$b->slug}", ['quantity' => 2])->assertSessionHas('success');
        $this->post("/cart/items/{$b->slug}", ['quantity' => 2])->assertSessionHas('error');
        $this->assertSame([$b->id => 2], session('cart'));

        $this->post("/cart/items/{$b->slug}", ['quantity' => 1])->assertSessionHas('success');
        $this->post("/cart/items/{$b->slug}", ['quantity' => 1])->assertSessionHas('error');
        $this->assertSame([$b->id => 3], session('cart'));
    }

    public function test_quantity_is_validated(): void
    {
        $b = $this->make('Rose Love');

        foreach ([0, -1, 'abc', Cart::MAX_QTY + 1] as $bad) {
            $this->post("/cart/items/{$b->slug}", ['quantity' => $bad])->assertSessionHasErrors('quantity');
        }
        $this->assertEmpty(session('cart', []));
    }

    public function test_max_quantity_per_bouquet_is_enforced(): void
    {
        $b = $this->make('Banyak Bunga', ['stock' => 100]);

        $this->post("/cart/items/{$b->slug}", ['quantity' => Cart::MAX_QTY])->assertSessionHas('success');
        $this->post("/cart/items/{$b->slug}", ['quantity' => 1])->assertSessionHas('error');
        $this->assertSame([$b->id => Cart::MAX_QTY], session('cart'));
    }

    public function test_hidden_or_missing_bouquets_cannot_be_added(): void
    {
        $this->make('Nonaktif Bunga', ['is_active' => false]);
        $this->make('Arsip Bunga', ['category_id' => $this->category('Arsip', false)->id]);

        $this->post('/cart/items/nonaktif-bunga')->assertNotFound();
        $this->post('/cart/items/arsip-bunga')->assertNotFound();
        $this->post('/cart/items/tidak-ada')->assertNotFound();
        $this->assertEmpty(session('cart', []));
    }

    public function test_cart_page_uses_database_prices_for_subtotal(): void
    {
        $a = $this->make('Aaa Bunga', ['price' => 100000]);
        $b = $this->make('Bbb Bunga', ['price' => 250000]);

        // 'price' dari browser harus diabaikan
        $this->post("/cart/items/{$a->slug}", ['quantity' => 2, 'price' => 1]);
        $this->post("/cart/items/{$b->slug}");

        $this->get('/cart')->assertSee('Rp 450.000');
    }

    public function test_cart_sync_adjusts_quantity_when_stock_drops(): void
    {
        $b = $this->make('Rose Love', ['stock' => 10]);
        $this->post("/cart/items/{$b->slug}", ['quantity' => 5]);

        $b->update(['stock' => 2]);

        $this->get('/cart')->assertSee('disesuaikan menjadi 2');
        $this->assertSame([$b->id => 2], session('cart'));
    }

    public function test_cart_sync_removes_unavailable_bouquets(): void
    {
        $habis = $this->make('Habis Bunga');
        $mati = $this->make('Mati Bunga');
        $ada = $this->make('Ada Bunga');
        foreach ([$habis, $mati, $ada] as $b) {
            $this->post("/cart/items/{$b->slug}");
        }

        $habis->update(['stock' => 0]);
        $mati->update(['is_active' => false]);

        $this->get('/cart')->assertSee('sedang habis')->assertSee('tidak lagi tersedia');
        $this->assertSame([$ada->id => 1], session('cart'));
    }

    public function test_empty_cart_message(): void
    {
        $this->get('/cart')->assertOk()->assertSee('Keranjangmu masih kosong');
    }

    public function test_navbar_badge_shows_total_items(): void
    {
        $b = $this->make('Rose Love');
        $this->post("/cart/items/{$b->slug}", ['quantity' => 3]);

        $this->get('/catalog')->assertSee('badge bg-secondary">3</span>', false);
    }

    public function test_catalog_card_and_detail_have_add_to_cart_form(): void
    {
        $b = $this->make('Rose Love');

        $this->get('/catalog')->assertSee(route('cart.store', $b), false);
        $this->get('/catalog/rose-love')->assertSee(route('cart.store', $b), false)->assertSee('name="quantity"', false);
    }
}