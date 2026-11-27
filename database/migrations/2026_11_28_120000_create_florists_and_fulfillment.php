<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Florist Assignment & tahap fulfillment (mockup hal. 48)
    public function up(): void
    {
        Schema::create('florists', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('phone', 20)->nullable();
            $table->string('specialty', 120)->nullable();   // mis. "Hand bouquet & wedding"
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('florist_id')->nullable()->after('courier_id')->constrained()->nullOnDelete();
            $table->string('fulfillment_stage', 20)->nullable()->after('florist_id');   // arranging | qc | packing | ready
            $table->timestamp('stage_updated_at')->nullable()->after('fulfillment_stage');
            $table->string('qc_photo')->nullable()->after('stage_updated_at');           // disk privat
            $table->timestamp('qc_passed_at')->nullable()->after('qc_photo');
            $table->string('production_note', 255)->nullable()->after('qc_passed_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('florist_id');
            $table->dropColumn(['fulfillment_stage', 'stage_updated_at', 'qc_photo', 'qc_passed_at', 'production_note']);
        });
        Schema::dropIfExists('florists');
    }
};
