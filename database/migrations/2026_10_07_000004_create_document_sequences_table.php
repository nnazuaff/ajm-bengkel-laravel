<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('prefix', 10);
            $table->date('date');
            $table->unsignedInteger('value')->default(0);
            $table->unique(['prefix', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_sequences');
    }
};
