<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('booking_number', 30)->unique();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('name', 120);
            $table->string('phone', 20);
            $table->string('email', 254)->nullable();
            $table->string('license_plate', 20);
            $table->string('brand', 60);
            $table->string('model', 100);
            $table->unsignedSmallInteger('year')->nullable();
            $table->unsignedInteger('current_mileage');
            $table->date('booking_date')->index();
            $table->time('arrival_time');
            $table->string('service_type', 100);
            $table->text('complaint');
            $table->text('notes')->nullable();
            $table->text('admin_notes')->nullable();
            $table->string('status', 30)->default('pending')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
