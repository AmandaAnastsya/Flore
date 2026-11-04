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
        Schema::create('reminders', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
    $table->enum('type', ['birthday', 'anniversary', 'recurring_purchase', 'custom']);
    $table->string('title');
    $table->date('occasion_date');                // tanggal acara
    $table->date('remind_at');                    // tanggal reminder dikirim
    $table->enum('channel', ['email', 'whatsapp'])->default('email');
    $table->enum('status', ['pending', 'sent', 'failed'])->default('pending');
    $table->timestamp('sent_at')->nullable();
    $table->text('error_message')->nullable();
    $table->timestamps();
    $table->index(['status', 'remind_at']);       // dipakai Scheduler tiap hari
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reminders');
    }
};
