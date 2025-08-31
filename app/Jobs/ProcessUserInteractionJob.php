<?php

namespace App\Jobs;

use App\Models\UserInteraction;
use App\Models\User;
use App\Services\RecommendationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ProcessUserInteractionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $userId;
    protected $businessId;
    protected $action;
    protected $source;
    protected $context;
    protected $userLatitude;
    protected $userLongitude;

    public $timeout = 60; // 1 minute timeout
    public $tries = 3; // Retry 3 times on failure

    /**
     * Create a new job instance.
     */
    public function __construct($userId, $businessId, $action, $source = null, $context = [], $userLatitude = null, $userLongitude = null)
    {
        $this->userId = $userId;
        $this->businessId = $businessId;
        $this->action = $action;
        $this->source = $source;
        $this->context = $context;
        $this->userLatitude = $userLatitude;
        $this->userLongitude = $userLongitude;

        // Set queue priority based on action importance
        $highPriorityActions = ['phone_call', 'favorite', 'collection_add', 'offer_use'];
        $this->onQueue(in_array($action, $highPriorityActions) ? 'high' : 'default');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::info('Processing user interaction job', [
                'user_id' => $this->userId,
                'business_id' => $this->businessId,
                'action' => $this->action
            ]);

            // 1. Store the interaction with location data
            UserInteraction::track(
                $this->userId,
                $this->businessId,
                $this->action,
                $this->source,
                $this->context,
                null, // custom weight
                $this->userLatitude,
                $this->userLongitude
            );

            // 2. Update user profile cache asynchronously
            $this->updateUserProfileCache();

            // 3. Trigger recommendation updates for high-value actions
            $this->triggerRecommendationUpdates();

            // 4. Update A/B testing metrics
            $this->updateABTestingMetrics();

            Log::info('User interaction job completed successfully', [
                'user_id' => $this->userId,
                'action' => $this->action
            ]);

        } catch (\Exception $e) {
            Log::error('User interaction job failed', [
                'user_id' => $this->userId,
                'action' => $this->action,
                'error' => $e->getMessage()
            ]);
            throw $e; // Re-throw to trigger retry mechanism
        }
    }

    /**
     * Update user profile cache in background
     */
    private function updateUserProfileCache(): void
    {
        try {
            $recommendationService = app(RecommendationService::class);
            
            // Clear existing cache
            Cache::forget("user_profile_fast:{$this->userId}");
            Cache::forget("user_profile_full:{$this->userId}");
            
            // Pre-warm cache with fresh data (only for high-value actions)
            $highValueActions = ['favorite', 'phone_call', 'collection_add', 'offer_use'];
            if (in_array($this->action, $highValueActions)) {
                // Dispatch separate job for cache warming to avoid blocking
                UpdateUserRecommendationCacheJob::dispatch($this->userId)
                    ->onQueue('low')
                    ->delay(now()->addSeconds(5)); // Small delay to let DB commit
            }

        } catch (\Exception $e) {
            Log::warning('Failed to update user profile cache', [
                'user_id' => $this->userId,
                'error' => $e->getMessage()
            ]);
            // Don't throw - this is not critical
        }
    }

    /**
     * Trigger recommendation updates for important actions
     */
    private function triggerRecommendationUpdates(): void
    {
        $importantActions = ['favorite', 'phone_call', 'review', 'collection_add'];
        
        if (in_array($this->action, $importantActions)) {
            // Update business similarity scores
            CalculateBusinessSimilarityJob::dispatch($this->businessId)
                ->onQueue('low')
                ->delay(now()->addMinutes(1));
                
            // Update trending data
            UpdateTrendingDataJob::dispatch($this->businessId, $this->action)
                ->onQueue('low')
                ->delay(now()->addSeconds(30));
        }
    }

    /**
     * Update A/B testing metrics
     */
    private function updateABTestingMetrics(): void
    {
        try {
            $abTestingService = app(\App\Services\ABTestingService::class);
            $variant = $abTestingService->getVariantForUser('personalization_level', $this->userId);
            
            // Track conversion metrics
            $abTestingService->trackExperiment(
                'personalization_level',
                $this->userId,
                $variant,
                [
                    'event_type' => 'interaction',
                    'action' => $this->action,
                    'business_id' => $this->businessId,
                    'source' => $this->source
                ]
            );

        } catch (\Exception $e) {
            Log::warning('Failed to update A/B testing metrics', [
                'user_id' => $this->userId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('User interaction job permanently failed', [
            'user_id' => $this->userId,
            'business_id' => $this->businessId,
            'action' => $this->action,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts()
        ]);

        // Store minimal interaction data as fallback
        try {
            UserInteraction::create([
                'user_id' => $this->userId,
                'business_id' => $this->businessId,
                'interaction_type' => $this->action,
                'source' => $this->source ?? 'job_fallback',
                'context' => ['error' => 'job_failed', 'original_context' => $this->context]
            ]);
        } catch (\Exception $e) {
            Log::critical('Failed to store fallback interaction data', [
                'user_id' => $this->userId,
                'error' => $e->getMessage()
            ]);
        }
    }
}
