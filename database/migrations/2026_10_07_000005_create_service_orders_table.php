<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_orders', function (Blueprint $table) {
            $table->id();
            $table->string('service_number', 30)->unique();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('vehicle_id')->constrained()->restrictOnDelete();
            $table->foreignId('mechanic_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('received_by')->constrained('users')->restrictOnDelete();
            $table->string('source', 20)->default('walk_in');
            $table->unsignedInteger('current_mileage');
            $table->text('complaint');
            $table->text('diagnosis')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 30)->default('waiting');
            $table->timestamp('received_at')->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'received_at']);
            $table->index(['mechanic_id', 'status']);
            $table->index(['vehicle_id', 'received_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_orders');
    }
};
