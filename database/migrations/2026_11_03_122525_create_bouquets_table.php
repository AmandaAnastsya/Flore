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
    Schema::create('bouquets', function (Blueprint $table) {
        $table->id();
        $table->foreignId('category_id')->constrained()->restrictOnDelete();
        $table->string('name');
        $table->string('slug')->unique();
        $table->text('description')->nullable();
        $table->decimal('price', 12, 2);
        $table->unsignedInteger('stock')->default(0);
        $table->unsignedInteger('low_stock_threshold')->default(5);
        $table->string('image')->nullable();

        // atribut untuk Recommendation Wizard
        $table->string('occasion')->nullable();
        $table->string('color')->nullable();
        $table->string('style')->nullable();
        $table->enum('size', ['mini', 'small', 'medium', 'large'])->default('medium');

        $table->boolean('is_same_day')->default(false);
        $table->boolean('is_active')->default(true);
        $table->timestamps();
        $table->softDeletes();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bouquets');
    }
};
