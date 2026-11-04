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
        Schema::create('orders', function (Blueprint $table) {
    $table->id();
    $table->string('order_number')->unique();
    $table->foreignId('user_id')->constrained()->restrictOnDelete();

    // penerima & pengiriman
    $table->string('recipient_name');
    $table->string('recipient_phone', 20);
    $table->string('delivery_city');
    $table->text('delivery_address');
    $table->date('delivery_date');
    $table->enum('delivery_slot', ['morning', 'afternoon', 'evening'])->nullable();

    // kartu ucapan & occasion
    $table->string('occasion')->nullable();
    $table->text('card_message')->nullable();
    $table->string('sender_name')->nullable();
    $table->boolean('is_anonymous')->default(false);

    // harga
    $table->decimal('subtotal', 12, 2);
    $table->decimal('delivery_fee', 12, 2)->default(0);
    $table->decimal('discount', 12, 2)->default(0);
    $table->decimal('total_amount', 12, 2);

    // status order (sesuai State Machine) & pembayaran
    $table->enum('status', [
        'created', 'pending_payment', 'payment_confirmed',
        'preparing', 'out_for_delivery', 'delivered', 'cancelled',
    ])->default('created');
    $table->enum('payment_status', ['unpaid', 'proof_uploaded', 'confirmed', 'rejected'])
          ->default('unpaid');
    $table->string('payment_proof')->nullable();
    $table->timestamp('paid_at')->nullable();
    $table->timestamp('cancelled_at')->nullable();

    $table->timestamps();
    $table->index(['status', 'payment_status']);
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
