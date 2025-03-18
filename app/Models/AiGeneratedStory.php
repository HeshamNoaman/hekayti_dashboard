<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiGeneratedStory extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'cover_photo',
        'hero_name',
        'painting_style',
        'story_topic',
        'story_data',
        'status',
    ];

    protected $casts = [
        'story_data' => 'array',
    ];

    /**
     * Get the slides for the AI-generated story.
     */
    public function slides(): HasMany
    {
        return $this->hasMany(AiGeneratedStorySlide::class, 'ai_story_id');
    }
}
