<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RecommendationService;
use App\Models\Business;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class RecommendationController extends Controller
{
    public function __construct(
        private RecommendationService $recommendationService
    ) {}

    /**
     * Get personalized business recommendations for the authenticated user
     */
    public function getRecommendations(Request $request): JsonResponse
    {
        $request->validate([
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'categories' => 'nullable|array',
            'categories.*' => 'integer|exists:categories,id',
            'count' => 'nullable|integer|min:1|max:50'
        ]);

        $user = Auth::user();
        $latitude = $request->input('latitude');
        $longitude = $request->input('longitude');
        $categories = $request->input('categories');
        $count = $request->input('count', 20);

        $startTime = microtime(true);

        try {
            $recommendations = $this->recommendationService->getRecommendations(
                $user,
                $latitude,
                $longitude,
                $categories,
                $count
            );

            $responseTime = round((microtime(true) - $startTime) * 1000, 2);
            
            // Get personalization level for this user
            $personalizationLevel = app(\App\Services\ABTestingService::class)
                ->getVariantForUser('personalization_level', $user->id);

            // Calculate personalization stats
            $personalizedCount = $recommendations->where('personalization_applied', true)->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'recommendations' => $recommendations,
                    'total_count' => $recommendations->count(),
                    'location_used' => $latitude && $longitude,
                    'categories_filtered' => !empty($categories),
                    'algorithm' => 'fast_personalized',
                    'personalization_level' => $personalizationLevel,
                    'personalized_results' => $personalizedCount,
                    'response_time_ms' => $responseTime
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get recommendations',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get advanced AI-powered recommendations (slower but more accurate)
     */
    public function getAdvancedAIRecommendations(Request $request): JsonResponse
    {
        $request->validate([
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'categories' => 'nullable|array',
            'categories.*' => 'integer|exists:categories,id',
            'count' => 'nullable|integer|min:1|max:50'
        ]);

        $user = Auth::user();
        $latitude = $request->input('latitude');
        $longitude = $request->input('longitude');
        $categories = $request->input('categories');
        $count = $request->input('count', 20);

        try {
            $recommendations = $this->recommendationService->getAdvancedAIRecommendations(
                $user,
                $latitude,
                $longitude,
                $categories,
                $count
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'recommendations' => $recommendations,
                    'total_count' => $recommendations->count(),
                    'location_used' => $latitude && $longitude,
                    'categories_filtered' => !empty($categories),
                    'algorithm' => 'advanced_neural_ai'
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get advanced AI recommendations',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get personalized businesses based on user preferences and location
     */
    public function getPersonalizedBusinesses(Request $request): JsonResponse
    {
        $request->validate([
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'radius' => 'nullable|numeric|min:1|max:100',
            'categories' => 'nullable|array',
            'categories.*' => 'integer|exists:categories,id',
            'min_rating' => 'nullable|numeric|min:0|max:5',
            'price_range' => 'nullable|array',
            'price_range.min' => 'nullable|numeric|min:0',
            'price_range.max' => 'nullable|numeric|min:0'
        ]);

        $user = Auth::user();
        $latitude = $request->input('latitude');
        $longitude = $request->input('longitude');
        
        $options = [
            'radius' => $request->input('radius'),
            'categories' => $request->input('categories'),
            'min_rating' => $request->input('min_rating'),
            'price_range' => $request->input('price_range')
        ];

        try {
            $businesses = $this->recommendationService->getPersonalizedBusinesses(
                $user,
                $latitude,
                $longitude,
                $options
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'businesses' => $businesses,
                    'total_count' => $businesses->count(),
                    'applied_filters' => array_filter($options)
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get personalized businesses',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get businesses similar to a specific business
     */
    public function getSimilarBusinesses(Request $request, int $businessId): JsonResponse
    {
        $request->validate([
            'count' => 'nullable|integer|min:1|max:20'
        ]);

        $business = Business::find($businessId);
        if (!$business) {
            return response()->json([
                'success' => false,
                'message' => 'Business not found'
            ], 404);
        }

        $count = $request->input('count', 10);

        try {
            $similarBusinesses = $this->recommendationService->getSimilarBusinesses($business, $count);

            return response()->json([
                'success' => true,
                'data' => [
                    'business' => $business,
                    'similar_businesses' => $similarBusinesses,
                    'total_count' => $similarBusinesses->count()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get similar businesses',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Track user interaction with a business
     */
    public function trackInteraction(Request $request): JsonResponse
    {
        $request->validate([
            'business_id' => 'required|integer|exists:businesses,id',
            'interaction_type' => 'required|string|in:view,search_click,phone_call,favorite,unfavorite,review,share,collection_add,collection_remove,offer_view,offer_use,direction_request,website_click',
            'source' => 'nullable|string|max:100',
            'context' => 'nullable|array',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180'
        ]);

        $user = Auth::user();
        $business = Business::find($request->input('business_id'));

        if (!$business) {
            return response()->json([
                'success' => false,
                'message' => 'Business not found'
            ], 404);
        }

        try {
            $context = $request->input('context', []);
            
            // Add location to context if provided
            if ($request->has('latitude') && $request->has('longitude')) {
                $context['user_location'] = [
                    'latitude' => $request->input('latitude'),
                    'longitude' => $request->input('longitude')
                ];
            }

            $this->recommendationService->trackInteraction(
                $user,
                $business,
                $request->input('interaction_type'),
                array_merge($context, [
                    'source' => $request->input('source'),
                    'timestamp' => now()->toISOString(),
                    'user_agent' => $request->userAgent(),
                    'ip_address' => $request->ip()
                ])
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
     * Get trending businesses in user's area
     */
    public function getTrendingBusinesses(Request $request): JsonResponse
    {
        $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'radius' => 'nullable|numeric|min:1|max:100',
            'days' => 'nullable|integer|min:1|max:30',
            'categories' => 'nullable|array',
            'categories.*' => 'integer|exists:categories,id',
            'count' => 'nullable|integer|min:1|max:50'
        ]);

        $latitude = $request->input('latitude');
        $longitude = $request->input('longitude');
        $radius = $request->input('radius', 25);
        $days = $request->input('days', 7);
        $count = $request->input('count', 20);

        try {
            // Use the location-based filter to get trending businesses
            $locationFilter = app(\App\Services\LocationBasedFilter::class);
            $trendingBusinesses = $locationFilter->getTrendingInArea(
                $latitude,
                $longitude,
                $radius,
                $days,
                $count
            );

            // Filter by categories if specified
            $categories = $request->input('categories');
            if ($categories) {
                $trendingBusinesses = $trendingBusinesses->filter(function ($business) use ($categories) {
                    $businessCategories = $business->categories->pluck('id')->toArray();
                    return !empty(array_intersect($businessCategories, $categories));
                })->values();
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'trending_businesses' => $trendingBusinesses,
                    'total_count' => $trendingBusinesses->count(),
                    'location' => compact('latitude', 'longitude', 'radius'),
                    'time_period_days' => $days,
                    'categories_filtered' => !empty($categories)
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get trending businesses',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get user's interaction history for analytics
     */
    public function getInteractionHistory(Request $request): JsonResponse
    {
        $request->validate([
            'days' => 'nullable|integer|min:1|max:90',
            'interaction_types' => 'nullable|array',
            'interaction_types.*' => 'string',
            'business_id' => 'nullable|integer|exists:businesses,id'
        ]);

        $user = Auth::user();
        $days = $request->input('days', 30);
        $interactionTypes = $request->input('interaction_types');
        $businessId = $request->input('business_id');

        try {
            $query = $user->interactions()
                ->with(['business:id,name,slug', 'business.categories:id,name'])
                ->where('created_at', '>=', now()->subDays($days));

            if ($interactionTypes) {
                $query->whereIn('interaction_type', $interactionTypes);
            }

            if ($businessId) {
                $query->where('business_id', $businessId);
            }

            $interactions = $query->orderByDesc('created_at')->get();

            // Group interactions by type for analytics
            $analytics = [
                'total_interactions' => $interactions->count(),
                'interaction_types' => $interactions->groupBy('interaction_type')
                    ->map(function ($group) {
                        return [
                            'count' => $group->count(),
                            'total_weight' => $group->sum('weight')
                        ];
                    }),
                'most_interacted_businesses' => $interactions->groupBy('business_id')
                    ->map(function ($group) {
                        return [
                            'business' => $group->first()->business,
                            'interaction_count' => $group->count(),
                            'total_weight' => $group->sum('weight'),
                            'last_interaction' => $group->sortByDesc('created_at')->first()->created_at
                        ];
                    })
                    ->sortByDesc('total_weight')
                    ->take(10)
                    ->values()
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'interactions' => $interactions,
                    'analytics' => $analytics,
                    'time_period_days' => $days
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
}
