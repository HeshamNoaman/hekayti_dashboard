<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\AiStoryService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register AiStoryService
        $this->app->singleton(AiStoryService::class, function ($app) {
            return new AiStoryService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
