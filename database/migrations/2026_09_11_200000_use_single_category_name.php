<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->renameColumn('name_en', 'categoryname');
            // Archive existing translations rather than discard user data.
            $table->renameColumn('name_ar', 'legacy_name_ar');
        });
        Schema::table('categories', function (Blueprint $table) {
            $table->string('legacy_name_ar', 120)->nullable()->change();
        });
    }

    public function down(): void
    {
        // New single-name records need a value for the old required Arabic column.
        DB::table('categories')->whereNull('legacy_name_ar')->update([
            'legacy_name_ar' => DB::raw('categoryname'),
        ]);
        Schema::table('categories', function (Blueprint $table) {
            $table->string('legacy_name_ar', 120)->nullable(false)->change();
        });
        Schema::table('categories', function (Blueprint $table) {
            $table->renameColumn('categoryname', 'name_en');
            $table->renameColumn('legacy_name_ar', 'name_ar');
        });
    }
};
