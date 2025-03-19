<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AiGeneratedStory;
use App\Http\Controllers\Api\AiStoryController as AiStoryApiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AiStoryController extends Controller
{
    protected $apiController;

    public function __construct(AiStoryApiController $apiController)
    {
        $this->apiController = $apiController;
    }

    /**
     * Display a listing of all AI generated stories.
     */
    public function index()
    {
        $stories = AiGeneratedStory::orderBy('created_at', 'desc')->get();
        return view('ai_stories.index', compact('stories'));
    }

    /**
     * Display the details of a specific AI generated story.
     */
    public function show($id)
    {
        $story = AiGeneratedStory::with(['slides' => function ($query) {
            $query->orderBy('page_no', 'asc');
        }])->findOrFail($id);

        return view('ai_stories.show', compact('story'));
    }

    /**
     * Show the form for creating a new AI story
     */
    public function create()
    {
        return view('ai_stories.create');
    }

    /**
     * Store a newly generated AI story
     */
    public function store(Request $request)
    {
        // Add the redirect parameter to use web redirects
        $request->merge(['redirect' => 'web']);

        // Use the API controller to handle the story generation
        return $this->apiController->generateStory($request);
    }

    /**
     * Delete an AI generated story
     */
    public function destroy(Request $request)
    {
        // Retrieve the story by ID
        $aiStory = AiGeneratedStory::findOrFail($request->story_id);

        // Get all slides
        $slides = $aiStory->slides();

        // Delete all slides related to the story
        $slides->each(function ($slide) {
            $slidePhotoPath = 'ai_stories/' . $slide->image;

            // Delete the slide photo file if it exists
            if (Storage::disk('public')->exists($slidePhotoPath)) {
                Storage::disk('public')->delete($slidePhotoPath);
            }

            $slide->delete();
        });

        // Delete the story cover photo
        $coverPhotoPath = 'ai_stories/' . $aiStory->cover_photo;
        if (Storage::disk('public')->exists($coverPhotoPath)) {
            Storage::disk('public')->delete($coverPhotoPath);
        }

        // Get the correct folder path including the 'ai_stories/' prefix
        $storyFolderPath = 'ai_stories/' . dirname($aiStory->cover_photo);

        // Delete the folder that contains the story
        if (Storage::disk('public')->exists($storyFolderPath)) {
            Storage::disk('public')->deleteDirectory($storyFolderPath);
        }

        // Delete the story
        $aiStory->delete();

        return redirect()
            ->route('ai-stories.index')
            ->with('success', 'تم حذف القصة بنجاح');
    }
}
