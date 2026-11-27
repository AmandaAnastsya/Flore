<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Preferensi email update status pesanan (invoice & email penting tetap selalu dikirim)
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('order_updates_enabled')->default(true)->after('reminder_days');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('order_updates_enabled'));
    }
};
