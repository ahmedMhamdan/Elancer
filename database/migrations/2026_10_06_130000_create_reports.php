<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            // Reports are evidence: closing an account never removes them.
            $table->foreignId('reporter_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('target_type', 16);
            $table->unsignedBigInteger('target_id');
            // The member whose content or conduct is reported.
            $table->foreignId('subject_id')->nullable()->constrained('users')->nullOnDelete();
            // Set for message and contract reports; the only conversation a reviewer may read (Q65).
            $table->foreignId('conversation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reason', 24);
            $table->text('explanation');
            // What the reporter saw, since public content can change after the report.
            $table->json('snapshot');
            $table->string('status', 16)->default('submitted');
            $table->string('outcome', 24)->nullable();
            $table->foreignId('handler_id')->nullable()->constrained('users')->nullOnDelete();
            // Nullable unique slot: one open report per member per target on MySQL, PostgreSQL and SQLite.
            $table->string('open_key', 64)->nullable()->unique();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'id']);
            $table->index(['reporter_id', 'id']);
            $table->index(['subject_id', 'id']);
        });
        Schema::create('report_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['report_id', 'id']);
        });
        // The audit log of administrator actions on reports. It never stores message text.
        Schema::create('moderation_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_email')->nullable();
            $table->string('action', 32);
            $table->foreignId('report_id')->nullable()->constrained()->nullOnDelete();
            $table->string('target_type', 16)->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->unsignedBigInteger('conversation_id')->nullable();
            $table->foreignId('subject_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason', 1000)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['report_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('moderation_events');
        Schema::dropIfExists('report_notes');
        Schema::dropIfExists('reports');
    }
};
