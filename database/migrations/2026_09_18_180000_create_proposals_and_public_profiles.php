<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->string('availability', 20)->default('available');
            $table->json('professional_links')->nullable();
        });
        Schema::create('proposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('draft');
            $table->string('organization', 20)->default('received');
            $table->text('client_note')->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->unsignedInteger('duration_days')->nullable();
            $table->json('draft')->nullable();
            $table->json('content')->nullable();
            $table->json('profile_snapshot')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
            $table->unique(['project_id', 'user_id']);
            $table->index(['user_id', 'updated_at']);
        });
        Schema::create('proposal_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proposal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('kind', 20);
            $table->json('content')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proposal_events');
        Schema::dropIfExists('proposals');
        Schema::table('profiles', fn (Blueprint $table) => $table->dropColumn(['availability', 'professional_links']));
    }
};
