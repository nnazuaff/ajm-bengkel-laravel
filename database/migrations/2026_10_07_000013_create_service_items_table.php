<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_order_id')->constrained()->restrictOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->restrictOnDelete();
            $table->string('description', 120);
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 14, 2);
            $table->decimal('subtotal', 14, 2);
            $table->foreignId('used_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('returned_at')->nullable();
            $table->timestamps();
            $table->index(['service_order_id', 'returned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_items');
    }
};
