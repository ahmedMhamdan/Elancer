<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // An image is never replaced in place: the working copy, each approval request and the
        // public version list image ids, so the client approves the exact images (Q52).
        Schema::create('portfolio_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portfolio_case_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->unsignedSmallInteger('width');
            $table->unsignedSmallInteger('height');
            $table->timestamps();
            $table->index(['portfolio_case_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portfolio_images');
    }
};
