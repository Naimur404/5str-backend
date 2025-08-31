<?php

namespace App\Jobs;

use App\Models\UserInteraction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessBatchInteractionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $userId;
    protected $interactions;

    public $timeout = 300; // 5 minutes for batch processing
    public $tries = 2;

    public function __construct($userId, array $interactions)
    {
        $this->userId = $userId;
        $this->interactions = $interactions;
        $this->onQueue('default'); // Regular priority for batches
    }

    public function handle(): void
    {
        try {
            Log::info('Processing batch interactions', [
                'user_id' => $this->userId,
                'interaction_count' => count($this->interactions)
            ]);

            $processedCount = 0;
            $errors = [];

            foreach ($this->interactions as $index => $interaction) {
                try {
                    // Extract location data from interaction
                    $userLatitude = $interaction['user_latitude'] ?? null;
                    $userLongitude = $interaction['user_longitude'] ?? null;
                    
                    // Directly save the interaction with location data
                    UserInteraction::track(
                        $this->userId,
                        $interaction['business_id'],
                        $interaction['action'],
                        $interaction['source'] ?? null,
                        $interaction['context'] ?? [],
                        null, // custom weight
                        $userLatitude,
                        $userLongitude
                    );

                    $processedCount++;

                    Log::debug('Batch interaction saved', [
                        'user_id' => $this->userId,
                        'business_id' => $interaction['business_id'],
                        'action' => $interaction['action'],
                        'index' => $index,
                        'has_location' => ($userLatitude && $userLongitude) ? 'yes' : 'no'
                    ]);

                } catch (\Exception $e) {
                    $errors[] = "Interaction {$index}: " . $e->getMessage();
                    Log::warning('Failed to save batch interaction', [
                        'user_id' => $this->userId,
                        'interaction_index' => $index,
                        'business_id' => $interaction['business_id'] ?? 'unknown',
                        'action' => $interaction['action'] ?? 'unknown',
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // Clear user profile cache after batch processing
            \Illuminate\Support\Facades\Cache::forget("user_profile_fast:{$this->userId}");
            \Illuminate\Support\Facades\Cache::forget("user_profile_full:{$this->userId}");

            Log::info('Batch interactions processing completed', [
                'user_id' => $this->userId,
                'processed_count' => $processedCount,
                'total_count' => count($this->interactions),
                'error_count' => count($errors),
                'errors' => $errors
            ]);

            // For high-value batch processing, trigger recommendation updates
            if ($processedCount > 0) {
                UpdateUserRecommendationCacheJob::dispatch($this->userId)
                    ->onQueue('low')
                    ->delay(now()->addSeconds(10));
            }

        } catch (\Exception $e) {
            Log::error('Batch interactions job failed', [
                'user_id' => $this->userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Batch interactions job permanently failed', [
            'user_id' => $this->userId,
            'interaction_count' => count($this->interactions),
            'error' => $exception->getMessage()
        ]);

        // Try to save interactions directly as fallback
        foreach ($this->interactions as $interaction) {
            try {
                UserInteraction::create([
                    'user_id' => $this->userId,
                    'business_id' => $interaction['business_id'],
                    'interaction_type' => $interaction['action'],
                    'source' => $interaction['source'] ?? 'batch_fallback',
                    'context' => array_merge(
                        $interaction['context'] ?? [], 
                        ['batch_job_failed' => true]
                    )
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to save fallback batch interaction', [
                    'user_id' => $this->userId,
                    'business_id' => $interaction['business_id'],
                    'error' => $e->getMessage()
                ]);
            }
        }
    }
}
