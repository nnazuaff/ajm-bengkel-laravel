<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_documentations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_order_id')->constrained()->restrictOnDelete();
            $table->foreignId('service_job_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('disk', 20)->default('local');
            $table->string('path')->unique();
            $table->string('category', 20);
            $table->string('caption', 1000)->nullable();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['service_order_id', 'deleted_at', 'created_at'], 'documentation_order_history_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_documentations');
    }
};
