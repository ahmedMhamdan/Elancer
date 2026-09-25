<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proposal_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('freelancer_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('client_read_through')->default(0);
            $table->unsignedBigInteger('freelancer_read_through')->default(0);
            $table->boolean('client_archived')->default(false);
            $table->boolean('freelancer_archived')->default(false);
            $table->timestamps();
            $table->index(['client_id', 'updated_at']);
            $table->index(['freelancer_id', 'updated_at']);
        });
        Schema::create('conversation_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->uuid('client_token');
            $table->text('body');
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('edited_at')->nullable();
            $table->timestamps();
            $table->unique(['conversation_id', 'sender_id', 'client_token'], 'conversation_message_idempotency');
            $table->index(['conversation_id', 'id']);
        });
        Schema::create('message_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_message_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->unsignedInteger('version');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['conversation_message_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_revisions');
        Schema::dropIfExists('conversation_messages');
        Schema::dropIfExists('conversations');
    }
};
