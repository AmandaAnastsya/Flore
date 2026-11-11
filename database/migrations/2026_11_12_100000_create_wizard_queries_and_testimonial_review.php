<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Log setiap pemakaian Smart Recommendation Wizard (KPI "Wizard Queries" & preferensi customer)
        Schema::create('wizard_queries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('occasion', 50)->nullable();
            $table->string('color', 50)->nullable();
            $table->string('style', 50)->nullable();
            $table->string('size', 50)->nullable();
            $table->string('budget', 50)->nullable();
            $table->string('mode', 20);
            $table->unsignedSmallInteger('result_count')->default(0);
            $table->timestamps();
            $table->index('created_at');
        });

        // Testimonial: status ditolak + satu testimonial per pesanan
        Schema::table('testimonials', function (Blueprint $table) {
            $table->timestamp('rejected_at')->nullable()->after('approved_at');
            $table->unique('order_id');
        });
    }

    public function down(): void
    {
        Schema::table('testimonials', function (Blueprint $table) {
            $table->dropUnique(['order_id']);
            $table->dropColumn('rejected_at');
        });

        Schema::dropIfExists('wizard_queries');
    }
};
