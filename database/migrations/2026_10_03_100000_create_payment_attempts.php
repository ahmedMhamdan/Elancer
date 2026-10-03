<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->timestamp('funded_at')->nullable();
            $table->timestamp('delivery_due_at')->nullable();
        });
        Schema::create('payment_attempts', function (Blueprint $table) {
            $table->id();
            // Application reference sent to the provider and checked on verification.
            $table->uuid('reference')->unique();
            $table->foreignId('contract_id')->constrained()->restrictOnDelete();
            $table->foreignId('payer_id')->constrained('users')->restrictOnDelete();
            // Nullable unique slot: one open attempt per contract on MySQL, PostgreSQL and SQLite.
            $table->foreignId('open_contract_id')->nullable()->unique()->constrained('contracts')->restrictOnDelete();
            $table->uuid('client_token');
            $table->string('provider', 32);
            $table->string('environment', 16)->default('sandbox');
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('status', 32)->default('pending');
            $table->string('provider_reference', 191)->nullable();
            $table->string('failure_reason', 64)->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->unique(['payer_id', 'client_token']);
            $table->unique(['provider', 'environment', 'provider_reference']);
            $table->index(['contract_id', 'id']);
        });
        Schema::create('payment_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_attempt_id')->constrained()->restrictOnDelete();
            $table->string('provider', 32);
            $table->string('environment', 16);
            $table->string('event_reference', 191);
            $table->string('type', 32);
            $table->unsignedBigInteger('amount_minor')->nullable();
            $table->char('currency', 3)->nullable();
            $table->timestamp('created_at');
            $table->unique(['provider', 'environment', 'event_reference']);
        });
        // Provider-side state of the local simulator; the application only reads it through the gateway.
        Schema::create('simulated_payments', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 64)->unique();
            $table->uuid('application_reference');
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('status', 16)->default('created');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('simulated_payments');
        Schema::dropIfExists('payment_events');
        Schema::dropIfExists('payment_attempts');
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn(['funded_at', 'delivery_due_at']);
        });
    }
};
