<?php

namespace Tests\Feature;

use App\Models\Bouquet;
use App\Models\Category;
use App\Services\Cart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartManageTest extends TestCase
{
    use RefreshDatabase;

    private function make(string $name, array $attrs = []): Bouquet
    {
        $cat = Category::firstOrCreate(['slug' => 'hand-bouquet'], ['name' => 'Hand Bouquet']);

        return Bouquet::create(array_merge([
            'category_id' => $cat->id, 'name' => $name, 'slug' => str($name)->slug(),
            'price' => 100000, 'stock' => 10, 'is_active' => true,
        ], $attrs));
    }

    private function inCart(Bouquet $bouquet, int $qty = 1): void
    {
        $this->post("/cart/items/{$bouquet->slug}", ['quantity' => $qty]);
    }

    public function test_guest_can_update_quantity_and_subtotal_follows(): void
    {
        $a = $this->make('Aaa Bunga', ['price' => 100000]);
        $b = $this->make('Bbb Bunga', ['price' => 250000]);
        $this->inCart($a);
        $this->inCart($b);

        $this->patch("/cart/items/{$a->slug}", ['quantity' => 4])->assertSessionHas('success');

        $this->assertSame([$a->id => 4, $b->id => 1], session('cart'));
        $this->get('/cart')->assertSee('Rp 650.000')->assertSee('5 barang');
    }

    public function test_update_cannot_exceed_stock(): void
    {
        $b = $this->make('Tipis Bunga', ['stock' => 3]);
        $this->inCart($b, 2);

        $this->patch("/cart/items/{$b->slug}", ['quantity' => 4])->assertSessionHas('error');
        $this->assertSame([$b->id => 2], session('cart'));

        $this->patch("/cart/items/{$b->slug}", ['quantity' => 3])->assertSessionHas('success');
        $this->assertSame([$b->id => 3], session('cart'));
    }

    public function test_update_validates_quantity(): void
    {
        $b = $this->make('Rose Love');
        $this->inCart($b, 2);

        foreach ([0, -1, 'abc', Cart::MAX_QTY + 1, null] as $bad) {
            $this->patch("/cart/items/{$b->slug}", ['quantity' => $bad])->assertSessionHasErrors('quantity');
        }
        $this->assertSame([$b->id => 2], session('cart'));
    }

    public function test_update_item_not_in_cart_is_rejected(): void
    {
        $b = $this->make('Rose Love');

        $this->patch("/cart/items/{$b->slug}", ['quantity' => 2])->assertSessionHas('error');
        $this->assertEmpty(session('cart', []));
    }

    public function test_update_hidden_bouquet_returns_404(): void
    {
        $b = $this->make('Nonaktif Bunga');
        $this->inCart($b);
        $b->update(['is_active' => false]);

        $this->patch("/cart/items/{$b->slug}", ['quantity' => 2])->assertNotFound();
    }

    public function test_remove_item_keeps_the_others(): void
    {
        $a = $this->make('Aaa Bunga');
        $b = $this->make('Bbb Bunga');
        $this->inCart($a);
        $this->inCart($b, 2);

        $this->delete("/cart/items/{$a->slug}")->assertSessionHas('success');

        $this->assertSame([$b->id => 2], session('cart'));
    }

    public function test_remove_is_idempotent_and_works_for_deleted_bouquet(): void
    {
        $a = $this->make('Aaa Bunga');
        $gone = $this->make('Hilang Bunga');
        $this->inCart($a);
        $this->inCart($gone);
        $gone->delete();   // soft delete oleh admin

        $this->delete("/cart/items/{$gone->slug}")->assertSessionHas('success');
        $this->delete("/cart/items/{$gone->slug}")->assertSessionHas('success');   // kedua kali tidak error

        $this->assertSame([$a->id => 1], session('cart'));
    }

    public function test_clear_empties_the_cart(): void
    {
        $this->inCart($this->make('Aaa Bunga'));
        $this->inCart($this->make('Bbb Bunga'));

        $this->delete('/cart')->assertRedirect('/cart');

        $this->assertEmpty(session('cart', []));
        $this->get('/cart')->assertSee('Keranjangmu masih kosong');
    }

    public function test_summary_shows_item_count_and_forms(): void
    {
        $b = $this->make('Rose Love', ['price' => 200000]);
        $this->inCart($b, 3);

        $this->get('/cart')
            ->assertSee('3 barang')
            ->assertSee('Rp 600.000')
            ->assertSee(route('cart.update', $b), false)
            ->assertSee(route('cart.destroy', $b), false)
            ->assertSee(route('cart.clear'), false);
    }

    public function test_navbar_badge_follows_updates_and_removal(): void
    {
        $b = $this->make('Rose Love');
        $this->inCart($b, 2);

        $this->patch("/cart/items/{$b->slug}", ['quantity' => 5]);
        $this->get('/catalog')->assertSee('badge bg-secondary">5</span>', false);

        $this->delete("/cart/items/{$b->slug}");
        $this->get('/catalog')->assertDontSee('badge bg-secondary">', false);
    }
}