<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Q83: what a hidden review said before its author changed it. Never shown to participants.
        Schema::create('contract_review_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_review_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('body');
            // When the replaced version was written, not when it was replaced.
            $table->timestamp('created_at');
            $table->index(['contract_review_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_review_revisions');
    }
};
