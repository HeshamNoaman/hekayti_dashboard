<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ai_generated_stories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('cover_photo');
            $table->string('hero_name');
            $table->string('painting_style');
            $table->string('story_topic');
            $table->json('story_data');
            $table->string('status')->default('completed');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_generated_stories');
    }
};
