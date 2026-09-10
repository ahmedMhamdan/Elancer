<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('workspace_role', 20)->nullable();
        });

        Schema::table('profiles', function (Blueprint $table) {
            $table->string('country', 100)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('company', 120)->nullable();
            $table->json('skills')->nullable();
            $table->string('photo_path')->nullable();
            $table->string('location', 255)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->dropColumn(['country', 'city', 'company', 'skills', 'photo_path']);
            // Leave location at 255: shrinking it could truncate existing user data.
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('workspace_role');
        });
    }
};
