<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Config;
use Exception;
use Tinify\Tinify;

class OptimizeImage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $imagePath;
    protected $imageUrl;

    /**
     * Create a new job instance.
     *
     * @param string $imagePath
     * @param string $imageUrl
     * @return void
     */
    public function __construct(string $imagePath, string $imageUrl)
    {
        $this->imagePath = $imagePath;
        $this->imageUrl = $imageUrl;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {

            // Get API key from config instead of directly from env
            $apiKey = Config::get('services.tinypng.api_key');
            if (empty($apiKey)) {
                throw new Exception('TinyPNG API key is not configured');
            }
            Log::info("Using TinyPNG API Key in job: {$apiKey}");

            Tinify::setKey($apiKey);

            // Optimize the image directly from the URL using TinyPNG
            $source = \Tinify\fromUrl($this->imageUrl);

            // Get the optimized image content
            $optimizedContent = $source->toBuffer();

            // Replace the original file with the optimized one
            Storage::disk('public')->put($this->imagePath, $optimizedContent);

            Log::info("Successfully optimized image: {$this->imagePath}");
        } catch (Exception $e) {
            Log::error("Failed to optimize image {$this->imagePath}: " . $e->getMessage());
        }
    }
}
