<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('check_in_codes', function (Blueprint $t) {
            $t->unsignedTinyInteger('id')->primary();
            $t->text('code')->nullable();
            $t->boolean('active')->default(false);
            $t->timestamp('expires_at')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestamps();
        });
        DB::table('check_in_codes')->insert(['id' => 1, 'active' => false]);
    }

    public function down(): void
    {
        Schema::dropIfExists('check_in_codes');
    }
};
