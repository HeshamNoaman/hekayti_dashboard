<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiGeneratedStorySlide extends Model
{
    use HasFactory;

    protected $fillable = [
        'ai_story_id',
        'page_no',
        'image',
        'text',
    ];

    /**
     * Get the story that owns the slide.
     */
    public function story(): BelongsTo
    {
        return $this->belongsTo(AiGeneratedStory::class, 'ai_story_id');
    }
}
