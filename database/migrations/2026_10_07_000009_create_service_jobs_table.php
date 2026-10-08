<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_order_id')->constrained()->restrictOnDelete();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->foreignId('mechanic_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->decimal('labor_price', 14, 2)->default('0.00');
            $table->string('status', 20)->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['service_order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_jobs');
    }
};
