<?php

namespace Tests\Feature;

use App\Models\Bouquet;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchemaRelationTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeders_populate_core_data(): void
    {
        $this->seed();

        $this->assertSame(4, Category::count());
        $this->assertSame(12, Bouquet::count());
        $this->assertSame(1, User::where('role', 'admin')->count());
    }

    public function test_seeder_can_run_twice_without_duplicates(): void
    {
        $this->seed();
        $this->seed();

        $this->assertSame(12, Bouquet::count());
    }

    public function test_order_relations_work(): void
    {
        $this->seed();
        $user = User::factory()->create();
        $bouquet = Bouquet::first();

        $order = $user->orders()->create([
            'recipient_name' => 'Tes', 'recipient_phone' => '0812', 'delivery_city' => 'Jakarta',
            'delivery_address' => 'Jl. Tes', 'delivery_date' => now()->addDay(),
            'subtotal' => $bouquet->price, 'total_amount' => $bouquet->price,
        ]);
        $order->items()->create([
            'bouquet_id' => $bouquet->id, 'bouquet_name' => $bouquet->name,
            'price' => $bouquet->price, 'quantity' => 1, 'subtotal' => $bouquet->price,
        ]);

        $this->assertStringStartsWith('FLR-', $order->order_number);
        $this->assertSame($bouquet->name, $order->items->first()->bouquet->name);
        $this->assertSame($bouquet->category->id, $bouquet->category_id);
        $this->assertSame(1, $user->orders()->count());
    }
}