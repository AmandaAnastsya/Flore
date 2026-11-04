<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('recommendation_rules', function (Blueprint $table) {
    $table->id();
    $table->enum('criteria', ['occasion', 'color', 'style', 'size']);
    $table->string('value');                      // mis. "anniversary", "pink"
    $table->unsignedTinyInteger('weight')->default(10); // bobot skor
    $table->boolean('is_active')->default(true);
    $table->timestamps();
    $table->unique(['criteria', 'value']);
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recommendation_rules');
    }
};
