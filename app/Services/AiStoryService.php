<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use GuzzleHttp\Client;
use App\Jobs\OptimizeImage;

class AiStoryService
{
    protected $deepseekApiKey;
    protected $openaiApiKey;
    protected $deepseekBaseUrl;
    protected $openaiBaseUrl;
    protected $slideCount;
    protected $photoModel;
    protected $photoSize;

    public function __construct()
    {
        $this->deepseekApiKey = Config::get('services.deepseek.api_key');
        $this->openaiApiKey = Config::get('services.openai.api_key');
        $this->deepseekBaseUrl = Config::get('services.deepseek.base_url', 'https://api.deepseek.com');
        $this->openaiBaseUrl = Config::get('services.openai.base_url', 'https://api.openai.com');
        // get prompt config
        $this->slideCount = Config::get('services.prompt.slide_count', 8);
        $this->photoModel = Config::get('services.prompt.photo_model', 'dall-e-3');
        $this->photoSize = Config::get('services.prompt.photo_size', '1024x1024');
    }

    /**
     * Generate a story using DeepSeek API
     */
    public function generateStory(string $heroName, string $paintingStyle, string $storyTopic)
    {
        try {
            // Read the prompt template
            $userPrompt = $this->getPromptTemplate($heroName, $storyTopic, $paintingStyle, $this->slideCount);
            $systemPrompt = $this->getSystemPrompt();

            // Make the API call to DeepSeek
            $response = Http::timeout(120)->withHeaders([
                'Authorization' => 'Bearer ' . $this->deepseekApiKey,
                'Content-Type' => 'application/json',
            ])->post($this->deepseekBaseUrl . '/v1/chat/completions', [
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'model' => 'deepseek-chat',
            ]);

            if (!$response->successful()) {
                Log::error('DeepSeek API error: ' . $response->body());
                throw new Exception('Failed to generate story with DeepSeek API: ' . $response->body());
            }

            $responseData = $response->json();
            $content = $responseData['choices'][0]['message']['content'];

            // Parse the JSON response
            $parsedStoryData = $this->parseJsonResponse($content);

            // save the response as json file for debugging
            Storage::disk('public')->put('ai_stories/storyData.json', json_encode($parsedStoryData));

            // check if the story data is valid
            if (empty($parsedStoryData) || !is_array($parsedStoryData) || count($parsedStoryData) < $this->slideCount) {
                throw new Exception('Invalid story data');
            }

            // Generate images for each story segment
            $storyDataWithImages = $this->generateImagesForStory($parsedStoryData, $paintingStyle);

            // save the response as json file for debugging
            Storage::disk('public')->put('ai_stories/storiesWithImages.json', json_encode($storyDataWithImages));

            return [
                'segments' => $storyDataWithImages,
                'slideCount' => $this->slideCount
            ];
        } catch (Exception $e) {
            Log::error('Error in generateStory: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Generate images in parallel using async requests
     */
    protected function generateImagesForStory(array $storyData, string $paintingStyle)
    {
        $client = new Client([
            'timeout' => 120,
            'connect_timeout' => 10,
            'http_errors' => false,
        ]);
        $promises = [];
        $requestCount = count($storyData);

        Log::info("Starting parallel image generation for {$requestCount} story segments");

        // Configure rate limiting - max 4 concurrent requests (below OpenAI's 5/min limit)
        $concurrencyLimit = min(4, $requestCount);
        $pool = new \GuzzleHttp\Pool(
            $client,
            (function () use ($storyData, $paintingStyle, $client) {
                foreach ($storyData as $i => $segment) {
                    $enhancedImagePrompt = $this->buildImagePrompt($segment, $paintingStyle);

                    yield $i => $this->createImageGenerationRequest($client, $enhancedImagePrompt, $i);

                    // Add a delay between requests to respect rate limits
                    if ($i < count($storyData) - 1) {
                        usleep(300000); // 300ms delay between requests
                    }
                }
            })(),
            [
                'concurrency' => $concurrencyLimit,
                'fulfilled' => function ($response, $index) use (&$storyData) {
                    $this->handleSuccessfulImageResponse($response, $index, $storyData);
                },
                'rejected' => function ($reason, $index) {
                    $this->handleFailedImageResponse($reason, $index);
                },
                'options' => [
                    'http_errors' => false
                ]
            ]
        );

        // Execute the pool of requests
        $pool->promise()->wait();

        // Verify all segments have images and handle any missing ones
        $storyData = $this->verifyAndRepairStoryImages($storyData, $paintingStyle);

        Log::info("Completed image generation for story segments");
        return $storyData;
    }

    /**
     * Create an image generation request
     */
    protected function createImageGenerationRequest($client, $prompt, $index)
    {
        Log::debug("Creating image generation request for segment {$index}");

        // Create a proper request object
        $request = new \GuzzleHttp\Psr7\Request(
            'POST',
            $this->openaiBaseUrl . '/v1/images/generations',
            [
                'Authorization' => 'Bearer ' . $this->openaiApiKey,
                'Content-Type' => 'application/json',
            ],
            json_encode([
                'model' => $this->photoModel,
                'prompt' => $prompt,
                'n' => 1,
                'size' => $this->photoSize,
                'quality' => 'standard',
            ])
        );

        // Return the request
        return $request;
    }

    /**
     * Handle successful image generation response
     */
    protected function handleSuccessfulImageResponse($response, $index, &$storyData)
    {
        try {
            $statusCode = $response->getStatusCode();

            if ($statusCode !== 200) {
                Log::error("Image API returned non-200 status code for segment {$index}: {$statusCode}");
                $storyData[$index]['imageError'] = "API returned status code {$statusCode}";
                return;
            }

            $responseData = json_decode($response->getBody(), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error("Failed to parse JSON response for segment {$index}: " . json_last_error_msg());
                $storyData[$index]['imageError'] = "Invalid JSON response";
                return;
            }

            $imageUrl = $responseData['data'][0]['url'] ?? null;

            if (!$imageUrl) {
                Log::error("No image URL in response for segment {$index}");
                $storyData[$index]['imageError'] = "No image URL in response";
                return;
            }

            $localImagePath = $this->downloadImage($imageUrl, $index + 1);

            if (!$localImagePath) {
                Log::error("Failed to download image for segment {$index} from URL: {$imageUrl}");
                $storyData[$index]['imageError'] = "Failed to download image";
                $storyData[$index]['imageUrl'] = $imageUrl; // Still store the URL for potential retry
                return;
            }

            // Success case
            Log::info("Successfully generated and downloaded image for segment {$index}");
            $storyData[$index]['imageUrl'] = $imageUrl;
            $storyData[$index]['localImagePath'] = $localImagePath;
        } catch (Exception $e) {
            Log::error("Exception processing image response for segment {$index}: " . $e->getMessage());
            $storyData[$index]['imageError'] = "Exception: " . $e->getMessage();
        }
    }

    /**
     * Handle failed image generation request
     */
    protected function handleFailedImageResponse($reason, $index)
    {
        $errorMessage = $reason instanceof Exception ? $reason->getMessage() : 'Unknown error';
        Log::error("Image generation request failed for segment {$index}: {$errorMessage}");
    }

    /**
     * Verify all story segments have images and attempt to repair if needed
     */
    protected function verifyAndRepairStoryImages(array $storyData, string $paintingStyle = 'colorful')
    {
        $retryCount = 0;
        $maxRetries = 2;

        while ($retryCount < $maxRetries) {
            $needsRetry = false;

            // Identify segments that need retry
            $retrySegments = collect($storyData)->filter(function ($segment, $i) {
                return !isset($segment['localImagePath']) || !$segment['localImagePath'];
            })->keys()->all();

            if (empty($retrySegments)) {
                break;
            }

            // if (!$needsRetry) {
            //     break;
            // }

            Log::warning("Retrying image generation for " . count($retrySegments) . " segments (attempt " . ($retryCount + 1) . ")");

            // Process retries with rate limiting
            foreach ($retrySegments as $index) {
                try {
                    // If we have a URL but failed to download, try downloading again
                    if (isset($storyData[$index]['imageUrl']) && $storyData[$index]['imageUrl']) {
                        $localImagePath = $this->downloadImage($storyData[$index]['imageUrl'], $index + 1);

                        if ($localImagePath) {
                            $storyData[$index]['localImagePath'] = $localImagePath;
                            unset($storyData[$index]['imageError']);
                            Log::info("Successfully re-downloaded image for segment {$index}");
                            continue;
                        }
                    }

                    // Otherwise, try generating a new image
                    $enhancedImagePrompt = $this->buildImagePrompt($storyData[$index], $paintingStyle);
                    $imageUrl = $this->generateImage($enhancedImagePrompt);

                    if ($imageUrl) {
                        $localImagePath = $this->downloadImage($imageUrl, $index + 1);

                        if ($localImagePath) {
                            $storyData[$index]['imageUrl'] = $imageUrl;
                            $storyData[$index]['localImagePath'] = $localImagePath;
                            unset($storyData[$index]['imageError']);
                            Log::info("Successfully regenerated image for segment {$index}");
                        }
                    }

                    // Add delay between retries to respect rate limits
                    sleep(1); // 1 second delay between retry requests

                } catch (Exception $e) {
                    Log::error("Error during retry for segment {$index}: " . $e->getMessage());
                }
            }

            $retryCount++;

            // Add a longer delay between retry batches
            if ($retryCount < $maxRetries && count($retrySegments) > 0) {
                sleep(5); // 5 second delay between retry batches
            }
        }

        // For any remaining segments without images, use a placeholder
        foreach ($storyData as $i => $segment) {
            if (!isset($segment['localImagePath']) || !$segment['localImagePath']) {
                Log::warning("Using placeholder image for segment {$i} after all retries failed");
                $storyData[$i]['localImagePath'] = 'ai_stories/placeholder.jpg';
                $storyData[$i]['isPlaceholder'] = true;
            }
        }

        return $storyData;
    }

    /**
     * Enhanced image prompt builder
     */
    protected function buildImagePrompt($segment, $paintingStyle)
    {
        return "Create a {$paintingStyle} style illustration for a children's story. "
            . "{$segment['photo_prompt']}."
            . "Child-friendly, colorful, and engaging for ages 5-7.";
    }

    /**
     * Generate an image using DALL-E API
     */
    protected function generateImage(string $imagePrompt)
    {
        try {
            $response = Http::timeout(60)->withHeaders([
                'Authorization' => 'Bearer ' . $this->openaiApiKey,
                'Content-Type' => 'application/json',
            ])->post($this->openaiBaseUrl . '/v1/images/generations', [
                'model' => $this->photoModel,
                'prompt' => $imagePrompt,
                'n' => 1,
                'size' => $this->photoSize,
                'quality' => 'standard',
            ]);

            if (!$response->successful()) {
                Log::error('DALL-E API error: ' . $response->body());
                return null;
            }

            $responseData = $response->json();
            return $responseData['data'][0]['url'] ?? null;
        } catch (Exception $e) {
            Log::error('Error in generateImage: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Download image from URL and save it locally
     */
    protected function downloadImage(string $url, int $slideNumber)
    {
        try {
            // Log the URL we're trying to download
            Log::info("Downloading image for slide $slideNumber from URL: $url");

            // define base path
            $basePath = 'ai_stories/';

            // Generate a folder name once and store it statically
            static $folderName = null;
            if ($folderName === null) {
                $folderName = date('Y-m-d_H-i-s') . '_' . Str::random(5);
                Storage::disk('public')->makeDirectory($basePath . $folderName);
            }

            $fileName = 'slide_' . $slideNumber . '.png';
            $path = $folderName . '/' . $fileName;
            $fullPath = $basePath . $path;

            // download the image from the url
            $response = Http::get($url);

            if (!$response->successful()) {
                Log::error('Failed to download image: ' . $response->status() . ' - ' . $response->body());
                return null;
            }

            // save the image to the local path
            $result = Storage::disk('public')->put($fullPath, $response->body());

            if (!$result) {
                Log::error("Failed to save image to storage: $fullPath");
                return null;
            }

            Log::info("Successfully saved image for slide $slideNumber to $path");

            // Dispatch a job to optimize the image in the background
            OptimizeImage::dispatch($fullPath, $url);

            // Return the relative path without 'storage/' prefix
            return $path;
        } catch (Exception $e) {
            Log::error('Error in downloadImage: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return null;
        }
    }

    /**
     * Parse JSON response from DeepSeek API
     */
    protected function parseJsonResponse(string $response)
    {
        try {
            // Extract JSON from the response (in case there's any extra text)
            $jsonMatch = [];
            preg_match('/\[[\s\S]*\]/', $response, $jsonMatch);

            if (!empty($jsonMatch)) {
                return json_decode($jsonMatch[0], true);
            }

            // Try parsing the entire response as JSON
            return json_decode($response, true);
        } catch (Exception $e) {
            Log::error('Error parsing JSON response: ' . $e->getMessage());
            Log::error('Raw response: ' . $response);
            throw new Exception('Failed to parse AI response');
        }
    }

    /**
     * Get the prompt template for story generation
     */
    //  that includes the story title in Arabic
    protected function getPromptTemplate(string $heroName, string $storyTopic, string $paintingStyle, int $slideCount)
    {
        return '
Create an inspiring children\'s story for kids aged five to seven, about a hero named ' . $heroName . ' in the topic of ' . $storyTopic . '.
The story should consist of ' . ($slideCount + 1) . ' slides in total, with the first slide being the cover photo.
The remaining ' . $slideCount . ' slides should contain 8 to 12 words written in standard Arabic.
The story should be supported by beautiful illustrations in the art style of ' . $paintingStyle . ', accurately representing the scenes and generated using AI.
The story must contain a detailed description of the characters and scenes to ensure consistency in the illustrations across different slides.

# Steps

1. The total number of slides will be ' . ($slideCount + 1) . ', including the cover photo as the first slide.
2. The first slide should be the cover photo with a "Story_Text" field containing only the story title in Arabic. The cover image should not contain any text or words within the image itself.
3. The remaining ' . $slideCount . ' slides consist of segments, each containing 8 to 12 words in standard Arabic.
4. Ensure the story revolves around ' . $storyTopic . ' with the hero ' . $heroName . '.
5. Make sure to incorporate the **art style** of ' . $paintingStyle . ' in all the illustrations.
6. For each scene, provide a detailed description of the character\'s appearance, clothing, and the scene itself to be used for generating the image using AI.
7. Ensure all text is grammatically correct and linguistically accurate.
8. Create a short and catchy title for the story in Arabic (no more than 5 words).

# Output Format

The result should be a JSON array containing ' . ($slideCount + 1) . ' elements. Each element must contain the following fields:
- **"Story_Text"**: The text of the segment from the story, written in Arabic (8-12 words).
- **"photo_prompt"**: The detailed description to be sent to AI for generating the image. It must include:
  - The character\'s appearance (e.g., hair color, eye color, distinctive features)
  - The character\'s clothing (e.g., type, color, any special details)
  - A detailed description of the scene (e.g., location, time of day, atmosphere) for accurate image generation.

# Example

[
    {
        "Story_Text": "مغامرات هشام",
        "photo_prompt": "Cover photo: A colorful illustration showing Hisham in a diving suit about to embark on an adventure. The background shows an underwater scene. Do not include any text in the image."
    },
    {
        "Story_Text": "هشام يغوص في البحر الأزرق العميق.",
        "photo_prompt": "An adventurous boy with brown hair and blue eyes wearing a blue diving suit, swimming beside a brightly colored fish in clear blue waters."
    },
    {
        "Story_Text": "اكتشف هشام كهفًا مدهشًا تحت الماء.",
        "photo_prompt": "A boy with brown hair and blue eyes wearing a blue diving suit, looking in awe at an underwater cave filled with coral reefs."
    }
]

# Notes

- Ensure that the descriptions are accurate and suitable for generating realistic images.
- The descriptions should clearly represent the characters, their appearance, clothing, the setting, and the specific moment in the story.
- Ensure the text is easy to understand and appropriate for the language level of the target age group.
';
    }

    /**
     * Get the system prompt for the AI model
     */
    protected function getSystemPrompt()
    {
        //  that includes the story title in Arabic
        return 'You are a creative children\'s story writer.
Your task is to generate a story based on the provided prompt and output it in the exact JSON format specified.
Each story must contain exactly ' . ($this->slideCount + 1) . ' slides, with the first slide being the cover photo.
The first slide should have:
- "Story_Text": The title of the story in Arabic only.
- "photo_prompt": A detailed description for the cover image that represents the story theme. The cover image should be visually appealing but must not contain any text or words within the image itself.
The remaining ' . $this->slideCount . ' slides should have each segment containing the following fields:
- "Story_Text": The text of the segment from the story, written in Arabic.
- "photo_prompt": The detailed description to be sent to AI for generating the image. It must include the character\'s appearance, clothing, and a detailed description of the scene.

Ensure the response is a valid JSON array with exactly ' . ($this->slideCount + 1) . ' elements, where the first element is the cover photo and the story title.
Do not include any text outside the JSON structure.';
    }
}
