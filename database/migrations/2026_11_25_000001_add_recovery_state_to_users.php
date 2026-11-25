<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->uuid('recovery_generation')->nullable();
            $table->string('pending_email')->nullable();
            $table->string('email_change_hash', 64)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['recovery_generation', 'pending_email', 'email_change_hash']);
        });
    }
};
