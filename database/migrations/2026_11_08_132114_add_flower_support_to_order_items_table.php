<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            // baris order bisa berisi bouquet ATAU bunga per tangkai
            $table->unsignedBigInteger('bouquet_id')->nullable()->change();
            $table->string('bouquet_name')->nullable()->change();

            $table->foreignId('flower_id')->nullable()->after('bouquet_id')->constrained()->restrictOnDelete();
            $table->string('flower_name')->nullable()->after('bouquet_name');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('flower_id');
            $table->dropColumn('flower_name');
        });
    }
};