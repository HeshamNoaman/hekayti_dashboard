<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\AiStoryService;
use App\Models\AiGeneratedStory;
use App\Models\AiGeneratedStorySlide;
use App\Traits\GeneralTrait;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class AiStoryController extends Controller
{
    use GeneralTrait;

    protected $aiStoryService;

    public function __construct(AiStoryService $aiStoryService)
    {
        $this->aiStoryService = $aiStoryService;
    }

    /**
     * Generate a new AI story
     */
    public function generateStory(Request $request)
    {
        try {
            // Validate input
            $validator = Validator::make($request->all(), [
                'hero_name' => 'required|string|max:50',
                'painting_style' => 'required|string|max:50',
                'story_topic' => 'required|string|max:50',
            ]);

            if ($validator->fails()) {
                return $this->returnError(400, $validator->errors()->first());
            }

            // Generate the story
            $storyResult = $this->aiStoryService->generateStory(
                $request->hero_name,
                $request->painting_style,
                $request->story_topic
            );

            // Extract data from result
            $storyName = $storyResult['name'] ?? "مغامرة " . $request->hero_name . " في " . $request->story_topic;
            $storySegments = $storyResult['segments'] ?? [];

            // Ensure we have segments
            if (empty($storySegments)) {
                throw new Exception('Failed to generate story segments');
            }

            // Get the cover photo from the first segment
            $coverPhoto = null;
            if (isset($storySegments[0]['localImagePath'])) {
                $coverPhoto = $storySegments[0]['localImagePath'];
            }

            // If no cover photo is available, throw an error
            if (!$coverPhoto) {
                throw new Exception('Failed to generate cover image for the story');
            }

            // Save the story to the database
            $aiGeneratedStory = AiGeneratedStory::create([
                'name' => $storyName,
                'cover_photo' => $coverPhoto,
                'hero_name' => $request->hero_name,
                'painting_style' => $request->painting_style,
                'story_topic' => $request->story_topic,
                'story_data' => $storySegments, // Keep the original data for reference
                'status' => 'completed',
            ]);

            // Create slides for each segment of the story
            foreach ($storySegments as $index => $segment) {
                if (isset($segment['نص_القصة']) && (isset($segment['localImagePath']) || isset($segment['imageUrl']))) {
                    AiGeneratedStorySlide::create([
                        'ai_story_id' => $aiGeneratedStory->id,
                        'page_no' => $index, // Start with 0 for cover
                        'image' => $segment['localImagePath'] ?? ($segment['imageUrl'] ?? ''),
                        'text' => $segment['نص_القصة'],
                    ]);
                }
            }

            // Get the story with its slides
            $aiGeneratedStory->load('slides');

            return $this->returnData(
                200,
                'Story generated successfully',
                'story',
                $aiGeneratedStory
            );
        } catch (Exception $e) {
            Log::error('Error generating story: ' . $e->getMessage());
            return $this->returnError(500, 'Error generating story: ' . $e->getMessage());
        }
    }

    /**
     * Get all AI generated stories
     */
    public function getAllStories()
    {
        try {
            $stories = AiGeneratedStory::with('slides')
                ->orderBy('id', 'desc')
                ->get();

            if ($stories->isEmpty()) {
                return $this->returnError(404, 'No AI generated stories found.');
            }

            return $this->returnData(200, 'AI stories retrieved', 'stories', $stories);
        } catch (Exception $e) {
            Log::error('Error retrieving AI stories: ' . $e->getMessage());
            return $this->returnError(500, 'Error retrieving AI stories: ' . $e->getMessage());
        }
    }

    /**
     * Get a specific AI generated story by ID
     */
    public function getStory($id)
    {
        try {
            $story = AiGeneratedStory::with(['slides' => function ($query) {
                $query->orderBy('page_no', 'asc');
            }])->find($id);

            if (!$story) {
                return $this->returnError(404, 'AI story not found.');
            }

            return $this->returnData(200, 'AI story retrieved', 'story', $story);
        } catch (Exception $e) {
            Log::error('Error retrieving AI story: ' . $e->getMessage());
            return $this->returnError(500, 'Error retrieving AI story: ' . $e->getMessage());
        }
    }
}
