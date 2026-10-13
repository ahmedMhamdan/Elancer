<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Plan 026: a retired skill stays on the profiles and projects that carry it and is no longer offered.
        Schema::table('skills', function (Blueprint $table) {
            $table->timestamp('retired_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('skills', function (Blueprint $table) {
            $table->dropColumn('retired_at');
        });
    }
};
