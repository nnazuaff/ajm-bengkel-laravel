<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipts', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('receipt_number')->unique();
            $table->foreignId('service_order_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('cashier_id')->constrained('users')->restrictOnDelete();
            $table->dateTime('transaction_date')->index();
            $table->string('status')->default('draft')->index();
            $table->string('payment_status')->default('unpaid')->index();
            foreach (['subtotal', 'discount', 'grand_total'] as $column) {
                $table->decimal($column, 14, 2)->default(0);
            }
            $table->text('notes')->nullable();
            foreach (['workshop_snapshot', 'customer_snapshot', 'vehicle_snapshot'] as $column) {
                $table->json($column)->nullable();
            }
            $table->text('void_reason')->nullable();
            $table->dateTime('voided_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipts');
    }
};
