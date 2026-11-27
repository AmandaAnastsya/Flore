<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // bouquet teratas yang direkomendasikan (untuk "Kombinasi Bunga Populer" di Smart Wizard & AI Insights)
        Schema::table('wizard_queries', function (Blueprint $table) {
            $table->json('bouquet_ids')->nullable()->after('result_count');
        });
    }

    public function down(): void
    {
        Schema::table('wizard_queries', function (Blueprint $table) {
            $table->dropColumn('bouquet_ids');
        });
    }
};
