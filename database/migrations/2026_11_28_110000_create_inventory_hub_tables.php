<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Fresh Floral & Supply Inventory Hub (mockup hal. 49)
    public function up(): void
    {
        Schema::table('flowers', function (Blueprint $table) {
            $table->decimal('cost_per_stem', 12, 2)->nullable()->after('bundle_price');   // HPP per tangkai
            $table->unsignedTinyInteger('shelf_life_days')->nullable()->after('reorder_level');   // umur segar (hari)
            $table->timestamp('last_restocked_at')->nullable()->after('shelf_life_days');        // tanggal bunga tiba
        });

        // Pita & wrapping paper, vas & aksesoris
        Schema::create('supplies', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['wrapping', 'vase']);
            $table->string('name', 100);
            $table->string('variant', 100)->nullable();          // kategori / tone, mis. "Satin · Dusty Pink"
            $table->string('unit', 20)->default('pcs');          // meter, lembar, roll, pcs
            $table->unsignedInteger('stock')->default(0);
            $table->unsignedInteger('reorder_level')->default(10);
            $table->decimal('cost_per_unit', 12, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // Riwayat restock, koreksi stok & bunga layu yang dibuang (untuk Inventory Report)
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->string('item_type', 20);                    // flower | supply | bouquet
            $table->unsignedBigInteger('item_id');
            $table->string('item_name', 150);                   // salinan nama saat itu
            $table->enum('reason', ['restock', 'adjustment', 'waste']);
            $table->integer('change');
            $table->unsignedInteger('stock_after');
            $table->decimal('unit_cost', 12, 2)->nullable();
            $table->string('note', 200)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->index(['item_type', 'item_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('supplies');
        Schema::table('flowers', fn (Blueprint $table) => $table->dropColumn(['cost_per_stem', 'shelf_life_days', 'last_restocked_at']));
    }
};
