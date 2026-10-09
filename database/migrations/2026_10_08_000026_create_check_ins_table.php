<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('check_ins', function (Blueprint $t) {
            $t->id();
            $t->foreignId('customer_id')->constrained()->restrictOnDelete();
            $t->string('status', 30)->default('waiting')->index();
            $t->timestamp('checked_in_at')->index();
            $t->timestamp('processed_at')->nullable();
            $t->foreignId('processed_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->foreignId('service_order_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $t->timestamps();
            $t->index(['customer_id', 'status', 'checked_in_at'], 'check_ins_waiting_customer_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('check_ins');
    }
};
