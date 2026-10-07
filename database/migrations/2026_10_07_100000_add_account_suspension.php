<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // What the suspended member is told. The internal reason lives only in moderation_events.
            $table->string('suspension_reason', 500)->nullable();
            $table->timestamp('suspended_at')->nullable();
        });
        Schema::table('moderation_events', function (Blueprint $table) {
            // Kept with the event, since reinstating clears it from the account.
            $table->string('member_reason', 500)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('moderation_events', function (Blueprint $table) {
            $table->dropColumn('member_reason');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['suspension_reason', 'suspended_at']);
        });
    }
};
