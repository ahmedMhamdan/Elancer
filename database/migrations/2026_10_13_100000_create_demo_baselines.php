<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Plan 027: the demo accounts' rows as saved by a super administrator, which a reset returns to.
        Schema::create('demo_baselines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('taken_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('taken_at');
            // Rows per table, for the administration page; the copy itself is compressed.
            $table->json('summary');
            $table->longText('payload');
            // A demo session's first and latest change since the last reset; both cleared by a reset.
            $table->timestamp('changed_since')->nullable();
            $table->timestamp('changed_at')->nullable();
            $table->timestamp('restored_at')->nullable();
            // Why the last reset was refused, until one succeeds.
            $table->string('problem', 500)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demo_baselines');
    }
};
