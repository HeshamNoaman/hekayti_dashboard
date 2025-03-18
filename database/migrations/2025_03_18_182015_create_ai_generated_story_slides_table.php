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
        Schema::create('ai_generated_story_slides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_story_id')->constrained('ai_generated_stories')->onDelete('cascade');
            $table->integer('page_no');
            $table->string('image');
            $table->text('text');
            $table->timestamps();

            // Make sure page numbers are unique per story
            $table->unique(['ai_story_id', 'page_no']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_generated_story_slides');
    }
};
