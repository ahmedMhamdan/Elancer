<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Q68: hiding keeps the row as it was; only these two columns say that moderation hid it. */
    private const TABLES = ['conversation_messages', 'projects', 'portfolio_cases'];

    public function up(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->timestamp('moderated_at')->nullable();
                $table->foreignId('moderated_by')->nullable()->constrained('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropConstrainedForeignId('moderated_by');
                $table->dropColumn('moderated_at');
            });
        }
    }
};
