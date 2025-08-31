<?php

namespace App\Jobs;

use App\Services\RecommendationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class UpdateUserRecommendationCacheJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $userId;
    
    public $timeout = 120; // 2 minutes timeout
    public $tries = 2;

    /**
     * Create a new job instance.
     */
    public function __construct($userId)
    {
        $this->userId = $userId;
        $this->onQueue('low'); // Low priority queue
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::info('Updating user recommendation cache', ['user_id' => $this->userId]);

            // Get the user model
            $user = \App\Models\User::find($this->userId);
            if (!$user) {
                Log::warning('User not found for cache update', ['user_id' => $this->userId]);
                return;
            }

            $recommendationService = app(RecommendationService::class);
            
            // Pre-generate and cache recommendations for different scenarios
            $this->cacheRecommendations($recommendationService, $user);
            
            Log::info('User recommendation cache updated successfully', [
                'user_id' => $this->userId,
                'user_email' => $user->email
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update user recommendation cache', [
                'user_id' => $this->userId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Pre-generate and cache common recommendation scenarios
     */
    private function cacheRecommendations(RecommendationService $service, \App\Models\User $user): void
    {
        try {
            // Cache different recommendation types with common parameters
            $scenarios = [
                ['limit' => 10, 'type' => 'general'],
                ['limit' => 5, 'type' => 'nearby'],
                ['limit' => 15, 'type' => 'extended']
            ];

            foreach ($scenarios as $scenario) {
                $cacheKey = "user_recommendations:{$this->userId}:{$scenario['type']}:{$scenario['limit']}";
                
                // Only cache if not already cached
                if (!Cache::has($cacheKey)) {
                    // Use the public getRecommendations method instead
                    $recommendations = $service->getRecommendations(
                        $user,
                        null, // latitude
                        null, // longitude 
                        null, // categories
                        $scenario['limit']
                    );
                    
                    // Cache for 10 minutes
                    Cache::put($cacheKey, $recommendations->toArray(), now()->addMinutes(10));
                }
            }

            // Also cache personalized recommendations
            $personalizedKey = "user_personalized:{$this->userId}:10";
            if (!Cache::has($personalizedKey)) {
                $personalized = $service->getPersonalizedBusinesses(
                    $user,
                    null, // latitude
                    null, // longitude
                    ['limit' => 10] // options array
                );
                Cache::put($personalizedKey, $personalized->toArray(), now()->addMinutes(10));
            }

        } catch (\Exception $e) {
            Log::warning('Failed to cache recommendation scenarios', [
                'user_id' => $this->userId,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('User recommendation cache job failed', [
            'user_id' => $this->userId,
            'error' => $exception->getMessage()
        ]);
    }
}
