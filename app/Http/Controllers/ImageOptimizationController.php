<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Jobs\OptimizeImage;
use Illuminate\Support\Facades\Storage;
class ImageOptimizationController extends Controller
{
    public function optimizeImage(Request $request)
    {
        // Validate the request
        $request->validate([
            'image_url' => 'required|url',
        ]);

        // Get the image URL from the request
        $imageUrl = $request->input('image_url');

        // Generate a unique path for the optimized image
        $imagePath = 'ai_stories/test/';
        $imageName = "slide_1.png";

        // create the directory if it doesn't exist
        $directory = dirname($imagePath);
        if (!Storage::disk('public')->exists($directory)) {
            Storage::disk('public')->makeDirectory($directory);
        }

        // Dispatch the job
        OptimizeImage::dispatch($imagePath . $imageName, $imageUrl);

        return response()->json([
            'message' => 'Image optimization job dispatched successfully!',
            'image_path' => $imagePath,
        ]);
    }
}
