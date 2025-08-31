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
}
