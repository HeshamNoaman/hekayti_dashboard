<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

class AiStoryService
{
    protected $deepseekApiKey;
    protected $openaiApiKey;
    protected $deepseekBaseUrl;
    protected $openaiBaseUrl;
    protected $slideCount;
    protected $photoModel;
    protected $photoSize;
    protected $tinypngApiKey;

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
        $this->tinypngApiKey = Config::get('services.tinypng.api_key');

        // Initialize Tinify with API key
        \Tinify\setKey($this->tinypngApiKey);
    }

    /**
     * Generate a story using DeepSeek API
     */
    public function generateStory(string $heroName, string $paintingStyle, string $storyTopic)
    {
        try {
            // Read the prompt template
            $promptTemplate = $this->getPromptTemplate($this->slideCount);

            // Replace placeholders in the prompt
            $userPrompt = str_replace(
                ['هشام محمد', 'عالم البحار', 'رسوم مائية'],
                [$heroName, $storyTopic, $paintingStyle],
                $promptTemplate
            );

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
            $storyData = $this->parseJsonResponse($content);

            // Generate a name for the story
            $storyName = $this->generateStoryName($heroName, $storyTopic);

            // Generate a cover image for the story
            $coverImageData = $this->generateCoverImage($heroName, $paintingStyle, $storyTopic, $storyName);

            // Add cover image as the first slide (slide 0)
            array_unshift($storyData, $coverImageData);

            // Generate images for each story segment
            $storyData = $this->generateImagesForStory($storyData, $heroName, $paintingStyle, $storyTopic);

            return [
                'name' => $storyName,
                'segments' => $storyData,
                'slideCount' => $this->slideCount
            ];
        } catch (Exception $e) {
            Log::error('Error in generateStory: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Generate a name for the story
     */
    protected function generateStoryName(string $heroName, string $storyTopic)
    {
        try {
            // Prepare a prompt to generate a story name
            $prompt = "Create a short, engaging title in Arabic for a children's story about a character named {$heroName} in {$storyTopic}. The title should be no more than 5 words.";

            $response = Http::timeout(60)->withHeaders([
                'Authorization' => 'Bearer ' . $this->deepseekApiKey,
                'Content-Type' => 'application/json',
            ])->post($this->deepseekBaseUrl . '/v1/chat/completions', [
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a creative title generator for children\'s stories in Arabic.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'model' => 'deepseek-chat',
                'max_tokens' => 50,
            ]);

            if (!$response->successful()) {
                Log::error('Failed to generate story name: ' . $response->body());
                // Fallback to a default name
                return "مغامرة " . $heroName . " في " . $storyTopic;
            }

            $responseData = $response->json();
            $storyName = trim($responseData['choices'][0]['message']['content']);

            // Remove any quotes if present
            $storyName = str_replace('"', '', $storyName);
            $storyName = str_replace("'", '', $storyName);

            return $storyName;
        } catch (Exception $e) {
            Log::error('Error generating story name: ' . $e->getMessage());
            // Fallback to a default name
            return "مغامرة " . $heroName . " في " . $storyTopic;
        }
    }

    /**
     * Generate a cover image for the story
     */
    protected function generateCoverImage(string $heroName, string $paintingStyle, string $storyTopic, string $storyName)
    {
        $coverImageDescription = "An engaging book cover for a children's story featuring {$heroName} in {$storyTopic}. The illustration should be colorful and appealing to children.";

        $coverImagePrompt = "Create a {$paintingStyle} style illustration for a children's book cover with the following description:
        {$coverImageDescription}

        Make the image colorful, engaging, and appealing for 5-7 year old children. It should clearly show the main character in an interesting scene without any text overlay.";

        $imageUrl = $this->generateImage($coverImagePrompt);

        $coverData = [
            'نص_القصة' => $storyName,
            'وصف_الصورة' => $coverImageDescription,
        ];

        if ($imageUrl) {
            $coverData['imageUrl'] = $imageUrl;

            // Download and save the image locally
            $imagePath = $this->downloadImage($imageUrl, 0); // 0 for cover
            if ($imagePath) {
                $coverData['localImagePath'] = $imagePath;
            }
        }

        return $coverData;
    }

    /**
     * Generate images for each story segment using DALL-E
     */
    protected function generateImagesForStory(array $storyData, string $heroName, string $paintingStyle, string $storyTopic)
    {
        $totalSlides = count($storyData);
        foreach ($storyData as $i => &$segment) {
            // Create an enhanced image prompt that maintains character consistency
            $enhancedImagePrompt = "Create a {$paintingStyle} style illustration for a children's story with the following description:
            {$segment['وصف_الصورة']}

            The main character is named {$heroName} and the story takes place in {$storyTopic}.
            Make the image child-friendly, colorful, and engaging for 5-7 year old children.
            This is slide " . ($i + 1) . " of {$totalSlides} in the story, maintain visual consistency with the character's appearance.";

            $imageUrl = $this->generateImage($enhancedImagePrompt);

            if ($imageUrl) {
                // Add the image URL to the story data
                $segment['imageUrl'] = $imageUrl;

                // Download and save the image locally
                $imagePath = $this->downloadImage($imageUrl, $i + 1);
                if ($imagePath) {
                    $segment['localImagePath'] = $imagePath;
                }
            }
        }

        return $storyData;
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
            $fullPath = storage_path('app/public/' . $basePath . $path);

            try {
                // Download and compress the image directly using TinyPNG
                $source = \Tinify\fromUrl($url);
                $source->toFile($fullPath);

                // Return the relative path without 'storage/' prefix
                return $path;
            } catch (\Tinify\Exception $e) {
                // If TinyPNG compression fails, download the original image
                Log::error('TinyPNG compression failed: ' . $e->getMessage());

                $response = Http::get($url);
                if ($response->successful()) {
                    Storage::disk('public')->put($basePath . $path, $response->body());
                    return $path;
                }

                Log::error('Failed to download original image as fallback');
                return null;
            }
        } catch (Exception $e) {
            Log::error('Error in downloadImage: ' . $e->getMessage());
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
    protected function getPromptTemplate(int $slideCount)
    {
        return 'قم بإنشاء قصة أطفال ملهمة للأطفال الذين تتراوح أعمارهم بين خمس إلى سبع سنوات، عن بطل يسمى هشام محمد في عالم البحار. القصة يجب أن تتكون من ' . $slideCount . ' مقاطع، كل منها يحتوي على 8 إلى 12 كلمة مكتوبة باللغة العربية الفصحى. يجب دعم القصة برسوم مائية جميلة تمثل المشاهد بدقة، تُولد باستخدام الذكاء الاصطناعي. على أن تحتوي القصة على وصف دقيق للشخصيات والمشاهد للحفاظ على اتساق الرسوم في مختلف الشرائح (Slides) المقدمة.

# خطوات

1. القصة تتكون من ' . $slideCount . ' مقاطع، كل منها يحتوي على 8 إلى 12 كلمة باللغة العربية الفصحى.
2. تأكد من أن القصة تدور حول المغامرات والشخصيات البحرية مع البطل هشام محمد.
3. لكل مشهد من المشاهد، قم بتوفير وصف دقيق للشخصية والمشهد ليتم استخدامه في توليد الصور باستخدام الذكاء الاصطناعي.
4. تأكد من أن كل النصوص صحيحة نحويًا ودقيق لغويًا.

# صيغة الناتج

يجب أن تكون النتيجة على شكل مصفوفة JSON تحتوي على ' . $slideCount . ' عناصر. كل عنصر يجب أن يحتوي على الحقول التالية:
- **"نص_القصة"**: نص المقطع من القصة.
- **"وصف_الصورة"**: الوصف التفصيلي الذي سيتم إرساله للذكاء الاصطناعي لتوليد الصورة ويجب أن يكون دقيقًا ويصف الشخصية والمشهد بشكل مفصل.

# مثال

[
    {
        "نص_القصة": "هشام يغوص في البحر الأزرق العميق.",
        "وصف_الصورة": "فتى مغامر يرتدي بدلة غوص بجانب سمكة زاهية الألوان في المياه الزرقاء الصافية."
    },
    {
        "نص_القصة": "اكتشف هشام كهفًا مدهشًا تحت الماء.",
        "وصف_الصورة": "فتى يرتدي بدلة غوص ينظر بإعجاب إلى كهف تحت الماء مليء بالشعاب المرجانية."
    }
]

# ملاحظات

- تأكد من أن يكون الوصف دقيقًا ومناسبًا لتوليد صور واقعية.
- يجب أن يعبر الوصف بدقة عن الشخصيات والمكان واللحظة المحددة في القصة.
- تأكد من أن النصوص سهلة الفهم وتتوافق مع مستوى اللغة للأطفال المستهدفين.';
    }

    /**
     * Get the system prompt for the AI model
     */
    protected function getSystemPrompt()
    {
        return 'You are a creative children\'s story writer.
        Your task is to generate a story based on the provided prompt and output it in the exact JSON format specified.
        Each story must contain exactly ' . $this->slideCount . ' segments, with each segment having \'نص_القصة\' and \'وصف_الصورة\' fields.
        Ensure the response is a valid JSON array with exactly ' . $this->slideCount . ' elements.
        Do not include any text outside the JSON structure.';
    }
}
