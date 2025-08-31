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
                    // Dispatch individual interaction job for each item
                    ProcessUserInteractionJob::dispatch(
                        $this->userId,
                        $interaction['business_id'],
                        $interaction['action'],
                        $interaction['source'] ?? null,
                        $interaction['context'] ?? []
                    )->onQueue('high'); // Process individual items with high priority

                    $processedCount++;

                } catch (\Exception $e) {
                    $errors[] = "Interaction {$index}: " . $e->getMessage();
                    Log::warning('Failed to dispatch individual interaction job', [
                        'user_id' => $this->userId,
                        'interaction_index' => $index,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            Log::info('Batch interactions processing completed', [
                'user_id' => $this->userId,
                'processed_count' => $processedCount,
                'total_count' => count($this->interactions),
                'error_count' => count($errors)
            ]);

        } catch (\Exception $e) {
            Log::error('Batch interactions job failed', [
                'user_id' => $this->userId,
                'error' => $e->getMessage()
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
