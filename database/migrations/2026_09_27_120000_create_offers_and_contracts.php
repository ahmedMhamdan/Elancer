<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('proposal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('freelancer_id')->constrained('users')->cascadeOnDelete();
            // Nullable unique slot works on MySQL, PostgreSQL and SQLite.
            $table->foreignId('pending_project_id')->nullable()->unique()->constrained('projects')->cascadeOnDelete();
            $table->uuid('client_token');
            $table->json('terms');
            $table->string('status', 32)->default('pending');
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('expires_at');
            $table->timestamp('closed_at')->nullable();
            $table->text('reason')->nullable();
            $table->timestamps();
            $table->unique(['client_id', 'client_token']);
            $table->index(['project_id', 'status']);
            $table->index(['freelancer_id', 'id']);
        });
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('proposal_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('offer_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('client_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('freelancer_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('conversation_id')->unique()->constrained()->restrictOnDelete();
            $table->json('agreement');
            $table->string('status', 32)->default('awaiting_payment');
            $table->timestamps();
            $table->index(['client_id', 'id']);
            $table->index(['freelancer_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contracts');
        Schema::dropIfExists('offers');
    }
};
