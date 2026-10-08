<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workshop_settings', function (Blueprint $table) {
            $table->string('horizontal_logo_path')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('workshop_settings', function (Blueprint $table) {
            $table->dropColumn('horizontal_logo_path');
        });
    }
};
