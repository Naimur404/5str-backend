<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BusinessOffering;
use App\Models\Review;
use App\Models\Favorite;
use App\Models\ReviewHelpfulVote;
use App\Services\AnalyticsService;
use App\Services\LocationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\PersonalAccessToken;

class OfferingController extends Controller
{
    protected $analyticsService;
    protected $locationService;

    public function __construct(AnalyticsService $analyticsService, LocationService $locationService)
    {
        $this->analyticsService = $analyticsService;
        $this->locationService = $locationService;
    }
    /**
     * Check if the given offering is in user's favorites
     */
    private function checkIsOfferingFavorite($offeringId, Request $request)
    {
        // Try to authenticate the user if token is provided
        if ($request->hasHeader('Authorization')) {
            try {
                $authHeader = $request->header('Authorization');
                if (strpos($authHeader, 'Bearer ') === 0) {
                    $token = substr($authHeader, 7);
                    
                    // Find the token and its associated user
                    $accessToken = PersonalAccessToken::findToken($token);
                    if ($accessToken && $accessToken->tokenable) {
                        $user = $accessToken->tokenable;
                        
                        return Favorite::where('user_id', $user->id)
                            ->where('favoritable_type', 'App\\Models\\BusinessOffering')
                            ->where('favoritable_id', $offeringId)
                            ->exists();
                    }
                }
            } catch (\Exception $e) {
                // Token invalid or expired, continue as guest
                Log::debug('Auth failed in offering favorite check: ' . $e->getMessage());
            }
        }
        
        return false;
    }

    /**
     * Get authenticated user from optional token
     */
    private function getOptionalAuthUser(Request $request)
    {
        if ($request->hasHeader('Authorization')) {
            try {
                $authHeader = $request->header('Authorization');
                if (strpos($authHeader, 'Bearer ') === 0) {
                    $token = substr($authHeader, 7);
                    
                    // Find the token and its associated user
                    $accessToken = PersonalAccessToken::findToken($token);
                    if ($accessToken && $accessToken->tokenable) {
                        return $accessToken->tokenable;
                    }
                }
            } catch (\Exception $e) {
                // Token invalid or expired, continue as guest
                Log::debug('Auth failed in optional auth: ' . $e->getMessage());
            }
        }
        
        return null;
    }

    /**
     * Get user vote status for a review
     */
    private function getUserVoteStatus($reviewId, $user)
    {
        if (!$user) {
            return [
                'has_voted' => false,
                'user_vote' => null
            ];
        }

        $vote = ReviewHelpfulVote::where('review_id', $reviewId)
            ->where('user_id', $user->id)
            ->first();

        return [
            'has_voted' => $vote !== null,
            'user_vote' => $vote ? $vote->is_helpful : null
        ];
    }

