<?php

namespace Tests\Feature;

use App\Models\Bouquet;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogTest extends TestCase
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

    public function test_guest_can_view_catalog(): void
    {
        $this->make('Rose Love');

        $this->get('/catalog')->assertOk()->assertSee('The Elegance Collection')->assertSee('Rose Love');
    }

    public function test_hides_inactive_bouquets_and_inactive_categories(): void
    {
        $this->make('Tampil Bunga');
        $this->make('Nonaktif Bunga', ['is_active' => false]);
        $this->make('Kategori Mati Bunga', ['category_id' => $this->category('Arsip', false)->id]);

        $this->get('/catalog')
            ->assertSee('Tampil Bunga')
            ->assertDontSee('Nonaktif Bunga')
            ->assertDontSee('Kategori Mati Bunga');
    }

    public function test_search_matches_name_and_description(): void
    {
        $this->make('Peony Dream', ['description' => 'Pink Peonies']);
        $this->make('Tulip Joy', ['description' => 'White Tulips']);

        $this->get('/catalog?q=Peony')->assertSee('Peony Dream')->assertDontSee('Tulip Joy');
        $this->get('/catalog?q=Tulips')->assertSee('Tulip Joy')->assertDontSee('Peony Dream');
        $this->get('/catalog?q=tidakada')->assertSee('Tidak ada bouquet yang cocok');
    }

    public function test_filters_by_occasion_style_color_category_and_price(): void
    {
        $this->make('Anniv Bunga', ['occasion' => 'anniversary', 'style' => 'luxury', 'color' => 'red', 'price' => 1750000]);
        $this->make('Birthday Bunga', ['occasion' => 'birthday', 'style' => 'modern', 'color' => 'pink', 'price' => 300000]);
        $this->make('Basket Bunga', ['category_id' => $this->category('Flower Basket')->id, 'price' => 800000]);

        $this->get('/catalog?occasion=anniversary')->assertSee('Anniv Bunga')->assertDontSee('Birthday Bunga');
        $this->get('/catalog?style=modern')->assertSee('Birthday Bunga')->assertDontSee('Anniv Bunga');
        $this->get('/catalog?color=red')->assertSee('Anniv Bunga')->assertDontSee('Birthday Bunga');
        $this->get('/catalog?price=gt1500')->assertSee('Anniv Bunga')->assertDontSee('Birthday Bunga');
        $this->get('/catalog?price=lt500')->assertSee('Birthday Bunga')->assertDontSee('Anniv Bunga');
        $this->get('/catalog?category=' . $this->category('Flower Basket')->id)
            ->assertSee('Basket Bunga')->assertDontSee('Anniv Bunga');
    }

    public function test_sorting_by_price(): void
    {
        $this->make('Murah Bunga', ['price' => 100000]);
        $this->make('Mahal Bunga', ['price' => 900000]);

        $this->get('/catalog?sort=price_asc')->assertSeeInOrder(['Murah Bunga', 'Mahal Bunga']);
        $this->get('/catalog?sort=price_desc')->assertSeeInOrder(['Mahal Bunga', 'Murah Bunga']);
    }

    public function test_bestseller_sort_counts_only_non_cancelled_orders(): void
    {
        $a = $this->make('Aaa Bunga');
        $b = $this->make('Bbb Bunga');
        $user = User::factory()->create();

        $buy = function (Bouquet $bouquet, int $qty, string $status) use ($user) {
            $order = $user->orders()->create([
                'recipient_name' => 'Tes', 'recipient_phone' => '0812', 'delivery_city' => 'Jakarta',
                'delivery_address' => 'Jl. Tes', 'delivery_date' => now()->addDay(),
                'subtotal' => 1000, 'total_amount' => 1000, 'status' => $status,
            ]);
            $order->items()->create([
                'bouquet_id' => $bouquet->id, 'bouquet_name' => $bouquet->name,
                'price' => 1000, 'quantity' => $qty, 'subtotal' => 1000 * $qty,
            ]);
        };

        $buy($a, 10, 'cancelled');   // tidak dihitung
        $buy($b, 3, 'created');

        $this->get('/catalog?sort=bestseller')->assertSeeInOrder(['Bbb Bunga', 'Aaa Bunga']);
    }

    public function test_invalid_filter_values_are_ignored(): void
    {
        $this->make('Rose Love');

        $this->get('/catalog?occasion=hacker&sort=zzz&price=xx&category=abc&color[]=1&q[]=1')
            ->assertOk()->assertSee('Rose Love');
    }

    public function test_out_of_stock_is_listed_with_label(): void
    {
        $this->make('Kosong Bunga', ['stock' => 0]);

        $this->get('/catalog')->assertSee('Kosong Bunga')->assertSee('Habis');
    }

    public function test_pagination_shows_total_and_keeps_filters(): void
    {
        foreach (range(1, 12) as $i) {
            $this->make("Bunga {$i}", ['occasion' => 'birthday']);
        }

        $this->get('/catalog?occasion=birthday')->assertSee('dari 12 bouquet')
            ->assertSee('occasion=birthday&amp;page=2', false);
        $this->get('/catalog?occasion=birthday&page=2')->assertOk();
    }
}