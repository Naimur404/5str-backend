<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\RecommendationService;
use App\Services\ContentBasedFilter;
use App\Services\CollaborativeFilter;
use App\Services\LocationBasedFilter;
use App\Services\AI\NeuralRecommendationEngine;

class RecommendationServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register the filter services as singletons
        $this->app->singleton(ContentBasedFilter::class);
        $this->app->singleton(CollaborativeFilter::class);
        $this->app->singleton(LocationBasedFilter::class);
        $this->app->singleton(NeuralRecommendationEngine::class);

        // Register the main recommendation service
        $this->app->singleton(RecommendationService::class, function ($app) {
            return new RecommendationService(
                $app->make(ContentBasedFilter::class),
                $app->make(CollaborativeFilter::class),
                $app->make(LocationBasedFilter::class),
                $app->make(NeuralRecommendationEngine::class)
            );
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
