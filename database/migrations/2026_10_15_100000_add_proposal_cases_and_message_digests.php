<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Plan 031: the public version of a case study as a proposal sent it (Q55). A submitted version names
        // these rows, so the client reads what was sent even after the freelancer hides or changes the case.
        Schema::create('proposal_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proposal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('portfolio_case_id')->constrained()->cascadeOnDelete();
            $table->json('content');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['proposal_id', 'portfolio_case_id']);
            $table->index('portfolio_case_id');
        });
        // Plan 031: when each participant was last emailed about this conversation, for the one-an-hour bound.
        Schema::table('conversations', function (Blueprint $table) {
            $table->timestamp('client_emailed_at')->nullable();
            $table->timestamp('freelancer_emailed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn(['client_emailed_at', 'freelancer_emailed_at']);
        });
        Schema::dropIfExists('proposal_cases');
    }
};
