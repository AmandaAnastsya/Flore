<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Alasan pembatalan, mis. batal otomatis karena tidak dibayar (State Machine hal. 39: autoCancel [payment timeout])
        Schema::table('orders', function (Blueprint $table) {
            $table->string('cancel_reason', 160)->nullable()->after('cancelled_at');
        });

        // Buku Alamat (Site Map hal. 33: Profile → Addresses; mockup hal. 47)
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('label', 40);                 // mis. "Rumah Orang Tua"
            $table->string('recipient_name', 100);
            $table->string('recipient_phone', 20);
            $table->string('city', 100);
            $table->string('address', 500);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->index(['user_id', 'is_default']);
        });

        // Contact Us (Use Case hal. 34)
        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 100);
            $table->string('email', 150);
            $table->string('phone', 20)->nullable();
            $table->string('topic', 30);
            $table->string('order_number', 30)->nullable();
            $table->text('message');
            $table->string('status', 20)->default('new');   // new | replied | closed
            $table->text('admin_note')->nullable();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_messages');
        Schema::dropIfExists('addresses');
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('cancel_reason'));
    }
};
