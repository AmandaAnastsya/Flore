<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Harga modal (HPP) per bouquet — hanya untuk admin
        Schema::table('bouquets', function (Blueprint $table) {
            $table->decimal('cost_price', 12, 2)->nullable()->after('price');
        });

        // Salinan HPP saat checkout, supaya margin pesanan lama tidak berubah bila HPP diedit
        Schema::table('order_items', function (Blueprint $table) {
            $table->decimal('unit_cost', 12, 2)->nullable()->after('price');
        });

        // Faktor "Optimasi Profit Margin" di Rule Engine Configurator
        Schema::table('recommendation_rules', function (Blueprint $table) {
            $table->enum('criteria', ['occasion', 'color', 'style', 'size', 'stock', 'margin'])->change();
        });
    }

    public function down(): void
    {
        \App\Models\RecommendationRule::where('criteria', 'margin')->delete();

        Schema::table('recommendation_rules', function (Blueprint $table) {
            $table->enum('criteria', ['occasion', 'color', 'style', 'size', 'stock'])->change();
        });
        Schema::table('order_items', fn (Blueprint $table) => $table->dropColumn('unit_cost'));
        Schema::table('bouquets', fn (Blueprint $table) => $table->dropColumn('cost_price'));
    }
};
