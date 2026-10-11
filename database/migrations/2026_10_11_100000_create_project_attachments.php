<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Q11: a brief's private files. The public page shows how many there are, never where they are stored.
        Schema::create('project_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('path', 191)->unique();
            $table->string('mime', 127);
            $table->unsignedBigInteger('size');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['project_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_attachments');
    }
};
