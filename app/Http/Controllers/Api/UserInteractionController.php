<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserInteraction;
use App\Models\Business;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class UserInteractionController extends Controller
{
    /**
     * Track user interaction with a business
     */
    public function track(Request $request): JsonResponse
    {
        $request->validate([
            'business_id' => 'required|exists:businesses,id',
            'action' => 'required|in:view,click,save,share,visit,call,direction,search_click,phone_call,favorite,unfavorite,review,collection_add,collection_remove,offer_view,offer_use,direction_request,website_click',
            'source' => 'nullable|string|max:100',
            'context' => 'nullable|array',
            'user_latitude' => 'nullable|numeric|between:-90,90',
            'user_longitude' => 'nullable|numeric|between:-180,180'
        ]);

        $user = Auth::user();
        $businessId = $request->input('business_id');
        $action = $request->input('action');
        $source = $request->input('source');
        $context = $request->input('context', []);
        $userLatitude = $request->input('user_latitude');
        $userLongitude = $request->input('user_longitude');

        // Verify business exists
        $business = Business::find($businessId);
        if (!$business) {
            return response()->json([
                'success' => false,
                'message' => 'Business not found'
            ], 404);
        }

        try {
            // Use job queue for async processing instead of closure
            \App\Jobs\ProcessUserInteractionJob::dispatch(
                $user->id,
                $businessId,
                $action,
                $source,
                $context,
                $userLatitude,
                $userLongitude
            );

            return response()->json([
                'success' => true,
                'message' => 'Interaction tracked successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to track interaction',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Track multiple user interactions at once (batch processing)
     */
    public function batch(Request $request): JsonResponse
    {
        $request->validate([
            'interactions' => 'required|array|min:1|max:50',
            'interactions.*.business_id' => 'required|exists:businesses,id',
            'interactions.*.action' => 'required|in:view,click,save,share,visit,call,direction,search_click,phone_call,favorite,unfavorite,review,collection_add,collection_remove,offer_view,offer_use,direction_request,website_click',
            'interactions.*.source' => 'nullable|string|max:100',
            'interactions.*.context' => 'nullable|array',
            'interactions.*.timestamp' => 'nullable|integer',
            'interactions.*.user_latitude' => 'nullable|numeric|between:-90,90',
            'interactions.*.user_longitude' => 'nullable|numeric|between:-180,180'
        ]);

        $user = Auth::user();
        $interactions = $request->input('interactions');
        $processedCount = 0;
        $errors = [];

        try {
            // Use job queue for batch processing
            \App\Jobs\ProcessBatchInteractionsJob::dispatch($user->id, $interactions);

            return response()->json([
                'success' => true,
                'message' => 'Batch interactions processed successfully',
                'processed_count' => count($interactions), // Will be processed async
                'submitted_count' => count($interactions),
                'errors' => [] // Errors will be logged, not returned to avoid blocking
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to process batch interactions',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get user's interaction history
     */
    public function history(Request $request): JsonResponse
    {
        $request->validate([
            'days' => 'nullable|integer|min:1|max:90',
            'interaction_types' => 'nullable|array',
            'business_id' => 'nullable|exists:businesses,id',
            'limit' => 'nullable|integer|min:1|max:100'
        ]);

        $user = Auth::user();
        $days = $request->input('days', 30);
        $interactionTypes = $request->input('interaction_types');
        $businessId = $request->input('business_id');
        $limit = $request->input('limit', 50);

        try {
            $query = UserInteraction::where('user_id', $user->id)
                ->where('created_at', '>=', now()->subDays($days))
                ->with(['business:id,name,slug,category_id', 'business.categories:id,name']);

            if ($interactionTypes) {
                $query->whereIn('interaction_type', $interactionTypes);
            }

            if ($businessId) {
                $query->where('business_id', $businessId);
            }

            $interactions = $query->orderByDesc('created_at')
                ->limit($limit)
                ->get();

            // Generate analytics
            $analytics = UserInteraction::getSummaryForUser($user->id, $days);

            return response()->json([
                'success' => true,
                'data' => [
                    'interactions' => $interactions,
                    'analytics' => $analytics,
                    'filters' => [
                        'days' => $days,
                        'interaction_types' => $interactionTypes,
                        'business_id' => $businessId,
                        'limit' => $limit
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get interaction history',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get user's personalization summary
     */
    public function personalizationSummary(Request $request): JsonResponse
    {
        $user = Auth::user();

        try {
            // Get current personalization level
            $personalizationLevel = app(\App\Services\ABTestingService::class)
                ->getVariantForUser('personalization_level', $user->id);

            // Get user profile data
            $userProfile = Cache::get("user_profile_fast:{$user->id}", []);

            // Get recent interaction summary
            $interactionSummary = UserInteraction::getSummaryForUser($user->id, 30);

            return response()->json([
                'success' => true,
                'data' => [
                    'personalization_level' => $personalizationLevel,
                    'preferred_categories' => $userProfile['preferred_categories'] ?? [],
                    'visited_businesses_count' => count($userProfile['visited_businesses'] ?? []),
                    'preferred_price_ranges' => $userProfile['preferred_price_range'] ?? [],
                    'interaction_summary' => $interactionSummary,
                    'profile_last_updated' => Cache::get("user_profile_fast:{$user->id}") ? 'cached' : 'needs_update'
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get personalization summary',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}
