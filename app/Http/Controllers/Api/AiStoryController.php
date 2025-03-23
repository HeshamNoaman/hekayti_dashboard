<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\AiStoryService;
use App\Models\AiGeneratedStory;
use App\Traits\GeneralTrait;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

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
        // Validate input
        $validator = Validator::make($request->all(), [
            'hero_name' => 'required|string|max:50',
            'painting_style' => 'required|string|max:50',
            'story_topic' => 'required|string|max:50',
        ]);

        if ($validator->fails()) {
            return $this->handleError($request, new ValidationException($validator));
        }

        try {
            // Set execution time using config
            $timeout = config('app.story_generation_timeout', 120);
            set_time_limit($timeout);
            ini_set('max_execution_time', $timeout);

            $storyResult = $this->aiStoryService->generateStory(
                $request->hero_name,
                $request->painting_style,
                $request->story_topic
            );

            // Move validation logic to a dedicated service method
            $this->validateStoryResult($storyResult);

            // Create story record and slides in a database transaction
            $aiGeneratedStory = $this->saveStoryWithSlides($request, $storyResult['segments']);

            return $this->handleResponse($request, $aiGeneratedStory);
        } catch (Exception $e) {
            Log::error('Error generating story', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->handleError($request, $e);
        }
    }

    /**
     * Validate the story result structure
     *
     * @param array $storyResult
     * @throws Exception
     */
    private function validateStoryResult(array $storyResult): void
    {
        $validationErrors = collect([
            'segments' => fn() => empty($storyResult['segments']) ? 'segments array is empty' : null,
            'segments_type' => fn() => !is_array($storyResult['segments']) ? 'segments is not an array' : null,
            'segments_count' => fn() => count($storyResult['segments']) < $storyResult['slideCount']
                ? sprintf(
                    'segments count (%d) is less than expected slideCount (%d)',
                    count($storyResult['segments']),
                    $storyResult['slideCount']
                )
                : null,
            'image_path' => fn() => empty($storyResult['segments'][0]['localImagePath']) ? 'first segment has no localImagePath' : null,
            'story_text' => fn() => empty($storyResult['segments'][0]['Story_Text']) ? 'first segment has no Story_Text' : null,
        ])
            ->map(fn($check) => $check())
            ->filter();

        if ($validationErrors->isNotEmpty()) {
            Log::error('Story validation errors', [
                'errors' => $validationErrors->toArray(),
                'story_result' => $storyResult
            ]);

            throw new Exception('Invalid story result structure: ' . $validationErrors->implode(', '));
        }
    }

    /**
     * Handle the response based on request type
     */
    private function handleResponse(Request $request, AiGeneratedStory $story)
    {
        if ($request->input('redirect') === 'web') {
            return redirect()
                ->route('ai-stories.show', $story->id)
                ->with('success', __('messages.story.created_successfully'));
        }

        return $this->returnData(200, 'Story generated successfully', 'story', $story);
    }

    /**
     * Handle error response based on request type
     */
    private function handleError(Request $request, Exception $e)
    {
        if ($request->input('redirect') === 'web') {
            return redirect()
                ->route('ai-stories.create')
                ->with('error', __('messages.story.creation_failed', ['error' => $e->getMessage()]))
                ->withInput();
        }

        return $this->returnError(500, 'Error generating story: ' . $e->getMessage());
    }

    /**
     * Save the story and its slides to the database
     *
     * @param Request $request
     * @param array $storySegments
     * @return AiGeneratedStory
     */
    private function saveStoryWithSlides(Request $request, array $storySegments)
    {
        return DB::transaction(function () use ($request, $storySegments) {
            // Create the story record
            $aiGeneratedStory = AiGeneratedStory::create([
                'name' => $storySegments[0]['Story_Text'],
                'cover_photo' => $storySegments[0]['localImagePath'],
                'hero_name' => $request->hero_name,
                'painting_style' => $request->painting_style,
                'story_topic' => $request->story_topic,
                'story_data' => $storySegments,
                'status' => 'completed',
            ]);

            // Prepare slides data
            $slidesData = [];
            foreach ($storySegments as $index => $segment) {
                if (isset($segment['Story_Text']) && isset($segment['localImagePath'])) {
                    $slidesData[] = [
                        'ai_story_id' => $aiGeneratedStory->id,
                        'page_no' => $index,
                        'image' => $segment['localImagePath'],
                        'text' => $segment['Story_Text'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            // Insert slides if we have any
            if (!empty($slidesData)) {
                $aiGeneratedStory->slides()->insert($slidesData);
            }

            return $aiGeneratedStory;
        });
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
