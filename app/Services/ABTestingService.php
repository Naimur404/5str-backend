<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ABTestingService
{
    const EXPERIMENTS = [
        'personalization_level' => [
            'variants' => ['none', 'light', 'full'],
            'traffic_split' => [50, 30, 20], // Percentage for each variant
            'active' => true
        ],
        'recommendation_count' => [
            'variants' => [15, 20, 25],
            'traffic_split' => [33, 34, 33],
            'active' => false
        ]
    ];

    /**
     * Get the variant for a user in a specific experiment
     */
    public function getVariantForUser(string $experimentName, int $userId): string
    {
        if (!isset(self::EXPERIMENTS[$experimentName]) || 
            !self::EXPERIMENTS[$experimentName]['active']) {
            return self::EXPERIMENTS[$experimentName]['variants'][0]; // Default variant
        }

        // Use consistent hashing to ensure user always gets same variant
        $userHash = crc32($userId . $experimentName) % 100;
        $experiment = self::EXPERIMENTS[$experimentName];
        
        $cumulative = 0;
        foreach ($experiment['traffic_split'] as $index => $percentage) {
            $cumulative += $percentage;
            if ($userHash < $cumulative) {
                return $experiment['variants'][$index];
            }
        }
        
        return $experiment['variants'][0]; // Fallback to first variant
    }

    /**
     * Track experiment results for analysis
     */
    public function trackExperiment(int $userId, string $experimentName, string $variant, array $metrics): void
    {
        try {
            DB::table('personalization_metrics')->insert([
                'user_id' => $userId,
                'personalization_level' => $variant,
                'response_time_ms' => $metrics['response_time_ms'] ?? 0,
                'recommendation_count' => $metrics['recommendation_count'] ?? 0,
                'metrics' => json_encode($metrics),
                'session_id' => request()->session()?->getId(),
                'created_at' => now()
            ]);
        } catch (\Exception $e) {
            // Log error but don't fail the main request
            Log::warning('Failed to track A/B test metrics', [
                'experiment' => $experimentName,
                'variant' => $variant,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get experiment performance metrics
     */
    public function getExperimentMetrics(string $experimentName, int $days = 7): array
    {
        $startDate = now()->subDays($days);
        
        $metrics = DB::table('personalization_metrics')
            ->where('created_at', '>=', $startDate)
            ->selectRaw('
                personalization_level as variant,
                COUNT(*) as total_requests,
                AVG(response_time_ms) as avg_response_time,
                AVG(recommendation_count) as avg_recommendations,
                COUNT(DISTINCT user_id) as unique_users
            ')
            ->groupBy('personalization_level')
            ->get();

        return $metrics->keyBy('variant')->toArray();
    }

    /**
     * Check if experiment is active
     */
    public function isExperimentActive(string $experimentName): bool
    {
        return isset(self::EXPERIMENTS[$experimentName]) && 
               self::EXPERIMENTS[$experimentName]['active'];
    }
}
