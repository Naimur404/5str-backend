<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ABTestingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class PersonalizationTestController extends Controller
{
    /**
     * Test A/B testing functionality
     */
    public function testABTesting(Request $request): JsonResponse
    {
        $user = Auth::user();
        $abTestingService = app(ABTestingService::class);
        
        $personalizationLevel = $abTestingService->getVariantForUser('personalization_level', $user->id);
        
        return response()->json([
            'user_id' => $user->id,
            'personalization_level' => $personalizationLevel,
            'experiments' => ABTestingService::EXPERIMENTS,
            'timestamp' => now()
        ]);
    }

    /**
     * Get A/B testing metrics
     */
    public function getMetrics(Request $request): JsonResponse
    {
        $abTestingService = app(ABTestingService::class);
        $days = $request->input('days', 7);
        
        $metrics = $abTestingService->getExperimentMetrics('personalization_level', $days);
        
        return response()->json([
            'experiment' => 'personalization_level',
            'days' => $days,
            'metrics' => $metrics
        ]);
    }

    /**
     * Get detailed personalization performance metrics
     */
    public function getPersonalizationMetrics(Request $request): JsonResponse
    {
        $request->validate([
            'days' => 'nullable|integer|min:1|max:90',
            'user_id' => 'nullable|integer'
        ]);

        $days = $request->input('days', 7);
        $userId = $request->input('user_id');

        try {
            if ($userId) {
                // Get metrics for specific user
                $userMetrics = \App\Models\PersonalizationMetrics::getUserPerformance($userId, $days);
                $totalRecords = \App\Models\PersonalizationMetrics::where('user_id', $userId)
                    ->where('created_at', '>=', now()->subDays($days))
                    ->count();

                return response()->json([
                    'success' => true,
                    'data' => [
                        'user_id' => $userId,
                        'days' => $days,
                        'total_records' => $totalRecords,
                        'performance_by_level' => $userMetrics
                    ]
                ]);
            } else {
                // Get overall analytics
                $analytics = \App\Models\PersonalizationMetrics::getPerformanceAnalytics($days);
                $totalRecords = \App\Models\PersonalizationMetrics::where('created_at', '>=', now()->subDays($days))->count();
                $uniqueUsers = \App\Models\PersonalizationMetrics::where('created_at', '>=', now()->subDays($days))
                    ->distinct('user_id')
                    ->count();

                return response()->json([
                    'success' => true,
                    'data' => [
                        'days' => $days,
                        'total_records' => $totalRecords,
                        'unique_users' => $uniqueUsers,
                        'performance_by_level' => $analytics,
                        'summary' => [
                            'avg_response_time' => $analytics ? array_sum(array_column($analytics, 'avg_response_time')) / count($analytics) : 0,
                            'total_recommendations' => $analytics ? array_sum(array_column($analytics, 'total_recommendations')) : 0
                        ]
                    ]
                ]);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get personalization metrics',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}
