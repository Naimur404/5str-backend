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
            // Use light personalization by default (removed A/B testing complexity)
            $personalizationLevel = 'light'; // You can change this to 'none', 'light', or 'full'
            
            $recommendations = $this->recommendationService->getRecommendations(
                $user,
                $latitude,
                $longitude,
                $categories,
                $count
            );

            $responseTime = round((microtime(true) - $startTime) * 1000, 2);

            // Calculate personalization stats
            $personalizedCount = $recommendations->where('personalization_applied', true)->count();

            // Transform recommendations with complete data
            $transformedRecommendations = $recommendations->map(function ($business) {
                return [
                    'id' => $business->id,
                    'name' => $business->business_name,
                    'slug' => $business->slug,
                    'description' => $business->description,
                    'phone' => $business->business_phone,
                    'email' => $business->business_email,
                    'website' => $business->website_url,
                    'address' => [
                        'full_address' => $business->full_address,
                        'city' => $business->city,
                        'area' => $business->area,
                        'landmark' => $business->landmark,
                    ],
                    'location' => [
                        'latitude' => $business->latitude,
                        'longitude' => $business->longitude,
                    ],
                    'rating' => [
                        'overall_rating' => $business->overall_rating,
                        'total_reviews' => $business->total_reviews,
                    ],
                    'price_range' => $business->price_range,
                    'features' => [
                        'has_delivery' => $business->has_delivery,
                        'has_pickup' => $business->has_pickup,
                        'has_parking' => $business->has_parking,
                        'is_verified' => $business->is_verified,
                        'is_featured' => $business->is_featured,
                    ],
                    'opening_hours' => $business->opening_hours,
                    'images' => [
                        'logo' => $business->logoImage?->image_url,
                        'cover' => $business->coverImage?->image_url,
                        'gallery' => $business->galleryImages->pluck('image_url')->toArray(),
                    ],
                    'category' => $business->category ? [
                        'id' => $business->category->id,
                        'name' => $business->category->name,
                        'icon' => $business->category->icon_image,
                    ] : null,
                    'subcategory' => $business->subcategory ? [
                        'id' => $business->subcategory->id,
                        'name' => $business->subcategory->name,
                    ] : null,
                    'offers' => $business->activeOffers->map(function ($offer) {
                        return [
                            'id' => $offer->id,
                            'title' => $offer->title,
                            'description' => $offer->description,
                            'discount_percentage' => $offer->discount_percentage,
                            'valid_to' => $offer->valid_to,
                        ];
                    }),
                    'recent_reviews' => $business->reviews->map(function ($review) {
                        return [
                            'id' => $review->id,
                            'rating' => $review->rating,
                            'comment' => $review->comment,
                            'created_at' => $review->created_at,
                        ];
                    }),
                    'personalization_score' => $business->personalization_applied ?? 0,
                    'distance_km' => $business->distance ?? null,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'recommendations' => $transformedRecommendations,
                    'total_count' => $recommendations->count(),
                    'location_used' => $latitude && $longitude,
                    'categories_filtered' => !empty($categories),
                    'algorithm' => 'fast_personalized',
                    'personalization_level' => $personalizationLevel,
                    'personalized_results' => $personalizedCount,
                    'response_time_ms' => $responseTime,
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

        $business = Business::with([
            'category:id,name,icon_image',
            'subcategory:id,name',
            'logoImage:id,business_id,image_url,image_type',
            'coverImage:id,business_id,image_url,image_type'
        ])->find($businessId);
        
        if (!$business) {
            return response()->json([
                'success' => false,
                'message' => 'Business not found'
            ], 404);
        }

        $count = $request->input('count', 10);

        try {
            $similarBusinesses = $this->recommendationService->getSimilarBusinesses($business, $count);

            // Transform similar businesses with complete data
            $transformedSimilar = $similarBusinesses->map(function ($similarBusiness) {
                return [
                    'id' => $similarBusiness->id,
                    'name' => $similarBusiness->business_name,
                    'slug' => $similarBusiness->slug,
                    'description' => $similarBusiness->description,
                    'phone' => $similarBusiness->business_phone,
                    'email' => $similarBusiness->business_email,
                    'website' => $similarBusiness->website_url,
                    'address' => [
                        'full_address' => $similarBusiness->full_address,
                        'city' => $similarBusiness->city,
                        'area' => $similarBusiness->area,
                        'landmark' => $similarBusiness->landmark,
                    ],
                    'location' => [
                        'latitude' => $similarBusiness->latitude,
                        'longitude' => $similarBusiness->longitude,
                    ],
                    'rating' => [
                        'overall_rating' => $similarBusiness->overall_rating,
                        'total_reviews' => $similarBusiness->total_reviews,
                    ],
                    'price_range' => $similarBusiness->price_range,
                    'features' => [
                        'has_delivery' => $similarBusiness->has_delivery,
                        'has_pickup' => $similarBusiness->has_pickup,
                        'has_parking' => $similarBusiness->has_parking,
                        'is_verified' => $similarBusiness->is_verified,
                        'is_featured' => $similarBusiness->is_featured,
                    ],
                    'opening_hours' => $similarBusiness->opening_hours,
                    'images' => [
                        'logo' => $similarBusiness->logoImage?->image_url,
                        'cover' => $similarBusiness->coverImage?->image_url,
                        'gallery' => $similarBusiness->galleryImages->pluck('image_url')->toArray(),
                    ],
                    'category' => $similarBusiness->category ? [
                        'id' => $similarBusiness->category->id,
                        'name' => $similarBusiness->category->name,
                        'icon' => $similarBusiness->category->icon_image,
                    ] : null,
                    'subcategory' => $similarBusiness->subcategory ? [
                        'id' => $similarBusiness->subcategory->id,
                        'name' => $similarBusiness->subcategory->name,
                    ] : null,
                    'offers' => $similarBusiness->activeOffers->map(function ($offer) {
                        return [
                            'id' => $offer->id,
                            'title' => $offer->title,
                            'description' => $offer->description,
                            'discount_percentage' => $offer->discount_percentage,
                            'valid_to' => $offer->valid_to,
                        ];
                    }),
                    'similarity_score' => $similarBusiness->similarity_score ?? 0,
                    'distance_km' => $similarBusiness->distance ?? null,
                ];
            });

            // Transform the main business data
            $transformedBusiness = [
                'id' => $business->id,
                'name' => $business->business_name,
                'slug' => $business->slug,
                'description' => $business->description,
                'rating' => [
                    'overall_rating' => $business->overall_rating,
                    'total_reviews' => $business->total_reviews,
                ],
                'images' => [
                    'logo' => $business->logoImage?->image_url,
                    'cover' => $business->coverImage?->image_url,
                ],
                'category' => $business->category ? [
                    'id' => $business->category->id,
                    'name' => $business->category->name,
                    'icon' => $business->category->icon_image,
                ] : null,
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'business' => $transformedBusiness,
                    'similar_businesses' => $transformedSimilar,
                    'total_count' => $transformedSimilar->count()
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

            // Load complete business data with relationships
            $trendingBusinesses->load([
                'category:id,name,icon_image',
                'subcategory:id,name',
                'logoImage:id,business_id,image_url,image_type',
                'coverImage:id,business_id,image_url,image_type',
                'galleryImages:id,business_id,image_url,image_type',
                'activeOffers:id,business_id,title,description,discount_percentage,valid_to',
                'reviews' => function($query) {
                    $query->latest()->limit(3)->select('id', 'reviewable_id', 'user_id', 'rating', 'comment', 'created_at');
                }
            ]);

            // Transform trending businesses with complete data
            $transformedTrending = $trendingBusinesses->map(function ($business) {
                return [
                    'id' => $business->id,
                    'name' => $business->business_name,
                    'slug' => $business->slug,
                    'description' => $business->description,
                    'phone' => $business->business_phone,
                    'email' => $business->business_email,
                    'website' => $business->website_url,
                    'address' => [
                        'full_address' => $business->full_address,
                        'city' => $business->city,
                        'area' => $business->area,
                        'landmark' => $business->landmark,
                    ],
                    'location' => [
                        'latitude' => $business->latitude,
                        'longitude' => $business->longitude,
                    ],
                    'rating' => [
                        'overall_rating' => $business->overall_rating,
                        'total_reviews' => $business->total_reviews,
                    ],
                    'price_range' => $business->price_range,
                    'features' => [
                        'has_delivery' => $business->has_delivery,
                        'has_pickup' => $business->has_pickup,
                        'has_parking' => $business->has_parking,
                        'is_verified' => $business->is_verified,
                        'is_featured' => $business->is_featured,
                    ],
                    'opening_hours' => $business->opening_hours,
                    'images' => [
                        'logo' => $business->logoImage?->image_url,
                        'cover' => $business->coverImage?->image_url,
                        'gallery' => $business->galleryImages->pluck('image_url')->toArray(),
                    ],
                    'category' => $business->category ? [
                        'id' => $business->category->id,
                        'name' => $business->category->name,
                        'icon' => $business->category->icon_image,
                    ] : null,
                    'subcategory' => $business->subcategory ? [
                        'id' => $business->subcategory->id,
                        'name' => $business->subcategory->name,
                    ] : null,
                    'offers' => $business->activeOffers->map(function ($offer) {
                        return [
                            'id' => $offer->id,
                            'title' => $offer->title,
                            'description' => $offer->description,
                            'discount_percentage' => $offer->discount_percentage,
                            'valid_to' => $offer->valid_to,
                        ];
                    }),
                    'recent_reviews' => $business->reviews->map(function ($review) {
                        return [
                            'id' => $review->id,
                            'rating' => $review->rating,
                            'comment' => $review->comment,
                            'created_at' => $review->created_at,
                        ];
                    }),
                    'trending_score' => $business->trending_score ?? 0,
                    'trending_growth' => $business->trending_growth ?? '0%',
                    'distance_km' => $business->distance ?? null,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'trending_businesses' => $transformedTrending,
                    'total_count' => $transformedTrending->count(),
                    'trending_analytics' => [
                        'trending_period' => "{$days} days",
                        'last_updated' => now()->toISOString(),
                        'geographical_scope' => [
                            'center' => compact('latitude', 'longitude'),
                            'radius_km' => $radius,
                            'coverage_area' => $radius > 25 ? 'city_wide' : 'local'
                        ],
                        'data_source' => 'real_time_analytics',
                        'algorithm_version' => 'v2.1'
                    ],
                    'filters' => [
                        'applied' => [
                            'location' => true,
                            'time_period' => $days,
                            'categories' => !empty($categories) ? $categories : null
                        ],
                        'available' => [
                            'time_periods' => ['1 day', '7 days', '14 days', '30 days'],
                            'max_radius' => 100,
                            'sortable_by' => ['trending_score', 'rating', 'distance', 'recent_activity']
                        ]
                    ],
                    'metadata' => [
                        'page_type' => 'full_trending_list',
                        'differs_from_home' => 'Complete business data with analytics',
                        'use_case' => 'Dedicated trending exploration',
                        'performance_notes' => 'Real-time trending calculations'
                    ]
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
