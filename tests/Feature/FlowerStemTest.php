<?php

namespace Tests\Feature;

use App\Models\Bouquet;
use App\Models\Category;
use App\Models\Flower;
use App\Services\Cart;
use Database\Seeders\FlowerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FlowerStemTest extends TestCase
{
    use RefreshDatabase;

    private function flower(string $name, array $attrs = []): Flower
    {
        return Flower::create(array_merge([
            'name' => $name, 'slug' => str($name)->slug(), 'species' => 'rose',
            'price_per_stem' => 8000, 'bundle_size' => 10, 'bundle_price' => 70000,
            'stock' => 100, 'reorder_level' => 30, 'is_active' => true,
        ], $attrs));
    }

    private function bouquet(string $name, int $price = 100000): Bouquet
    {
        $cat = Category::firstOrCreate(['slug' => 'hand-bouquet'], ['name' => 'Hand Bouquet']);

        return Bouquet::create([
            'category_id' => $cat->id, 'name' => $name, 'slug' => str($name)->slug(),
            'price' => $price, 'stock' => 10, 'is_active' => true,
        ]);
    }

   private function addStem(Flower $f, int|string $qty = 1)
    {
        return $this->post("/cart/stems/{$f->slug}", ['quantity' => $qty]);
    }

    // ---------------------------------------------------------------- harga

    public function test_price_for_uses_bundle_price_automatically(): void
    {
        $rose = $this->flower('Rose Merah');   // 8.000/tangkai, paket 10 = 70.000

        $this->assertSame(0.0, $rose->priceFor(0));
        $this->assertSame(8000.0, $rose->priceFor(1));
        $this->assertSame(72000.0, $rose->priceFor(9));
        $this->assertSame(70000.0, $rose->priceFor(10));
        $this->assertSame(78000.0, $rose->priceFor(11));
        $this->assertSame(180000.0, $rose->priceFor(25));   // 2 paket + 5 tangkai
        $this->assertSame(10000.0, $rose->bundleSavings());
    }

    public function test_price_without_bundle_and_with_bad_bundle(): void
    {
        $peony = $this->flower('Peony', ['price_per_stem' => 55000, 'bundle_size' => null, 'bundle_price' => null]);
        $this->assertSame(165000.0, $peony->priceFor(3));
        $this->assertSame(0.0, $peony->bundleSavings());

        // paket yang lebih mahal dari satuan tidak boleh dipakai
        $odd = $this->flower('Aneh', ['bundle_price' => 100000]);
        $this->assertSame(80000.0, $odd->priceFor(10));
    }

    public function test_stock_status_levels(): void
    {
        $this->assertSame('out', (new Flower(['stock' => 0, 'reorder_level' => 30]))->stock_status);
        $this->assertSame('low', (new Flower(['stock' => 30, 'reorder_level' => 30]))->stock_status);
        $this->assertSame('ok', (new Flower(['stock' => 31, 'reorder_level' => 30]))->stock_status);
    }

    public function test_seeder_stays_within_max_types_and_has_required_flowers(): void
    {
        $this->seed(FlowerSeeder::class);
        $this->seed(FlowerSeeder::class);   // aman dijalankan dua kali

        $this->assertLessThanOrEqual(Flower::MAX_TYPES, Flower::count());
        foreach (['Rose Merah', 'Rose Pink', 'Rose Putih', 'Lily', 'Matahari', 'Sedap Malam', 'Baby Rose Spray', 'Gerbera'] as $name) {
            $this->assertDatabaseHas('flowers', ['name' => $name]);
        }
        foreach (Flower::whereNotNull('bundle_size')->get() as $flower) {
            $this->assertLessThan($flower->bundle_size * (float) $flower->price_per_stem, (float) $flower->bundle_price, "{$flower->name}: harga paket harus lebih murah dari satuan");
        }
    }

    // -------------------------------------------------------------- katalog

    public function test_stem_catalog_lists_active_flowers_with_prices_and_bundle(): void
    {
        $this->flower('Rose Merah');
        $this->flower('Tersembunyi', ['is_active' => false]);

        $this->get('/stems')->assertOk()
            ->assertSee('Bunga Per Tangkai')->assertSee('Rose Merah')
            ->assertSee('Rp 8.000')->assertSee('Paket 10 tangkai Rp 70.000')->assertSee('hemat Rp 10.000')
            ->assertDontSee('Tersembunyi');
    }

    public function test_stem_catalog_search_and_in_stock_filter_and_labels(): void
    {
        $this->withoutExceptionHandling();
        $this->flower('Rose Merah');
        $this->flower('Lily', ['species' => 'lily']);
        $this->flower('Tulip', ['species' => 'tulip', 'stock' => 0]);
        $this->flower('Peony', ['species' => 'peony', 'stock' => 12]);

        $this->get('/stems?q=lily')->assertSee('Lily')->assertDontSee('Rose Merah');
        $this->get('/stems?in_stock=1')->assertDontSee('Tulip')->assertSee('Rose Merah');
        $this->get('/stems')->assertSee('<span class="tag tag-out">Habis</span>', false)->assertSee('Sisa 12 tangkai');
        $this->get('/stems?q=tidakada')->assertSee('Tidak ada jenis bunga yang cocok');
    }

    // ------------------------------------------------------------- keranjang

    public function test_guest_can_add_stems_default_one_and_accumulate(): void
    {
        $f = $this->flower('Rose Merah');

        $this->addStem($f, 1)->assertSessionHas('success');
        $this->post("/cart/stems/{$f->slug}")->assertSessionHas('success');   // tanpa quantity = 1
        $this->addStem($f, 8);

        $this->assertSame([$f->id => 10], session('cart_stems'));
    }

    public function test_cannot_exceed_stock_or_add_out_of_stock(): void
    {
        $few = $this->flower('Peony', ['stock' => 3]);
        $none = $this->flower('Tulip', ['stock' => 0]);

        $this->addStem($few, 2)->assertSessionHas('success');
        $this->addStem($few, 2)->assertSessionHas('error');
        $this->assertSame([$few->id => 2], session('cart_stems'));

        $this->addStem($none)->assertSessionHas('error');
        $this->assertArrayNotHasKey($none->id, session('cart_stems'));
    }

    public function test_stem_quantity_is_validated_and_hidden_flowers_are_404(): void
    {
        $f = $this->flower('Rose Merah', ['stock' => 500]);
        $off = $this->flower('Mati', ['is_active' => false]);

        foreach ([0, -1, 'abc', Cart::MAX_STEMS + 1] as $bad) {
            $this->addStem($f, $bad)->assertSessionHasErrors('quantity');
        }
        $this->assertEmpty(session('cart_stems', []));

        $this->addStem($off)->assertNotFound();
        $this->post('/cart/stems/tidak-ada')->assertNotFound();
    }

    public function test_cart_shows_stems_with_bundle_pricing_and_combined_subtotal(): void
    {
        $rose = $this->flower('Rose Merah');
        $b = $this->bouquet('Rose Love', 100000);

        $this->addStem($rose, 25);                  // 2 paket + 5 tangkai = 180.000
        $this->post("/cart/items/{$b->slug}");      // + 100.000 = 280.000

        $this->get('/cart')->assertOk()
            ->assertSee('Rose Merah')->assertSee('Harga paket')
            ->assertSee('25 tangkai')->assertSee('1 barang')->assertSee('Rp 280.000');
    }

    public function test_stem_only_cart_is_not_shown_as_empty(): void
    {
        $this->addStem($this->flower('Rose Merah'), 3);

        $this->get('/cart')->assertDontSee('Keranjangmu masih kosong')->assertSee('3 tangkai');
    }

    public function test_update_and_remove_stems(): void
    {
        $f = $this->flower('Rose Merah', ['stock' => 20]);
        $other = $this->flower('Lily');
        $this->addStem($f, 2);
        $this->addStem($other, 1);

        $this->patch("/cart/stems/{$f->slug}", ['quantity' => 15])->assertSessionHas('success');
        $this->patch("/cart/stems/{$f->slug}", ['quantity' => 25])->assertSessionHas('error');   // melebihi stok
        $this->patch("/cart/stems/{$f->slug}", ['quantity' => 0])->assertSessionHasErrors('quantity');
        $this->assertSame([$f->id => 15, $other->id => 1], session('cart_stems'));

        $this->delete("/cart/stems/{$f->slug}")->assertSessionHas('success');
        $this->assertSame([$other->id => 1], session('cart_stems'));
    }

    public function test_remove_works_for_deleted_flower(): void
    {
        $f = $this->flower('Rose Merah');
        $this->addStem($f);
        $f->delete();

        $this->delete("/cart/stems/{$f->slug}")->assertSessionHas('success');
        $this->assertEmpty(session('cart_stems', []));
    }

    public function test_sync_adjusts_and_removes_stems(): void
    {
        $rose = $this->flower('Rose Merah', ['stock' => 50]);
        $lily = $this->flower('Lily');
        $this->addStem($rose, 30);
        $this->addStem($lily, 4);

        $rose->update(['stock' => 12]);
        $lily->update(['is_active' => false]);

        $this->get('/cart')
            ->assertSee('disesuaikan menjadi 12 tangkai')
            ->assertSee('tidak lagi tersedia');
        $this->assertSame([$rose->id => 12], session('cart_stems'));

        $rose->update(['stock' => 0]);
        $this->get('/cart')->assertSee('sedang habis')->assertSee('Keranjangmu masih kosong');
    }

    public function test_badge_counts_both_types_and_clear_empties_both(): void
    {
        $b = $this->bouquet('Rose Love');
        $f = $this->flower('Rose Merah');
        $this->post("/cart/items/{$b->slug}", ['quantity' => 2]);
        $this->addStem($f, 5);

        $this->get('/catalog')->assertSee('badge bg-secondary">7</span>', false);

        $this->delete('/cart')->assertRedirect('/cart');
        $this->assertEmpty(session('cart', []));
        $this->assertEmpty(session('cart_stems', []));
    }

    public function test_navbar_links_to_stem_catalog(): void
    {
        $this->get('/catalog')->assertSee('Per Tangkai')->assertSee(route('stems.index'), false);
    }
}