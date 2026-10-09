<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('email')->nullable()->change();
            $t->timestamp('identity_verified_at')->nullable();
        });
        Schema::table('check_ins', function (Blueprint $t) {
            $t->foreignId('account_id')->nullable()->constrained('users')->restrictOnDelete();
            $t->string('browser_token_hash', 64)->nullable();
            $t->timestamp('browser_access_expires_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('check_ins', function (Blueprint $t) {
            $t->dropConstrainedForeignId('account_id');
            $t->dropColumn(['browser_token_hash', 'browser_access_expires_at']);
        });
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('identity_verified_at'));
        // Email remains nullable: reversing this would destroy passwordless customers.
    }
};
