<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->unsignedSmallInteger('revisions_used')->default(0);
            $table->timestamp('completed_at')->nullable();
        });
        // A formal delivery is immutable: a redelivery is a new row with the next number.
        Schema::create('contract_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('number');
            $table->uuid('client_token');
            $table->text('message');
            $table->json('links');
            $table->timestamp('created_at');
            $table->unique(['contract_id', 'number']);
            $table->unique(['contract_id', 'client_token']);
        });
        Schema::create('contract_submission_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_submission_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('path', 191)->unique();
            $table->string('mime', 127);
            $table->unsignedBigInteger('size');
        });
        // One request per delivery, so a retry can never consume a second round.
        Schema::create('contract_revision_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained()->restrictOnDelete();
            $table->foreignId('contract_submission_id')->unique()->constrained()->restrictOnDelete();
            $table->unsignedInteger('round');
            $table->text('changes');
            $table->timestamp('created_at');
            $table->unique(['contract_id', 'round']);
        });
        Schema::create('contract_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained()->restrictOnDelete();
            $table->foreignId('author_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('subject_id')->constrained('users')->restrictOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('body');
            $table->timestamps();
            $table->unique(['contract_id', 'author_id']);
            $table->index(['subject_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_reviews');
        Schema::dropIfExists('contract_revision_requests');
        Schema::dropIfExists('contract_submission_files');
        Schema::dropIfExists('contract_submissions');
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn(['revisions_used', 'completed_at']);
        });
    }
};
