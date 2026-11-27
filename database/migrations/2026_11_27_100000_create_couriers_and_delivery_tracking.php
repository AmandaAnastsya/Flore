<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Armada kurir toko (Logistics & Courier Dispatch — proposal hal. 50)
        Schema::create('couriers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('phone', 20);
            $table->enum('vehicle', ['motor', 'mobil', 'instan'])->default('motor');
            $table->string('plate_number', 15)->nullable();
            $table->string('notes', 160)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('courier_id')->nullable()->after('payment_proof')->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('travel_minutes')->nullable()->after('courier_id');   // estimasi waktu tempuh
            $table->timestamp('dispatched_at')->nullable()->after('travel_minutes');
            $table->timestamp('eta_at')->nullable()->after('dispatched_at');
            $table->timestamp('delivered_at')->nullable()->after('eta_at');
            $table->string('received_by', 80)->nullable()->after('delivered_at');
            $table->string('delivery_photo')->nullable()->after('received_by');                // bukti serah terima (POD), disk privat
            $table->string('delivery_issue', 255)->nullable()->after('delivery_photo');
            $table->timestamp('issue_reported_at')->nullable()->after('delivery_issue');
            $table->index(['status', 'delivery_date']);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['status', 'delivery_date']);
            $table->dropConstrainedForeignId('courier_id');
            $table->dropColumn(['travel_minutes', 'dispatched_at', 'eta_at', 'delivered_at', 'received_by',
                'delivery_photo', 'delivery_issue', 'issue_reported_at']);
        });

        Schema::dropIfExists('couriers');
    }
};
