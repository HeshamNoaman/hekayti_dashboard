<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AiStoryService;
use App\Models\AiGeneratedStory;
use App\Models\AiGeneratedStorySlide;
use Exception;
use Illuminate\Support\Facades\Log;

class TestAiStoryGeneration extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:test-ai-story-generation
                          {--hero_name=سارة أحمد : Name of the hero in the story}
                          {--painting_style=رسوم كرتونية : Style of the illustrations}
                          {--story_topic=عالم الفضاء : Topic of the story}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the AI story generation functionality';

    /**
     * Execute the console command.
     */
    public function handle(AiStoryService $aiStoryService)
    {
        $this->info('Starting AI story generation test...');

        try {
            $heroName = $this->option('hero_name');
            $paintingStyle = $this->option('painting_style');
            $storyTopic = $this->option('story_topic');

            $this->info("Generating story with the following parameters:");
            $this->info("Hero name: $heroName");
            $this->info("Painting style: $paintingStyle");
            $this->info("Story topic: $storyTopic");

            // Generate the story
            $this->info("Calling AI service to generate story...");
            $storyResult = $aiStoryService->generateStory($heroName, $paintingStyle, $storyTopic);

            $this->info("Story generated successfully!");

            // Extract data
            $storyName = $storyResult['name'] ?? "مغامرة " . $heroName . " في " . $storyTopic;
            $storySegments = $storyResult['segments'] ?? [];

            $this->info("Generated story name: $storyName");

            if (empty($storySegments)) {
                throw new Exception('Failed to generate story segments');
            }

            // Get cover photo from first segment
            $coverPhoto = null;
            if (isset($storySegments[0]['localImagePath'])) {
                $coverPhoto = $storySegments[0]['localImagePath'];
                $this->info("Using image as cover photo: $coverPhoto");
            } else if (isset($storySegments[0]['imageUrl'])) {
                $coverPhoto = $storySegments[0]['imageUrl'];
                $this->info("Using image URL as cover photo: $coverPhoto");
            } else {
                throw new Exception('Failed to generate cover image for the story');
            }

            // Save to database
            $this->info("Saving story to database...");
            $aiGeneratedStory = AiGeneratedStory::create([
                'name' => $storyName,
                'cover_photo' => $coverPhoto,
                'hero_name' => $heroName,
                'painting_style' => $paintingStyle,
                'story_topic' => $storyTopic,
                'story_data' => $storySegments,
                'status' => 'completed',
            ]);

            $this->info("Story saved to database with ID: " . $aiGeneratedStory->id);

            // Create slides for each segment
            $this->info("Creating story slides...");
            foreach ($storySegments as $index => $segment) {
                if (isset($segment['نص_القصة']) && (isset($segment['localImagePath']) || isset($segment['imageUrl']))) {
                    $slide = AiGeneratedStorySlide::create([
                        'ai_story_id' => $aiGeneratedStory->id,
                        'page_no' => $index,
                        'image' => $segment['localImagePath'] ?? ($segment['imageUrl'] ?? ''),
                        'text' => $segment['نص_القصة'],
                    ]);
                    $this->info("Created slide " . $index . " with ID: " . $slide->id);
                } else {
                    $this->warn("Skipping slide " . $index . " due to missing data");
                }
            }

            // Display summary
            $this->info("Story summary:");
            $this->line("Story ID: " . $aiGeneratedStory->id);
            $this->line("Story Name: " . $aiGeneratedStory->name);
            $this->line("Cover Photo: " . $aiGeneratedStory->cover_photo);

            $slides = AiGeneratedStorySlide::where('ai_story_id', $aiGeneratedStory->id)
                ->orderBy('page_no')
                ->get();

            $this->info("Story Slides:");
            foreach ($slides as $slide) {
                $this->line("Page " . $slide->page_no . ":");
                $this->line("  Text: " . $slide->text);
                $this->line("  Image: " . $slide->image);
                $this->newLine();
            }

            $this->info("Test completed successfully!");
        } catch (Exception $e) {
            $this->error("Error during test: " . $e->getMessage());
            Log::error("Error in TestAiStoryGeneration: " . $e->getMessage());
        }
    }
}
