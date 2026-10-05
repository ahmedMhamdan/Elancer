<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Accepted changes sit beside the agreement, which stays as it was accepted.
        Schema::table('contracts', function (Blueprint $table) {
            $table->unsignedSmallInteger('extra_rounds')->default(0);
            $table->timestamp('revision_due_at')->nullable();
        });
        Schema::create('contract_amendments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained()->restrictOnDelete();
            // Nullable unique slot: one pending amendment per contract on MySQL, PostgreSQL and SQLite.
            $table->foreignId('open_contract_id')->nullable()->unique()->constrained('contracts')->restrictOnDelete();
            $table->foreignId('proposer_id')->constrained('users')->restrictOnDelete();
            $table->text('reason');
            // Which date the proposal moves: the first delivery or the current revision.
            $table->string('date_kind', 16)->nullable();
            $table->timestamp('new_due_at')->nullable();
            $table->timestamp('previous_due_at')->nullable();
            $table->unsignedSmallInteger('extra_rounds')->default(0);
            // The round the proposal was made in, so a later round cannot accept it.
            $table->unsignedSmallInteger('revisions_used');
            $table->string('status', 16)->default('pending');
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->index(['contract_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_amendments');
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn(['extra_rounds', 'revision_due_at']);
        });
    }
};
