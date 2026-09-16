<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 160)->nullable();
            $table->text('description')->nullable();
            $table->decimal('budget_min', 10, 2)->nullable();
            $table->decimal('budget_max', 10, 2)->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->json('screening_questions')->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamp('application_closes_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'published_at', 'id']);
        });
        Schema::create('project_skill', function (Blueprint $table) {
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('skill_id')->constrained()->cascadeOnDelete();
            $table->primary(['project_id', 'skill_id']);
            $table->index(['skill_id', 'project_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_skill');
        Schema::dropIfExists('projects');
    }
};
