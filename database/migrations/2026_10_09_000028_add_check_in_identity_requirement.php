<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('check_ins', fn (Blueprint $t) => $t->boolean('requires_identity_verification')->default(true));
    }

    public function down(): void
    {
        Schema::table('check_ins', fn (Blueprint $t) => $t->dropColumn('requires_identity_verification'));
    }
};
