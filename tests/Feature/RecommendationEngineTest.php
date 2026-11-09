<?php

namespace Tests\Feature;

use App\Models\Bouquet;
use App\Models\Category;
use App\Models\Order;
use App\Models\RecommendationRule;
use App\Models\User;
use App\Services\RecommendationEngine;
use App\Services\RecommendationResult;
use Database\Seeders\RecommendationRuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecommendationEngineTest extends TestCase
{
    use RefreshDatabase;

    private RecommendationEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RecommendationRuleSeeder::class);   // bobot: occasion 20-30, color 20, style 15, size 10
        $this->engine = app(RecommendationEngine::class);
    }

    private function bouquet(string $name, array $attrs = []): Bouquet
    {
        $cat = Category::firstOrCreate(['slug' => 'hand-bouquet'], ['name' => 'Hand Bouquet']);

        return Bouquet::unguarded(fn () => Bouquet::create(array_merge([
            'category_id' => $cat->id, 'name' => $name, 'slug' => str($name)->slug(),
            'price' => 300000, 'stock' => 10, 'is_active' => true,
            'occasion' => 'anniversary', 'color' => 'pink', 'style' => 'classic', 'size' => 'medium',
        ], $attrs)));
    }

    private function names(RecommendationResult $result): array
    {
        return $result->items->map(fn ($i) => $i['bouquet']->name)->all();
    }

    // ---------------------------------------------------------------- skor

    public function test_scores_by_rule_weights_and_orders_best_first(): void
    {
        $this->bouquet('A Perfect');                                          // anniversary + pink
        $this->bouquet('B Occasion Only', ['color' => 'white']);
        $this->bouquet('C Color Only', ['occasion' => 'birthday']);
        $this->bouquet('D Nothing', ['occasion' => 'wedding', 'color' => 'red']);

        $r = $this->engine->recommend(['occasion' => 'anniversary', 'color' => 'pink', 'budget' => '200-350']);

        $this->assertSame('exact', $r->mode);
        $this->assertSame(['A Perfect', 'B Occasion Only', 'C Color Only'], $this->names($r));   // D skor 0 tidak muncul
        $this->assertSame([50, 30, 20], $r->items->pluck('score')->all());
        $this->assertSame([100, 60, 40], $r->items->pluck('percent')->all());
        $this->assertSame([true, false, false], $r->items->pluck('is_perfect')->all());
        $this->assertSame(['occasion', 'color'], $r->items[0]['matched']);
        $this->assertSame(['color'], $r->items[1]['missed']);
        $this->assertSame(3, $r->total);
    }

    public function test_style_and_size_add_to_the_score(): void
    {
        $this->bouquet('S1', ['style' => 'modern', 'size' => 'mini']);
        $this->bouquet('S2');

        $r = $this->engine->recommend(['style' => 'modern', 'size' => 'mini']);

        $this->assertSame(['S1'], $this->names($r));
        $this->assertSame(25, $r->items[0]['score']);   // 15 + 10
        $this->assertSame(100, $r->items[0]['percent']);
    }

    public function test_changing_rule_weights_changes_the_ranking(): void
    {
        $this->bouquet('X', ['occasion' => 'anniversary', 'color' => 'white']);
        $this->bouquet('Y', ['occasion' => 'birthday', 'color' => 'pink']);
        $answers = ['occasion' => 'anniversary', 'color' => 'pink'];

        $this->assertSame(['X', 'Y'], $this->names($this->engine->recommend($answers)));   // 30 vs 20

        RecommendationRule::where(['criteria' => 'occasion', 'value' => 'anniversary'])->update(['weight' => 5]);
        RecommendationRule::where(['criteria' => 'color', 'value' => 'pink'])->update(['weight' => 50]);

        $this->assertSame(['Y', 'X'], $this->names($this->engine->recommend($answers)));   // 5 vs 50
    }

    public function test_reasons_are_human_readable(): void
    {
        $this->bouquet('Satu');

        $r = $this->engine->recommend(['occasion' => 'anniversary', 'color' => 'pink', 'budget' => '200-350']);

        $this->assertSame(['Cocok untuk Anniversary', 'Warna Pink', 'Sesuai budget'], $r->items[0]['reasons']);
    }

    // ------------------------------------------------------- filter keras

    public function test_hard_filters_exclude_unavailable_bouquets(): void
    {
        $this->bouquet('Ok');
        $this->bouquet('Habis', ['stock' => 0]);
        $this->bouquet('Nonaktif', ['is_active' => false]);
        $off = Category::unguarded(fn () => Category::create(['name' => 'Off', 'slug' => 'off', 'is_active' => false]));
        $this->bouquet('Kategori Mati', ['category_id' => $off->id]);
        $this->bouquet('Terhapus')->delete();
        $this->bouquet('Mahal', ['price' => 900000]);

        $r = $this->engine->recommend(['occasion' => 'anniversary', 'budget' => '200-350']);

        $this->assertSame('exact', $r->mode);
        $this->assertSame(['Ok'], $this->names($r));
    }

    public function test_budget_ranges_include_min_and_exclude_max(): void
    {
        $this->bouquet('P199', ['price' => 199999]);
        $this->bouquet('P200', ['price' => 200000]);
        $this->bouquet('P349', ['price' => 349999]);
        $this->bouquet('P350', ['price' => 350000]);
        $this->bouquet('P750', ['price' => 750000]);
        $answers = ['occasion' => 'anniversary'];

        $this->assertSame(['P199'], $this->names($this->engine->recommend($answers + ['budget' => 'under-200'])));
        $this->assertSame(['P200', 'P349'], $this->names($this->engine->recommend($answers + ['budget' => '200-350'])));
        $this->assertSame(['P350'], $this->names($this->engine->recommend($answers + ['budget' => '350-500'])));
        $this->assertSame(['P750'], $this->names($this->engine->recommend($answers + ['budget' => 'over-750'])));
    }

    // ------------------------------------------------------------ fallback

    public function test_relaxes_budget_when_nothing_matches_exactly(): void
    {
        $this->bouquet('Dekat', ['price' => 230000]);   // dalam 240.000 (200.000 + 20%)
        $this->bouquet('Jauh', ['price' => 300000]);

        $r = $this->engine->recommend(['occasion' => 'anniversary', 'budget' => 'under-200']);

        $this->assertSame('relaxed_budget', $r->mode);
        $this->assertSame(['Dekat'], $this->names($r));
        $this->assertFalse($r->items[0]['within_budget']);
        $this->assertContains('Sedikit di luar budget', $r->items[0]['reasons']);
    }

    public function test_falls_back_to_popular_in_budget_when_no_criteria_match(): void
    {
        $this->bouquet('Satu', ['occasion' => 'wedding', 'color' => 'red']);

        $r = $this->engine->recommend(['occasion' => 'anniversary', 'budget' => '200-350']);

        $this->assertSame('alternatives', $r->mode);
        $this->assertSame(['Satu'], $this->names($r));
        $this->assertSame(0, $r->items[0]['score']);
    }

    public function test_browse_mode_when_no_criteria_chosen(): void
    {
        $this->bouquet('Satu');
        $this->bouquet('Mahal', ['price' => 900000]);

        $this->assertSame('browse', $this->engine->recommend(['budget' => '200-350'])->mode);
        $this->assertSame(['Satu'], $this->names($this->engine->recommend(['budget' => '200-350'])));
        $this->assertCount(2, $this->engine->recommend([])->items);   // tanpa apa pun: semua yang tersedia
    }

    public function test_none_when_nothing_is_available(): void
    {
        $r = $this->engine->recommend(['occasion' => 'anniversary', 'budget' => '200-350']);

        $this->assertSame('none', $r->mode);
        $this->assertTrue($r->isEmpty());
        $this->assertSame(0, $r->total);
        $this->assertNotSame('', $r->message);
    }

    // ----------------------------------------------- input tidak valid

    public function test_invalid_or_disabled_choices_are_ignored_and_reported(): void
    {
        $this->bouquet('Satu');
        RecommendationRule::where(['criteria' => 'color', 'value' => 'peach'])->update(['is_active' => false]);

        $r = $this->engine->recommend(['occasion' => 'ngawur', 'color' => 'peach', 'style' => ['x'], 'budget' => 'gratis']);

        $this->assertSame([], $r->criteria);
        $this->assertNull($r->budget);
        $this->assertSame(
            ['occasion' => 'ngawur', 'color' => 'peach', 'style' => '(tidak valid)', 'budget' => 'gratis'],
            $r->ignored
        );
        $this->assertSame('browse', $r->mode);   // tidak error, tetap menampilkan sesuatu
    }

    public function test_disabled_rule_no_longer_scores(): void
    {
        $this->bouquet('Pink', ['color' => 'pink']);
        RecommendationRule::where(['criteria' => 'color', 'value' => 'pink'])->update(['is_active' => false]);

        $r = $this->engine->recommend(['color' => 'pink']);

        $this->assertSame(['color' => 'pink'], $r->ignored);
        $this->assertSame([], $r->criteria);
    }

    // ------------------------------------------------------------ urutan

    public function test_tie_breaks_prefer_popular_then_cheaper(): void
    {
        $this->bouquet('Sepi');
        $laris = $this->bouquet('Laris');

        $order = Order::create([
            'user_id' => User::factory()->create()->id, 'recipient_name' => 'Budi', 'recipient_phone' => '081234567890',
            'delivery_city' => 'Bandung', 'delivery_address' => 'Jl. Merdeka No. 10',
            'delivery_date' => now()->addDays(2)->toDateString(), 'subtotal' => 1500000, 'total_amount' => 1500000,
        ]);
        $order->items()->create([
            'bouquet_id' => $laris->id, 'bouquet_name' => 'Laris', 'price' => 300000, 'quantity' => 5, 'subtotal' => 1500000,
        ]);

        $this->assertSame(['Laris', 'Sepi'], $this->names($this->engine->recommend(['occasion' => 'anniversary'])));
    }

    public function test_equal_score_prefers_cheaper(): void
    {
        $this->bouquet('Mahal', ['price' => 340000]);
        $this->bouquet('Murah', ['price' => 250000]);

        $r = $this->engine->recommend(['occasion' => 'anniversary', 'budget' => '200-350']);

        $this->assertSame(['Murah', 'Mahal'], $this->names($r));
    }

    public function test_limit_is_applied_and_clamped(): void
    {
        foreach (range(1, 10) as $i) {
            $this->bouquet(sprintf('B%02d', $i), ['price' => 200000 + $i * 1000]);
        }
        $answers = ['occasion' => 'anniversary'];

        $r = $this->engine->recommend($answers, 3);
        $this->assertCount(3, $r->items);
        $this->assertSame(10, $r->total);

        $this->assertCount(1, $this->engine->recommend($answers, 0)->items);      // minimal 1
        $this->assertCount(10, $this->engine->recommend($answers, 999)->items);   // maksimal 24, di sini hanya ada 10
    }

    // ---------------------------------------------------------- opsi wizard

    public function test_options_come_from_active_rules_only(): void
    {
        RecommendationRule::where(['criteria' => 'color', 'value' => 'red'])->update(['is_active' => false]);

        $options = $this->engine->options();

        $this->assertSame(
            ['anniversary', 'birthday', 'wedding', 'graduation', 'apology', 'grand-opening'],
            $options['occasion']   // urut bobot terbesar
        );
        $this->assertContains('pink', $options['color']);
        $this->assertNotContains('red', $options['color']);
        $this->assertSame(['under-200', '200-350', '350-500', '500-750', 'over-750'], array_keys($options['budget']));
    }
}