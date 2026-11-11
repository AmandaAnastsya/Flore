<?php

namespace Tests\Feature;

use App\Models\Bouquet;
use App\Models\Category;
use App\Models\User;
use Database\Seeders\RecommendationRuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecommendationWizardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RecommendationRuleSeeder::class);
    }

    private function bouquet(string $name, array $attrs = []): Bouquet
    {
        $cat = Category::firstOrCreate(['slug' => 'hand-bouquet'], ['name' => 'Hand Bouquet', 'is_active' => true]);

        return Bouquet::unguarded(fn () => Bouquet::create(array_merge([
            'category_id' => $cat->id, 'name' => $name, 'slug' => str($name)->slug(),
            'price' => 300000, 'stock' => 10, 'is_active' => true,
            'occasion' => 'anniversary', 'color' => 'pink', 'style' => 'classic', 'size' => 'medium',
        ], $attrs)));
    }

    private function customer(): User
    {
        return User::factory()->create(['role' => 'customer']);
    }

    public function test_home_renders_wizard_modal_with_all_steps(): void
    {
        $this->actingAs($this->customer())
            ->get(route('home'))
            ->assertOk()
            ->assertSee('id="wizardModal"', false)
            ->assertSee('data-step="occasion"', false)
            ->assertSee('data-step="color"', false)
            ->assertSee('data-step="style"', false)
            ->assertSee('data-step="size"', false)
            ->assertSee('data-step="budget"', false)
            ->assertSee('value="grand-opening"', false)
            ->assertSee('Di bawah Rp 200.000')
            ->assertSee('data-auto-open="0"', false);
    }

    public function test_wizard_route_opens_home_with_modal(): void
    {
        $user = $this->customer();

        $this->actingAs($user)->get(route('wizard.index'))
            ->assertRedirect(route('home', ['wizard' => 1]));

        $this->actingAs($user)->get(route('home', ['wizard' => 1]))
            ->assertSee('data-auto-open="1"', false);
    }

    public function test_recommend_returns_scored_json(): void
    {
        $this->bouquet('Blush Perfect');
        $this->bouquet('White One', ['color' => 'white']);
        $this->bouquet('Wedding Red', ['occasion' => 'wedding', 'color' => 'red']);

        $res = $this->getJson(route('wizard.recommend', [
            'occasion' => 'anniversary', 'color' => 'pink', 'budget' => '200-350',
        ]))->assertOk();

        $res->assertJsonPath('mode', 'exact')
            ->assertJsonCount(2, 'items')
            ->assertJsonPath('items.0.name', 'Blush Perfect')
            ->assertJsonPath('items.0.percent', 100)
            ->assertJsonPath('items.0.is_perfect', true)
            ->assertJsonPath('items.0.price', 'Rp 300.000')
            ->assertJsonPath('items.0.cart_url', route('cart.store', 'blush-perfect'))
            ->assertJsonPath('items.1.name', 'White One');

        $this->assertContains('Sesuai budget', $res->json('items.0.reasons'));
    }

    public function test_recommend_with_no_answers_browses_popular(): void
    {
        $this->bouquet('Any');

        $this->getJson(route('wizard.recommend'))
            ->assertOk()
            ->assertJsonPath('mode', 'browse')
            ->assertJsonCount(1, 'items');
    }

    public function test_recommend_rejects_non_string_input(): void
    {
        $this->getJson(route('wizard.recommend', ['occasion' => ['x']]))
            ->assertStatus(422);
    }

    public function test_out_of_stock_and_hidden_bouquets_are_not_recommended(): void
    {
        $this->bouquet('Sold Out', ['stock' => 0]);
        $this->bouquet('Inactive', ['is_active' => false]);

        $this->getJson(route('wizard.recommend', ['occasion' => 'anniversary']))
            ->assertOk()
            ->assertJsonPath('mode', 'none')
            ->assertJsonCount(0, 'items');
    }
}
