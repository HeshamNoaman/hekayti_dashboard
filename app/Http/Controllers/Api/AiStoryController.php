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
        // Set execution time to 5 minutes
        set_time_limit(300);
        ini_set('max_execution_time', 300);

        try {
            // Validate input
            $validator = Validator::make($request->all(), [
                'hero_name' => 'required|string|max:50',
                'painting_style' => 'required|string|max:50',
                'story_topic' => 'required|string|max:50',
            ]);

            if ($validator->fails()) {

                if ($request->has('redirect') && $request->redirect == 'web') {
                    return redirect()->route('ai-stories.create')
                        ->with('error', $validator->errors()->first())
                        ->withInput();
                }

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
            if (empty($storySegments) || count($storySegments) == 0) {
                throw new Exception('Failed to generate story segments');
            }

            // Save the story to the database
            $aiGeneratedStory = AiGeneratedStory::create([
                'name' => $storyName,
                'cover_photo' => $storySegments[0]['localImagePath'],
                'hero_name' => $request->hero_name,
                'painting_style' => $request->painting_style,
                'story_topic' => $request->story_topic,
                'story_data' => $storySegments, // Keep the original data for reference
                'status' => 'completed',
            ]);

            // Create slides for each segment of the story
            foreach ($storySegments as $index => $segment) {
                if (isset($segment['نص_القصة']) && (isset($segment['localImagePath']) || isset($segment['imageUrl']))) {
                    $aiGeneratedStory->slides()->create([
                        'page_no' => $index, // Start with 0 for cover
                        'image' => $segment['localImagePath'] ?? ($segment['imageUrl'] ?? ''),
                        'text' => $segment['نص_القصة'],
                    ]);
                }
            }

            // Get the story with its slides
            $aiGeneratedStory->load('slides');

            // If this is a web request with redirect parameter, redirect to the story view
            if ($request->has('redirect') && $request->redirect == 'web') {
                return redirect()->route('ai-stories.show', $aiGeneratedStory->id)
                    ->with('success', 'تم إنشاء القصة بنجاح');
            }

            return $this->returnData(
                200,
                'Story generated successfully',
                'story',
                $aiGeneratedStory
            );
        } catch (Exception $e) {
            Log::error('Error generating story: ' . $e->getMessage());

            // If this is a web request with redirect parameter, redirect back with error
            if ($request->has('redirect') && $request->redirect == 'web') {
                return redirect()->route('ai-stories.create')
                    ->with('error', 'حدث خطأ أثناء إنشاء القصة: ' . $e->getMessage());
            }

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
