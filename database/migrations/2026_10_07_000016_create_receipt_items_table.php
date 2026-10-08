<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipt_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receipt_id')->constrained()->restrictOnDelete();
            $table->string('description', 255);
            $table->string('type');
            $table->unsignedInteger('quantity');
            foreach (['unit_price', 'discount', 'total'] as $column) {
                $table->decimal($column, 14, 2)->default(0);
            }
            $table->foreignId('inventory_item_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('service_job_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipt_items');
    }
};
