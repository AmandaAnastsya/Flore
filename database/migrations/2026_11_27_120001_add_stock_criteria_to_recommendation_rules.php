<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Faktor "Ketersediaan Stok" (Inventory Safety) dari Rule Engine Configurator
    public function up(): void
    {
        Schema::table('recommendation_rules', function (Blueprint $table) {
            $table->enum('criteria', ['occasion', 'color', 'style', 'size', 'stock'])->change();
        });
    }

    public function down(): void
    {
        \App\Models\RecommendationRule::where('criteria', 'stock')->delete();

        Schema::table('recommendation_rules', function (Blueprint $table) {
            $table->enum('criteria', ['occasion', 'color', 'style', 'size'])->change();
        });
    }
};
