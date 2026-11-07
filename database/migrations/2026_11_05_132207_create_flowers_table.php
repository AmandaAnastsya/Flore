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
       Schema::create('flowers', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('slug')->unique();
    $table->string('species');                       // rose, lily, sunflower, ...
    $table->string('color')->nullable();
    $table->string('origin')->nullable();            // Lokal (Bandung), Import (Holland), ...
    $table->text('description')->nullable();
    $table->string('image')->nullable();
    $table->decimal('price_per_stem', 12, 2);
    $table->unsignedSmallInteger('bundle_size')->nullable();   // tangkai per paket
    $table->decimal('bundle_price', 12, 2)->nullable();        // harga satu paket
    $table->unsignedInteger('stock')->default(0);              // dalam tangkai
    $table->unsignedInteger('reorder_level')->default(30);     // batas stok menipis
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
        Schema::dropIfExists('flowers');
    }
};
