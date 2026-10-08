<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workshop_settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary()->default(1);
            $table->string('name', 120);
            $table->string('phone', 30)->default('');
            $table->text('address');
            $table->text('receipt_footer');
            $table->string('logo_path')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workshop_settings');
    }
};
