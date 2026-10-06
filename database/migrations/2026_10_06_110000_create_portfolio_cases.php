<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portfolio_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Set for completed Elancer work, whose client must approve the public content (Q52).
            $table->foreignId('contract_id')->nullable()->unique()->constrained()->restrictOnDelete();
            // The private working copy; saving it never changes what is public.
            $table->json('content');
            $table->json('public_content')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('hidden_at')->nullable();
            // Q53: the client withdrew permission; only a new approval clears it.
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'id']);
        });
        Schema::create('portfolio_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portfolio_case_id')->constrained()->cascadeOnDelete();
            // Nullable unique slot: one open request per case on MySQL, PostgreSQL and SQLite.
            $table->foreignId('open_case_id')->nullable()->unique()->constrained('portfolio_cases')->cascadeOnDelete();
            // The exact content the client is asked to approve.
            $table->json('content');
            $table->string('status', 16)->default('pending');
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->index(['portfolio_case_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portfolio_approvals');
        Schema::dropIfExists('portfolio_cases');
    }
};
