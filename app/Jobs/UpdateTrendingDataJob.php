<?php

namespace App\Jobs;

use App\Models\TrendingData;
use App\Models\Business;
use App\Models\UserInteraction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UpdateTrendingDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $businessId;
    protected $action;
    
    public $timeout = 180; // 3 minutes timeout
    public $tries = 2;

    public function __construct($businessId, $action)
    {
        $this->businessId = $businessId;
        $this->action = $action;
        $this->onQueue('low');
    }

    public function handle(): void
    {
        try {
            Log::info('Updating trending data', [
                'business_id' => $this->businessId,
                'action' => $this->action
            ]);

            $business = Business::find($this->businessId);
            if (!$business) {
                Log::warning('Business not found for trending update', ['business_id' => $this->businessId]);
                return;
            }

            // Calculate trending scores
            $trendingScores = $this->calculateTrendingScores($business);
            
            // Update or create trending data
            $this->updateTrendingData($business, $trendingScores);
            
            Log::info('Trending data updated successfully', [
                'business_id' => $this->businessId,
                'trending_score' => $trendingScores['trending_score']
            ]);

        } catch (\Exception $e) {
            Log::error('Trending data update failed', [
                'business_id' => $this->businessId,
                'action' => $this->action,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    private function calculateTrendingScores(Business $business): array
    {
        $now = now();
        $yesterday = $now->copy()->subDay();
        $weekAgo = $now->copy()->subWeek();

        // Get interaction counts for different time periods
        $todayInteractions = $this->getInteractionCount($business->id, $now->startOfDay(), $now);
        $yesterdayInteractions = $this->getInteractionCount($business->id, $yesterday->startOfDay(), $yesterday->endOfDay());
        $weekInteractions = $this->getInteractionCount($business->id, $weekAgo, $now);

        // Calculate weighted scores for different actions
        $actionWeights = [
            'view' => 1.0,
            'click' => 1.5,
            'favorite' => 3.0,
            'phone_call' => 5.0,
            'direction_request' => 2.5,
            'share' => 3.5,
            'review' => 4.0,
            'collection_add' => 4.5
        ];

        $weightedScore = 0;
        foreach ($actionWeights as $action => $weight) {
            $actionCount = UserInteraction::where('business_id', $business->id)
                ->where('interaction_type', $action)
                ->where('created_at', '>=', $weekAgo)
                ->count();
            $weightedScore += $actionCount * $weight;
        }

        // Calculate growth rate
        $growthRate = $yesterdayInteractions > 0 
            ? ($todayInteractions - $yesterdayInteractions) / $yesterdayInteractions 
            : ($todayInteractions > 0 ? 1.0 : 0.0);

        // Calculate velocity (acceleration of interactions)
        $velocity = $this->calculateVelocity($business->id);

        // Combine scores with time decay
        $timeDecayFactor = 0.9; // Slightly favor recent activity
        $baseScore = $weightedScore * $timeDecayFactor;
        $trendingScore = $baseScore + ($growthRate * 10) + ($velocity * 5);

        return [
            'trending_score' => round($trendingScore, 2),
            'weighted_score' => round($weightedScore, 2),
            'growth_rate' => round($growthRate, 4),
            'velocity' => round($velocity, 4),
            'total_interactions' => $weekInteractions,
            'today_interactions' => $todayInteractions
        ];
    }

    private function getInteractionCount($businessId, $startDate, $endDate): int
    {
        return UserInteraction::where('business_id', $businessId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();
    }

    private function calculateVelocity($businessId): float
    {
        // Get hourly interaction counts for the last 24 hours
        $hourlyData = UserInteraction::where('business_id', $businessId)
            ->where('created_at', '>=', now()->subHours(24))
            ->select(DB::raw('HOUR(created_at) as hour, COUNT(*) as count'))
            ->groupBy('hour')
            ->get()
            ->pluck('count', 'hour')
            ->toArray();

        if (count($hourlyData) < 3) {
            return 0.0; // Need at least 3 data points for velocity
        }

        // Calculate simple velocity as change in interaction rate
        $times = array_keys($hourlyData);
        $counts = array_values($hourlyData);
        
        // Linear regression to find trend
        $n = count($times);
        $sumX = array_sum($times);
        $sumY = array_sum($counts);
        $sumXY = 0;
        $sumX2 = 0;
        
        for ($i = 0; $i < $n; $i++) {
            $sumXY += $times[$i] * $counts[$i];
            $sumX2 += $times[$i] * $times[$i];
        }
        
        $slope = ($n * $sumXY - $sumX * $sumY) / ($n * $sumX2 - $sumX * $sumX);
        
        return $slope; // Positive slope = accelerating, negative = decelerating
    }

    private function updateTrendingData(Business $business, array $scores): void
    {
        TrendingData::updateOrCreate(
            [
                'item_type' => 'business',
                'item_id' => $business->id
            ],
            [
                'trending_score' => $scores['trending_score'],
                'weighted_score' => $scores['weighted_score'],
                'growth_rate' => $scores['growth_rate'],
                'velocity' => $scores['velocity'],
                'total_interactions' => $scores['total_interactions'],
                'period_start' => now()->subWeek(),
                'period_end' => now(),
                'metadata' => [
                    'calculation_method' => 'interaction_based',
                    'last_action' => $this->action,
                    'today_interactions' => $scores['today_interactions'],
                    'updated_by_job' => true
                ]
            ]
        );
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Trending data job failed', [
            'business_id' => $this->businessId,
            'action' => $this->action,
            'error' => $exception->getMessage()
        ]);
    }
}