    /**
     * Get business offerings (products/services/menu)
     */
    public function index(Request $request, $businessId)
    {
        try {
            $query = BusinessOffering::where('business_id', $businessId)
                ->available();

            // Filter by type
            if ($request->has('type')) {
                if ($request->type === 'products') {
                    $query->products();
                } elseif ($request->type === 'services') {
                    $query->services();
                }
            }

            // Filter by category
            if ($request->has('category_id')) {
                $query->where('category_id', $request->category_id);
            }

            // Sort options
            $sortBy = $request->input('sort', 'rating');
            switch ($sortBy) {
                case 'rating':
                    $query->orderBy('average_rating', 'desc');
                    break;
                case 'price_low':
                    $query->orderBy('price', 'asc');
                    break;
                case 'price_high':
                    $query->orderBy('price', 'desc');
                    break;
                case 'popular':
                    $query->popular()->orderBy('total_reviews', 'desc');
                    break;
                case 'name':
                    $query->orderBy('name', 'asc');
                    break;
                default:
                    $query->orderBy('sort_order')->orderBy('name');
            }

            $offerings = $query->with(['category:id,name,slug'])
                ->get()
                ->map(function($offering) use ($request) {
                    return [
                        'id' => $offering->id,
                        'name' => $offering->name,
                        'description' => $offering->description,
                        'offering_type' => $offering->offering_type,
                        'price' => $offering->price,
                        'price_max' => $offering->price_max,
                        'price_range' => $offering->price_range,
                        'currency' => $offering->currency,
                        'image_url' => $offering->image_url,
                        'is_available' => $offering->is_available,
                        'is_popular' => $offering->is_popular,
                        'is_featured' => $offering->is_featured,
                        'average_rating' => $offering->average_rating,
                        'total_reviews' => $offering->total_reviews,
                        'is_favorite' => $this->checkIsOfferingFavorite($offering->id, $request),
                        'category' => $offering->category ? [
                            'id' => $offering->category->id,
                            'name' => $offering->category->name,
                            'slug' => $offering->category->slug,
                        ] : null,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'business_id' => $businessId,
                    'offerings' => $offerings
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch offerings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get offering details
     */
    public function show(Request $request, $businessId, $offeringId)
    {
        try {
            $offering = BusinessOffering::where('business_id', $businessId)
                ->where('id', $offeringId)
                ->available()
                ->with(['category:id,name,slug', 'business:id,business_name,slug'])
                ->firstOrFail();

            // Track offering view for analytics with location data
            try {
                $latitude = $request->input('latitude');
                $longitude = $request->input('longitude');
                $userArea = $this->locationService->determineUserAreaPrecise($latitude, $longitude);

                // Use enhanced tracking for trending analysis
                $this->analyticsService->logOfferingView(
                    offeringId: $offeringId,
                    businessId: $businessId,
                    userLatitude: $latitude ? (float) $latitude : null,
                    userLongitude: $longitude ? (float) $longitude : null,
                    userArea: $userArea,
                    request: $request
                );
                
                Log::info("Offering view tracked for offering ID: {$offeringId} in area: {$userArea}");
            } catch (\Exception $e) {
                Log::error("Failed to track offering view: " . $e->getMessage());
            }

            $data = [
                'id' => $offering->id,
                'name' => $offering->name,
                'description' => $offering->description,
                'offering_type' => $offering->offering_type,
                'price' => $offering->price,
                'price_max' => $offering->price_max,
                'price_range' => $offering->price_range,
                'currency' => $offering->currency,
                'image_url' => $offering->image_url,
                'is_available' => $offering->is_available,
                'is_popular' => $offering->is_popular,
                'is_featured' => $offering->is_featured,
                'average_rating' => $offering->average_rating,
                'total_reviews' => $offering->total_reviews,
                'is_favorite' => $this->checkIsOfferingFavorite($offering->id, $request),
                'business' => [
                    'id' => $offering->business->id,
                    'business_name' => $offering->business->business_name,
                    'slug' => $offering->business->slug,
                ],
                'category' => $offering->category ? [
                    'id' => $offering->category->id,
                    'name' => $offering->category->name,
                    'slug' => $offering->category->slug,
                ] : null,
            ];

            return response()->json([
                'success' => true,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Offering not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Get offering reviews
     */
    public function reviews(Request $request, $businessId, $offeringId)
    {
        try {
            $offering = BusinessOffering::where('business_id', $businessId)
                ->where('id', $offeringId)
                ->available()
                ->firstOrFail();
            
            // Get authenticated user (if any)
            $user = $this->getOptionalAuthUser($request);
            
            $page = $request->input('page', 1);
            $limit = $request->input('limit', 20);

            $query = Review::where('reviewable_type', 'App\\Models\\BusinessOffering')
                ->where('reviewable_id', $offeringId)
                ->approved()
                ->with(['user:id,name,profile_image,trust_level', 'images:id,review_id,image_url']);

            // Sort options
            $sortBy = $request->input('sort', 'recent');
            switch ($sortBy) {
                case 'helpful':
                    $query->orderBy('helpful_count', 'desc');
                    break;
                case 'rating_high':
                    $query->orderBy('overall_rating', 'desc');
                    break;
                case 'rating_low':
                    $query->orderBy('overall_rating', 'asc');
                    break;
                default:
                    $query->orderBy('created_at', 'desc');
            }

            $reviews = $query->paginate($limit, ['*'], 'page', $page);

            // Map reviews to lightweight format with vote status
            $reviewsData = $reviews->getCollection()->map(function($review) use ($user) {
                $voteStatus = $this->getUserVoteStatus($review->id, $user);
                
                return [
                    'id' => $review->id,
                    'overall_rating' => $review->overall_rating,
                    'service_rating' => $review->service_rating,
                    'quality_rating' => $review->quality_rating,
                    'value_rating' => $review->value_rating,
                    'title' => $review->title,
                    'review_text' => $review->review_text,
                    'pros' => $review->pros,
                    'cons' => $review->cons,
                    'visit_date' => $review->visit_date,
                    'is_recommended' => $review->is_recommended,
                    'is_verified_visit' => $review->is_verified_visit,
                    'helpful_count' => $review->helpful_count,
                    'not_helpful_count' => $review->not_helpful_count,
                    'user_vote_status' => [
                        'has_voted' => $voteStatus['has_voted'],
                        'user_vote' => $voteStatus['user_vote'] // true = helpful, false = not helpful, null = no vote
                    ],
                    'user' => [
                        'id' => $review->user->id,
                        'name' => $review->user->name,
                        'profile_image' => $review->user->profile_image,
                        'trust_level' => $review->user->trust_level,
                    ],
                    'images' => $review->images->map(function($image) {
                        return [
                            'id' => $image->id,
                            'image_url' => $image->image_url,
                        ];
                    })->toArray(),
                    'created_at' => $review->created_at->format('Y-m-d H:i:s'),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'offering' => [
                        'id' => $offering->id,
                        'name' => $offering->name,
                        'offering_type' => $offering->offering_type,
                        'average_rating' => $offering->average_rating,
                        'total_reviews' => $offering->total_reviews
                    ],
                    'reviews' => $reviewsData,
                    'pagination' => [
                        'current_page' => $reviews->currentPage(),
                        'last_page' => $reviews->lastPage(),
                        'per_page' => $reviews->perPage(),
                        'total' => $reviews->total(),
                        'has_more' => $reviews->hasMorePages()
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch offering reviews',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Track offering view for trending analysis
     */
    public function trackOfferingView(Request $request, $businessId, $offeringId)
    {
        try {
            $offering = BusinessOffering::where('business_id', $businessId)
                ->where('id', $offeringId)
                ->available()
                ->firstOrFail();

            $latitude = $request->input('latitude');
            $longitude = $request->input('longitude');
            $userArea = $this->locationService->determineUserAreaPrecise($latitude, $longitude);

            // Track the view event for trending analysis
            $this->analyticsService->logOfferingView(
                offeringId: $offeringId,
                businessId: $businessId,
                userLatitude: $latitude ? (float) $latitude : null,
                userLongitude: $longitude ? (float) $longitude : null,
                userArea: $userArea,
                request: $request
            );

            return response()->json([
                'success' => true,
                'message' => 'Offering view tracked',
                'data' => [
                    'offering_id' => $offeringId,
                    'business_id' => $businessId,
                    'user_area' => $userArea
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to track offering view: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to track offering view'
            ], 500);
        }
    }

    /**
     * Get offering analytics by area
     * Uses LocationService for precise area detection and analytics
     */
    public function getOfferingAnalyticsByArea(Request $request)
    {
        try {
            $latitude = $request->input('latitude');
            $longitude = $request->input('longitude');
            $radiusKm = $request->input('radius', 20);
            $categoryId = $request->input('category_id');
            $businessId = $request->input('business_id');

            if (!$latitude || !$longitude) {
                return response()->json([
                    'success' => false,
                    'message' => 'Latitude and longitude are required'
                ], 422);
            }

            // Get detailed location insights
            $locationInsights = $this->locationService->getAreaInsights($latitude, $longitude);
            
            // Build query for offerings in the area
            $query = BusinessOffering::active()
                ->whereHas('business', function ($q) use ($latitude, $longitude, $radiusKm) {
                    $q->active()
                      ->nearbyWithDistance($latitude, $longitude, $radiusKm);
                })
                ->with([
                    'business:id,business_name,slug,latitude,longitude,overall_rating,total_reviews',
                    'category:id,name,slug',
                    'variants:id,business_offering_id,name,price'
                ]);

            if ($categoryId) {
                $query->where('category_id', $categoryId);
            }

            if ($businessId) {
                $query->where('business_id', $businessId);
            }

            $offerings = $query->get();

            // Add distance calculation for each offering
            $offerings = $offerings->map(function ($offering) use ($latitude, $longitude) {
                if ($offering->business) {
                    $offering->distance_km = $this->calculateDistance(
                        $latitude, $longitude,
                        $offering->business->latitude, $offering->business->longitude
                    );
                }
                return $offering;
            })->sortBy('distance_km');

            // Calculate area statistics
            $stats = [
                'total_offerings' => $offerings->count(),
                'unique_businesses' => $offerings->pluck('business_id')->unique()->count(),
                'avg_business_rating' => $offerings->avg('business.overall_rating') ? round($offerings->avg('business.overall_rating'), 2) : 0,
                'total_business_reviews' => $offerings->sum('business.total_reviews'),
                'category_distribution' => $offerings->groupBy('category.name')->map->count(),
                'price_range_analysis' => [
                    'min_price' => $offerings->flatMap->variants->min('price'),
                    'max_price' => $offerings->flatMap->variants->max('price'),
                    'avg_price' => $offerings->flatMap->variants->avg('price') ? round($offerings->flatMap->variants->avg('price'), 2) : 0
                ],
                'distance_distribution' => [
                    'within_5km' => $offerings->where('distance_km', '<=', 5)->count(),
                    '5_to_10km' => $offerings->whereBetween('distance_km', [5.01, 10])->count(),
                    '10_to_15km' => $offerings->whereBetween('distance_km', [10.01, 15])->count(),
                    'above_15km' => $offerings->where('distance_km', '>', 15)->count(),
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'location_details' => $locationInsights,
                    'search_parameters' => [
                        'center_coordinates' => ['latitude' => $latitude, 'longitude' => $longitude],
                        'radius_km' => $radiusKm,
                        'category_filter' => $categoryId,
                        'business_filter' => $businessId
                    ],
                    'offering_statistics' => $stats,
                    'sample_offerings' => $offerings->take(5)->map(function ($offering) {
                        return [
                            'id' => $offering->id,
                            'name' => $offering->name,
                            'business_name' => $offering->business->business_name ?? null,
                            'category' => $offering->category->name ?? null,
                            'distance_km' => round($offering->distance_km ?? 0, 2),
                            'business_rating' => $offering->business->overall_rating ?? null,
                            'price_range' => $offering->variants->isEmpty() ? null : [
                                'min' => $offering->variants->min('price'),
                                'max' => $offering->variants->max('price')
                            ]
                        ];
                    })
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Offering area analytics error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get offering analytics'
            ], 500);
        }
    }

    /**
     * Get offerings with enhanced area-based filtering and recommendations
     */
    public function getOfferingsByPreciseArea(Request $request)
    {
        try {
            $latitude = $request->input('latitude');
            $longitude = $request->input('longitude');
            $specificArea = $request->input('specific_area');
            $radiusKm = $request->input('radius', 15);
            $limit = $request->input('limit', 20);
            $sortBy = $request->input('sort_by', 'relevance'); // relevance, price, distance, rating
            $maxPrice = $request->input('max_price');
            $minPrice = $request->input('min_price');

            if (!$latitude || !$longitude) {
                return response()->json([
                    'success' => false,
                    'message' => 'Latitude and longitude are required'
                ], 422);
            }

            // Get user's precise location info
            $userArea = $this->locationService->determineUserAreaPrecise($latitude, $longitude);
            $locationInsights = $this->locationService->getAreaInsights($latitude, $longitude);

            // Build query for offerings
            $query = BusinessOffering::active()
                ->whereHas('business', function ($q) use ($latitude, $longitude, $radiusKm) {
                    $q->active()
                      ->nearbyWithDistance($latitude, $longitude, $radiusKm);
                })
                ->with([
                    'business:id,business_name,slug,latitude,longitude,overall_rating,total_reviews,price_range',
                    'category:id,name,slug,icon_image,color_code',
                    'variants:id,business_offering_id,name,price,description',
                    'images:id,business_offering_id,image_url'
                ]);

            // Apply price filtering
            if ($minPrice || $maxPrice) {
                $query->whereHas('variants', function ($q) use ($minPrice, $maxPrice) {
                    if ($minPrice) {
                        $q->where('price', '>=', $minPrice);
                    }
                    if ($maxPrice) {
                        $q->where('price', '<=', $maxPrice);
                    }
                });
            }

            $offerings = $query->get();

            // Add distance and area calculations
            $offerings = $offerings->map(function ($offering) use ($latitude, $longitude, $userArea) {
                if ($offering->business) {
                    $offering->distance_km = $this->calculateDistance(
                        $latitude, $longitude,
                        $offering->business->latitude, $offering->business->longitude
                    );
                    
                    $offering->business_area = $this->locationService->determineUserAreaPrecise(
                        $offering->business->latitude, 
                        $offering->business->longitude
                    );
                    
                    $offering->relevance_score = $this->calculateOfferingRelevanceScore($offering, $userArea);
                }
                return $offering;
            });

            // Filter by specific area if requested
            if ($specificArea && $specificArea === $userArea) {
                $offerings = $offerings->filter(function ($offering) use ($userArea) {
                    return $offering->business_area === $userArea;
                });
            }

            // Apply sorting
            switch ($sortBy) {
                case 'price':
                    $offerings = $offerings->sortBy(function ($offering) {
                        return $offering->variants->min('price') ?? 0;
                    });
                    break;
                case 'distance':
                    $offerings = $offerings->sortBy('distance_km');
                    break;
                case 'rating':
                    $offerings = $offerings->sortByDesc('business.overall_rating');
                    break;
                case 'relevance':
                default:
                    $offerings = $offerings->sortByDesc('relevance_score');
                    break;
            }

            $offerings = $offerings->take($limit)->values();

            // Track this search for analytics
            $this->analyticsService->logSearch(
                searchTerm: null,
                categoryId: null,
                userLatitude: (float) $latitude,
                userLongitude: (float) $longitude,
                userArea: $userArea,
                filtersApplied: [
                    'offering_area_search' => true,
                    'specific_area' => $specificArea,
                    'radius_km' => $radiusKm,
                    'sort_by' => $sortBy,
                    'price_range' => ['min' => $minPrice, 'max' => $maxPrice]
                ],
                resultsCount: $offerings->count(),
                request: $request
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'user_location' => $locationInsights,
                    'offerings' => $offerings->map(function ($offering) {
                        return [
                            'id' => $offering->id,
                            'name' => $offering->name,
                            'description' => $offering->description,
                            'business' => [
                                'id' => $offering->business->id,
                                'name' => $offering->business->business_name,
                                'slug' => $offering->business->slug,
                                'rating' => $offering->business->overall_rating,
                                'reviews_count' => $offering->business->total_reviews,
                                'price_range' => $offering->business->price_range,
                                'area' => $offering->business_area
                            ],
                            'category' => $offering->category,
                            'variants' => $offering->variants,
                            'images' => $offering->images,
                            'distance_km' => round($offering->distance_km ?? 0, 2),
                            'relevance_score' => $offering->relevance_score ?? null,
                            'price_range' => $offering->variants->isEmpty() ? null : [
                                'min' => $offering->variants->min('price'),
                                'max' => $offering->variants->max('price')
                            ]
                        ];
                    }),
                    'metadata' => [
                        'total_found' => $offerings->count(),
                        'search_radius_km' => $radiusKm,
                        'sort_by' => $sortBy,
                        'area_filtered' => $specificArea !== null,
                        'price_filtered' => $minPrice !== null || $maxPrice !== null
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Precise area offering search error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get offerings by area'
            ], 500);
        }
    }

    /**
     * Calculate offering relevance score for area-based recommendations
     */
    private function calculateOfferingRelevanceScore($offering, $userArea)
    {
        $score = 0;

        // Business rating contribution (0-30 points)
        if ($offering->business) {
            $score += ($offering->business->overall_rating / 5) * 30;
            
            // Review count factor (0-15 points)
            $reviewScore = min(($offering->business->total_reviews / 50) * 15, 15);
            $score += $reviewScore;
        }

        // Distance factor (0-20 points, closer is better)
        $distanceScore = max(0, 20 - ($offering->distance_km * 2));
        $score += $distanceScore;

        // Price competitiveness (0-15 points)
        if (!$offering->variants->isEmpty()) {
            $avgPrice = $offering->variants->avg('price');
            // Lower prices get higher scores (simplified logic)
            if ($avgPrice <= 500) {
                $score += 15;
            } elseif ($avgPrice <= 1000) {
                $score += 10;
            } elseif ($avgPrice <= 2000) {
                $score += 5;
            }
        }

        // Same area bonus (0-10 points)
        if ($offering->business_area === $userArea) {
            $score += 10;
        }

        // Variant availability bonus (0-10 points)
        $variantCount = $offering->variants->count();
        $score += min($variantCount * 2, 10);

        return round($score, 2);
    }

    /**
     * Get popular offerings in a specific area
     */
    public function getPopularOfferingsInArea(Request $request)
    {
        try {
            $latitude = $request->input('latitude');
            $longitude = $request->input('longitude');
            $radiusKm = $request->input('radius', 25);
            $limit = $request->input('limit', 15);
            $timeframe = $request->input('timeframe', 'last_30_days'); // last_7_days, last_30_days, last_90_days

            if (!$latitude || !$longitude) {
                return response()->json([
                    'success' => false,
                    'message' => 'Latitude and longitude are required'
                ], 422);
            }

            // Get location insights
            $locationInsights = $this->locationService->getAreaInsights($latitude, $longitude);

            // Calculate date range for popularity analysis
            $endDate = now();
            $startDate = match($timeframe) {
                'last_7_days' => now()->subDays(7),
                'last_30_days' => now()->subDays(30),
                'last_90_days' => now()->subDays(90),
                default => now()->subDays(30)
            };

            // Get offerings with view counts from analytics
            $offerings = BusinessOffering::active()
                ->whereHas('business', function ($q) use ($latitude, $longitude, $radiusKm) {
                    $q->active()
                      ->nearbyWithDistance($latitude, $longitude, $radiusKm);
                })
                ->with([
                    'business:id,business_name,slug,latitude,longitude,overall_rating,total_reviews',
                    'category:id,name,slug',
                    'variants:id,business_offering_id,name,price',
                    'images:id,business_offering_id,image_url'
                ])
                ->get();

            // Add popularity scoring based on multiple factors
            $offerings = $offerings->map(function ($offering) use ($latitude, $longitude, $startDate, $endDate) {
                $offering->distance_km = $this->calculateDistance(
                    $latitude, $longitude,
                    $offering->business->latitude, $offering->business->longitude
                );

                // Calculate popularity score (this could be enhanced with actual view tracking data)
                $offering->popularity_score = $this->calculatePopularityScore($offering, $startDate, $endDate);
                
                return $offering;
            })->sortByDesc('popularity_score')->take($limit);

            return response()->json([
                'success' => true,
                'data' => [
                    'location_details' => $locationInsights,
                    'timeframe' => $timeframe,
                    'period' => [
                        'start_date' => $startDate->format('Y-m-d'),
                        'end_date' => $endDate->format('Y-m-d')
                    ],
                    'popular_offerings' => $offerings->map(function ($offering) {
                        return [
                            'id' => $offering->id,
                            'name' => $offering->name,
                            'description' => $offering->description,
                            'business' => [
                                'id' => $offering->business->id,
                                'name' => $offering->business->business_name,
                                'rating' => $offering->business->overall_rating
                            ],
                            'category' => $offering->category,
                            'distance_km' => round($offering->distance_km, 2),
                            'popularity_score' => $offering->popularity_score,
                            'price_range' => $offering->variants->isEmpty() ? null : [
                                'min' => $offering->variants->min('price'),
                                'max' => $offering->variants->max('price')
                            ],
                            'main_image' => $offering->images->first()->image_url ?? null
                        ];
                    }),
                    'metadata' => [
                        'total_found' => $offerings->count(),
                        'search_radius_km' => $radiusKm,
                        'ranking_factors' => [
                            'business_rating',
                            'offering_engagement',
                            'price_competitiveness',
                            'distance_proximity'
                        ]
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Popular offerings in area error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get popular offerings'
            ], 500);
        }
    }

    /**
     * Calculate popularity score for offerings
     */
    private function calculatePopularityScore($offering, $startDate, $endDate)
    {
        $score = 0;

        // Business rating factor (0-40 points)
        if ($offering->business) {
            $score += ($offering->business->overall_rating / 5) * 40;
            
            // Review count factor (0-20 points)
            $reviewScore = min(($offering->business->total_reviews / 100) * 20, 20);
            $score += $reviewScore;
        }

        // Variant count factor (0-15 points)
        $variantScore = min($offering->variants->count() * 3, 15);
        $score += $variantScore;

        // Price attractiveness (0-15 points)
        if (!$offering->variants->isEmpty()) {
            $avgPrice = $offering->variants->avg('price');
            if ($avgPrice <= 300) {
                $score += 15;
            } elseif ($avgPrice <= 600) {
                $score += 12;
            } elseif ($avgPrice <= 1000) {
                $score += 8;
            } elseif ($avgPrice <= 2000) {
                $score += 4;
            }
        }

        // Image availability bonus (0-10 points)
        if ($offering->images->isNotEmpty()) {
            $score += 10;
        }

        return round($score, 2);
    }

    /**
     * Calculate distance between two points using Haversine formula
     */
    private function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371; // Earth's radius in kilometers

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return round($earthRadius * $c, 2);
    }
}
