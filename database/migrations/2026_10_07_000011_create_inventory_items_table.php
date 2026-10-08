<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 60)->unique();
            $table->string('name', 120);
            $table->foreignId('category_id')->nullable()->constrained('inventory_categories')->restrictOnDelete();
            $table->string('brand', 120)->nullable();
            $table->decimal('purchase_price', 14, 2)->default('0.00');
            $table->decimal('selling_price', 14, 2)->default('0.00');
            $table->unsignedInteger('current_stock')->default(0);
            $table->unsignedInteger('minimum_stock')->default(0);
            $table->string('unit', 30);
            $table->string('supplier', 120)->nullable();
            $table->string('storage_location', 120)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['is_active', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
    }
};
