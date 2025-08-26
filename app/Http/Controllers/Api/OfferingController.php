<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BusinessOffering;
use App\Models\Review;
use App\Models\Favorite;
use App\Models\ReviewHelpfulVote;
use App\Services\AnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\PersonalAccessToken;

class OfferingController extends Controller
{
    protected $analyticsService;

    public function __construct(AnalyticsService $analyticsService)
    {
        $this->analyticsService = $analyticsService;
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
            $sortBy = $request->input('sort', 'sort_order');
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
                $userArea = $this->determineUserArea($latitude, $longitude);

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
            $userArea = $this->determineUserArea($latitude, $longitude);

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
     * Determine user area from coordinates
     */
    private function determineUserArea($latitude, $longitude)
    {
        if (!$latitude || !$longitude) {
            return 'Dhanmondi'; // Default area
        }

        // Bangladesh area boundaries
        $bangladeshAreas = [
            'Dhanmondi' => [
                'lat_min' => 23.740, 'lat_max' => 23.755,
                'lng_min' => 90.365, 'lng_max' => 90.380
            ],
            'Gulshan' => [
                'lat_min' => 23.780, 'lat_max' => 23.800,
                'lng_min' => 90.405, 'lng_max' => 90.425
            ],
            'Banani' => [
                'lat_min' => 23.785, 'lat_max' => 23.795,
                'lng_min' => 90.395, 'lng_max' => 90.410
            ],
            'Uttara' => [
                'lat_min' => 23.855, 'lat_max' => 23.885,
                'lng_min' => 90.395, 'lng_max' => 90.420
            ],
            'Mirpur' => [
                'lat_min' => 23.795, 'lat_max' => 23.825,
                'lng_min' => 90.345, 'lng_max' => 90.375
            ],
        ];

        // Check which area the coordinates fall into
        foreach ($bangladeshAreas as $areaName => $bounds) {
            if ($latitude >= $bounds['lat_min'] && $latitude <= $bounds['lat_max'] &&
                $longitude >= $bounds['lng_min'] && $longitude <= $bounds['lng_max']) {
                return $areaName;
            }
        }

        return 'Bangladesh';
    }
}
