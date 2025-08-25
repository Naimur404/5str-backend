<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\BusinessOffering;
use App\Models\Review;
use App\Models\Offer;
use App\Models\SearchLog;
use App\Models\Favorite;
use App\Models\ReviewHelpfulVote;
use App\Services\AnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\PersonalAccessToken;
use Illuminate\Support\Facades\Auth;

class BusinessController extends Controller
{
    protected $analyticsService;

    public function __construct(AnalyticsService $analyticsService)
    {
        $this->analyticsService = $analyticsService;
    }

    /**
     * Check if the given business is in user's favorites
     */
    private function checkIsFavorite($businessId, Request $request)
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
                            ->where('favoritable_type', 'App\\Models\\Business')
                            ->where('favoritable_id', $businessId)
                            ->exists();
                    }
                }
            } catch (\Exception $e) {
                // Token invalid or expired, continue as guest
                Log::debug('Auth failed in business favorite check: ' . $e->getMessage());
            }
        }
        
        return false;
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
     * Get all businesses with filtering and pagination
     */
    public function index(Request $request)
    {
        try {
            $latitude = $request->input('latitude');
            $longitude = $request->input('longitude');
            $radiusKm = $request->input('radius', 20);
            $page = $request->input('page', 1);
            $limit = $request->input('limit', 20);

            $query = Business::active()
                ->with(['category', 'logoImage']);

            // Location-based filtering
            if ($latitude && $longitude) {
                $query->nearby($latitude, $longitude, $radiusKm);
            }

            // Category filter
            if ($request->has('category_id')) {
                $query->inCategory($request->category_id);
            }

            // Rating filter
            if ($request->has('min_rating')) {
                $query->withRating($request->min_rating);
            }

            // Price range filter
            if ($request->has('price_min')) {
                $query->priceRange($request->price_min, $request->price_max);
            }

            // Features filter
            if ($request->boolean('has_delivery')) {
                $query->where('has_delivery', true);
            }
            if ($request->boolean('has_pickup')) {
                $query->where('has_pickup', true);
            }
            if ($request->boolean('has_parking')) {
                $query->where('has_parking', true);
            }
            if ($request->boolean('is_verified')) {
                $query->verified();
            }

            // Sort options
            $sortBy = $request->input('sort', 'discovery_score');
            switch ($sortBy) {
                case 'rating':
                    $query->orderBy('overall_rating', 'desc');
                    break;
                case 'distance':
                    // Already sorted by distance in nearby scope
                    break;
                case 'name':
                    $query->orderBy('business_name');
                    break;
                default:
                    $query->orderBy('discovery_score', 'desc');
            }

            $businesses = $query->paginate($limit, ['*'], 'page', $page);

            return response()->json([
                'success' => true,
                'data' => [
                    'businesses' => $businesses->items(),
                    'pagination' => [
                        'current_page' => $businesses->currentPage(),
                        'last_page' => $businesses->lastPage(),
                        'per_page' => $businesses->perPage(),
                        'total' => $businesses->total(),
                        'has_more' => $businesses->hasMorePages()
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch businesses',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Search businesses
     */
    public function search(Request $request)
    {
        try {
            $searchTerm = $request->input('q');
            $latitude = $request->input('latitude');
            $longitude = $request->input('longitude');
            $categoryId = $request->input('category_id');
            $radiusKm = $request->input('radius', 20);
            $page = $request->input('page', 1);
            $limit = $request->input('limit', 20);

            $query = Business::active()
                ->with(['category', 'logoImage']);

            // Text search
            if ($searchTerm) {
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('business_name', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('description', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('full_address', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('area', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('city', 'LIKE', "%{$searchTerm}%");
                });
            }

            // Category filter
            if ($categoryId) {
                $query->inCategory($categoryId);
            }

            // Location-based filtering
            if ($latitude && $longitude) {
                $query->nearby($latitude, $longitude, $radiusKm);
            }

            // Apply other filters
            if ($request->has('min_rating')) {
                $query->withRating($request->min_rating);
            }

            $businesses = $query->paginate($limit, ['*'], 'page', $page);

            // Log the search
            $this->logSearch($request, $businesses->total());

            return response()->json([
                'success' => true,
                'data' => [
                    'search_term' => $searchTerm,
                    'businesses' => $businesses->items(),
                    'pagination' => [
                        'current_page' => $businesses->currentPage(),
                        'last_page' => $businesses->lastPage(),
                        'per_page' => $businesses->perPage(),
                        'total' => $businesses->total(),
                        'has_more' => $businesses->hasMorePages()
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Search failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get nearby businesses
     */
    public function nearby(Request $request)
    {
        try {
            $latitude = $request->input('latitude');
            $longitude = $request->input('longitude');
            $radiusKm = $request->input('radius', 10);
            $limit = $request->input('limit', 20);

            if (!$latitude || !$longitude) {
                return response()->json([
                    'success' => false,
                    'message' => 'Latitude and longitude are required'
                ], 422);
            }

            $businesses = Business::active()
                ->nearby($latitude, $longitude, $radiusKm)
                ->with(['category', 'logoImage'])
                ->take($limit)
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'location' => [
                        'latitude' => $latitude,
                        'longitude' => $longitude,
                        'radius_km' => $radiusKm
                    ],
                    'businesses' => $businesses
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch nearby businesses',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get featured businesses
     */
    public function featured(Request $request)
    {
        try {
            $limit = $request->input('limit', 10);

            $businesses = Business::active()
                ->featured()
                ->with(['category', 'logoImage'])
                ->orderBy('overall_rating', 'desc')
                ->take($limit)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $businesses
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch featured businesses',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get business details
     */
    public function show(Request $request, $businessId)
    {
        try {
            $business = Business::active()
                ->with([
                    'category',
                    'subcategory',
                    'owner',
                    'images',
                    'logoImage',
                    'coverImage',
                    'galleryImages'
                ])
                ->findOrFail($businessId);

            // Track business view for analytics
            try {
                $this->analyticsService->logView($business, $request);
                Log::info("Business view tracked for business ID: {$businessId}");
            } catch (\Exception $e) {
                Log::error("Failed to track business view: " . $e->getMessage());
            }

            // Update discovery score if user location is provided
            if ($request->has('latitude') && $request->has('longitude')) {
                $business->updateDiscoveryScore($request->latitude, $request->longitude);
            }

            // Check if business is in user's favorites
            $isFavorite = $this->checkIsFavorite($businessId, $request);

            // Convert business to array and add is_favorite flag
            $businessData = $business->toArray();
            $businessData['is_favorite'] = $isFavorite;

            return response()->json([
                'success' => true,
                'data' => $businessData
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Business not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Get business offerings (products/services)
     */
    public function offerings(Request $request, $businessId)
    {
        try {
            $business = Business::active()->findOrFail($businessId);
            
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

            $offerings = $query->with(['category', 'variants'])
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();

            // Add is_favorite flag to each offering
            $offeringsWithFavorites = $offerings->map(function($offering) use ($request) {
                $offeringData = $offering->toArray();
                $offeringData['is_favorite'] = $this->checkIsOfferingFavorite($offering->id, $request);
                return $offeringData;
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'business' => $business->only(['id', 'business_name']),
                    'offerings' => $offeringsWithFavorites
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
     * Get business reviews
     */
    public function reviews(Request $request, $businessId)
    {
        try {
            $business = Business::active()->findOrFail($businessId);
            
            // Get authenticated user (if any)
            $user = $this->getOptionalAuthUser($request);
            
            $page = $request->input('page', 1);
            $limit = $request->input('limit', 20);

            $query = Review::where('reviewable_type', 'App\\Models\\Business')
                ->where('reviewable_id', $businessId)
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
                    'business' => $business->only(['id', 'business_name', 'overall_rating', 'total_reviews']),
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
                'message' => 'Failed to fetch reviews',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get business offers
     */
    public function offers(Request $request, $businessId)
    {
        try {
            $business = Business::active()->findOrFail($businessId);
            
            $offers = Offer::where('business_id', $businessId)
                ->where('is_active', true)
                ->where('valid_from', '<=', now())
                ->where('valid_to', '>=', now())
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'business' => $business->only(['id', 'business_name']),
                    'offers' => $offers
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch offers',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Track business click from search results
     */
    public function trackClick(Request $request, Business $business)
    {
        try {
            // Log the view
            $this->analyticsService->logView($business, $request);

            // Update search log if search_log_id is provided
            if ($request->has('search_log_id')) {
                $this->analyticsService->updateSearchClick(
                    $request->input('search_log_id'),
                    $business->id
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'Click tracked successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to track click: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to track click'
            ], 500);
        }
    }

    /**
     * Log search activity
     */
    private function logSearch(Request $request, $resultsCount)
    {
        try {
            $this->analyticsService->logSearch(
                searchTerm: $request->input('q'),
                categoryId: $request->input('category_id'),
                userLatitude: $request->input('latitude') ? (float) $request->input('latitude') : null,
                userLongitude: $request->input('longitude') ? (float) $request->input('longitude') : null,
                filtersApplied: $request->except(['q', 'page', 'limit']),
                resultsCount: $resultsCount,
                request: $request
            );
        } catch (\Exception $e) {
            // Log error but don't fail the request
            Log::error('Failed to log search: ' . $e->getMessage());
        }
    }
}
