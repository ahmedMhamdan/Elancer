<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->timestamp('cancelled_at')->nullable();
        });
        Schema::create('contract_cancellations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained()->restrictOnDelete();
            // Nullable unique slot: one unresolved request per contract on MySQL, PostgreSQL and SQLite.
            $table->foreignId('open_contract_id')->nullable()->unique()->constrained('contracts')->restrictOnDelete();
            $table->foreignId('requester_id')->constrained('users')->restrictOnDelete();
            $table->text('reason');
            // The operating state to return to when the request is declined or withdrawn.
            $table->string('prior_status', 32);
            $table->string('status', 32)->default('pending');
            $table->string('refund_status', 16)->nullable();
            $table->string('refund_reference', 191)->nullable();
            $table->string('refund_failure', 64)->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();
            $table->index(['contract_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_cancellations');
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn('cancelled_at');
        });
    }
};
