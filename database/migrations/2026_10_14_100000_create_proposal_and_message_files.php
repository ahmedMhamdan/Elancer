<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Plan 030: a proposal's files. Submit freezes the list into the version the client receives; a file
        // that reached a client is kept for the history when the freelancer removes it from the next version.
        Schema::create('proposal_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proposal_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('path', 191)->unique();
            $table->string('mime', 127);
            $table->unsignedBigInteger('size');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('removed_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['proposal_id', 'id']);
        });
        // Plan 030: files sent with a message. Like the message itself, they cannot be deleted by a participant.
        Schema::create('message_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_message_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('path', 191)->unique();
            $table->string('mime', 127);
            $table->unsignedBigInteger('size');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['conversation_message_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_files');
        Schema::dropIfExists('proposal_files');
    }
};
