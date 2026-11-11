<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Momen orang terdekat yang diingatkan tiap tahun (mis. "Ulang Tahun Ibu")
        Schema::create('special_moments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['birthday', 'anniversary', 'custom'])->default('custom');
            $table->string('title', 100);
            $table->date('date');
            $table->timestamps();
            $table->index(['user_id', 'date']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('reminders_enabled')->default(true)->after('anniversary_date');
            $table->unsignedTinyInteger('reminder_days')->default(7)->after('reminders_enabled');
        });

        Schema::table('reminders', function (Blueprint $table) {
            $table->foreignId('special_moment_id')->nullable()->after('order_id')
                ->constrained()->nullOnDelete();
            $table->string('dedupe_key', 120)->nullable()->unique()->after('special_moment_id');
        });
    }

    public function down(): void
    {
        Schema::table('reminders', function (Blueprint $table) {
            $table->dropUnique(['dedupe_key']);
            $table->dropColumn('dedupe_key');
            $table->dropConstrainedForeignId('special_moment_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['reminders_enabled', 'reminder_days']);
        });

        Schema::dropIfExists('special_moments');
    }
};
