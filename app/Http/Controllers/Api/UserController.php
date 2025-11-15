<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Favorite;
use App\Models\UserPoint;
use App\Models\Review;
use App\Models\Business;
use App\Models\BusinessOffering;
use App\Models\Attraction;
use App\Models\UserAttractionInteraction;
use App\Models\User;
use App\Models\BusinessSubmission;
use App\Models\AttractionSubmission;
use App\Models\OfferingSubmission;
use App\Models\UserCollection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    /**
     * Get user profile information (public profile view)
     */
    public function profile(Request $request, $userId)
    {
        try {
            $user = User::findOrFail($userId);
            $authUser = Auth::user();

            // Basic user information
            $profileData = [
                'id' => $user->id,
                'name' => $user->name,
                'profile_image' => $user->profile_image,
                'city' => $user->city,
                'trust_level' => $user->trust_level,
                'total_points' => $user->total_points,
                'total_reviews_written' => $user->total_reviews_written,
                'is_verified_reviewer' => $user->isVerifiedReviewer(),
                'member_since' => $user->created_at->format('F Y'),
                'is_active' => $user->is_active,
            ];

            // Add user level and achievements
            $profileData['user_level'] = $user->getUserLevel();
            $profileData['achievements'] = $user->getAchievements();

            // Statistics
            $stats = [
                'total_reviews' => $user->reviews()->approved()->count(),
                'total_helpful_votes_received' => $user->reviews()->sum('helpful_count'),
                'total_businesses_reviewed' => $user->reviews()
                    ->where('reviewable_type', 'App\\Models\\Business')
                    ->approved()
                    ->distinct('reviewable_id')
                    ->count('reviewable_id'),
                'total_offerings_reviewed' => $user->reviews()
                    ->where('reviewable_type', 'App\\Models\\BusinessOffering')
                    ->approved()
                    ->distinct('reviewable_id')
                    ->count('reviewable_id'),
                'average_rating_given' => round($user->reviews()->approved()->avg('overall_rating') ?? 0, 1),
            ];

            // Submission statistics
            $submissionStats = [
                'business_submissions' => $user->businessSubmissions()->count(),
                'approved_business_submissions' => $user->businessSubmissions()->where('status', 'approved')->count(),
                'attraction_submissions' => $user->attractionSubmissions()->count(),
                'approved_attraction_submissions' => $user->attractionSubmissions()->where('status', 'approved')->count(),
                'offering_submissions' => $user->offeringSubmissions()->count(),
                'approved_offering_submissions' => $user->offeringSubmissions()->where('status', 'approved')->count(),
            ];

            $submissionStats['total_submissions'] = $submissionStats['business_submissions'] + 
                                                  $submissionStats['attraction_submissions'] + 
                                                  $submissionStats['offering_submissions'];

            $submissionStats['total_approved_submissions'] = $submissionStats['approved_business_submissions'] + 
                                                           $submissionStats['approved_attraction_submissions'] + 
                                                           $submissionStats['approved_offering_submissions'];

            // Collection statistics
            $collectionStats = [
                'total_collections' => $user->collections()->count(),
                'public_collections' => $user->collections()->where('is_public', true)->count(),
                'total_collection_followers' => $user->collections()
                    ->withCount('followers')
                    ->get()
                    ->sum('followers_count'),
            ];

            // Recent activity (last 5 approved reviews)
            $recentReviews = $user->reviews()
                ->approved()
                ->with(['reviewable'])
                ->orderBy('created_at', 'desc')
                ->take(5)
                ->get()
                ->map(function($review) {
                    $reviewable = $review->reviewable;
                    if (!$reviewable) return null;

                    $baseData = [
                        'id' => $review->id,
                        'overall_rating' => $review->overall_rating,
                        'title' => $review->title,
                        'review_text' => strlen($review->review_text) > 150 
                            ? substr($review->review_text, 0, 150) . '...' 
                            : $review->review_text,
                        'helpful_count' => $review->helpful_count,
                        'created_at' => $review->created_at->diffForHumans(),
                        'review_type' => $review->reviewable_type === 'App\\Models\\Business' ? 'business' : 'offering',
                    ];

                    if ($review->reviewable_type === 'App\\Models\\Business') {
                        $baseData['business'] = [
                            'id' => $reviewable->id,
                            'name' => $reviewable->business_name,
                            'slug' => $reviewable->slug,
                        ];
                    } else {
                        $baseData['offering'] = [
                            'id' => $reviewable->id,
                            'name' => $reviewable->name,
                            'business_name' => $reviewable->business->business_name ?? null,
                        ];
                    }

                    return $baseData;
                })->filter();

            // Recent public collections (if any)
            $recentCollections = $user->collections()
                ->where('is_public', true)
                ->withCount('businesses')
                ->orderBy('updated_at', 'desc')
                ->take(3)
                ->get()
                ->map(function($collection) {
                    return [
                        'id' => $collection->id,
                        'name' => $collection->name,
                        'description' => $collection->description,
                        'businesses_count' => $collection->businesses_count,
                        'is_featured' => $collection->is_featured,
                        'updated_at' => $collection->updated_at->diffForHumans(),
                    ];
                });

            // Points breakdown (if viewing own profile or admin)
            $pointsBreakdown = null;
            if ($authUser && ($authUser->id === $user->id || $authUser->hasAnyRole(['admin', 'super-admin']))) {
                $pointsBreakdown = $user->points()
                    ->selectRaw('point_type, SUM(points) as total_points, COUNT(*) as total_activities')
                    ->groupBy('point_type')
                    ->get()
                    ->mapWithKeys(function($item) {
                        return [$item->point_type => [
                            'total_points' => (int) $item->total_points,
                            'total_activities' => (int) $item->total_activities
                        ]];
                    });
            }

            // Privacy settings - hide sensitive info if not own profile
            if (!$authUser || $authUser->id !== $user->id) {
                unset($profileData['total_points']); // Hide exact points from other users
                $profileData['points_range'] = $this->getPointsRange($user->total_points);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'profile' => $profileData,
                    'statistics' => $stats,
                    'submissions' => $submissionStats,
                    'collections' => $collectionStats,
                    'recent_reviews' => $recentReviews,
                    'recent_collections' => $recentCollections,
                    'points_breakdown' => $pointsBreakdown,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'User profile not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Get points range for privacy (instead of exact points)
     */
    private function getPointsRange($points)
    {
        if ($points >= 10000) {
            return '10,000+';
        } elseif ($points >= 5000) {
            return '5,000 - 9,999';
        } elseif ($points >= 1000) {
            return '1,000 - 4,999';
        } elseif ($points >= 500) {
            return '500 - 999';
        } elseif ($points >= 100) {
            return '100 - 499';
        } else {
            return '0 - 99';
        }
    }

    /**
     * Get user's favorite businesses and offerings
     */
    public function favorites(Request $request)
    {
        try {
            $user = Auth::user();
            $type = $request->input('type'); // 'business', 'offering', 'attraction', or null for all
            $page = $request->input('page', 1);
            $limit = $request->input('limit', 20);

            // Get traditional favorites (businesses and offerings)
            $favoritesData = collect();

            if (!$type || in_array($type, ['business', 'offering'])) {
                $query = Favorite::where('user_id', $user->id)
                    ->with(['favoritable']);

                // Filter by type if specified
                if ($type === 'business') {
                    $query->where('favoritable_type', 'App\\Models\\Business');
                } elseif ($type === 'offering') {
                    $query->where('favoritable_type', 'App\\Models\\BusinessOffering');
                } elseif (!$type) {
                    // Get both business and offering favorites when no type specified
                    $query->whereIn('favoritable_type', ['App\\Models\\Business', 'App\\Models\\BusinessOffering']);
                }

                $favorites = $query->orderBy('created_at', 'desc')->get();

                // Map favorites to lightweight format
                $traditionalFavorites = $favorites->map(function($favorite) {
                    $item = $favorite->favoritable;
                    if (!$item) return null;

                    if ($favorite->favoritable_type === 'App\\Models\\Business') {
                        return [
                            'id' => $favorite->id,
                            'type' => 'business',
                            'favorited_at' => $favorite->created_at->format('Y-m-d H:i:s'),
                            'business' => [
                                'id' => $item->id,
                                'business_name' => $item->business_name,
                                'slug' => $item->slug,
                                'landmark' => $item->landmark,
                                'overall_rating' => $item->overall_rating,
                                'total_reviews' => $item->total_reviews,
                                'price_range' => $item->price_range,
                                'category_name' => $item->category->name ?? null,
                                'logo_image' => $item->logoImage->image_url ?? null,
                            ]
                        ];
                    } elseif ($favorite->favoritable_type === 'App\\Models\\BusinessOffering') {
                        return [
                            'id' => $favorite->id,
                            'type' => 'offering',
                            'favorited_at' => $favorite->created_at->format('Y-m-d H:i:s'),
                            'offering' => [
                                'id' => $item->id,
                                'name' => $item->name,
                                'business_id' => $item->business_id,
                                'offering_type' => $item->offering_type,
                                'price_range' => $item->price_range,
                                'average_rating' => $item->average_rating,
                                'total_reviews' => $item->total_reviews,
                                'business_name' => $item->business->business_name ?? null,
                                'image_url' => $item->image_url,
                            ]
                        ];
                    }
                    return null;
                })->filter();

                $favoritesData = $favoritesData->merge($traditionalFavorites);
            }

            // Get attraction favorites (bookmarks and likes)
            if (!$type || $type === 'attraction') {
                $attractionInteractions = UserAttractionInteraction::where('user_id', $user->id)
                    ->whereIn('interaction_type', ['bookmark', 'like', 'wishlist'])
                    ->where('is_active', true)
                    ->with(['attraction.coverImage', 'attraction.galleries'])
                    ->orderBy('created_at', 'desc')
                    ->get();

                $attractionFavorites = $attractionInteractions->map(function($interaction) {
                    $attraction = $interaction->attraction;
                    if (!$attraction) return null;

                    return [
                        'id' => $interaction->id,
                        'type' => 'attraction',
                        'interaction_type' => $interaction->interaction_type, // bookmark, like, wishlist
                        'favorited_at' => $interaction->created_at->format('Y-m-d H:i:s'),
                        'attraction' => [
                            'id' => $attraction->id,
                            'name' => $attraction->name,
                            'slug' => $attraction->slug,
                            'type' => $attraction->type,
                            'category' => $attraction->category,
                            'address' => $attraction->address,
                            'city' => $attraction->city,
                            'area' => $attraction->area,
                            'overall_rating' => $attraction->overall_rating,
                            'total_reviews' => $attraction->total_reviews,
                            'total_likes' => $attraction->total_likes,
                            'is_free' => $attraction->is_free,
                            'entry_fee' => $attraction->entry_fee,
                            'currency' => $attraction->currency,
                            'estimated_duration_minutes' => $attraction->estimated_duration_minutes,
                            'difficulty_level' => $attraction->difficulty_level,
                            'cover_image' => $attraction->cover_image_url,
                            'is_verified' => $attraction->is_verified,
                            'is_featured' => $attraction->is_featured,
                        ],
                        'notes' => $interaction->notes,
                        'user_rating' => $interaction->user_rating,
                    ];
                })->filter();

                $favoritesData = $favoritesData->merge($attractionFavorites);
            }

            // Sort all favorites by date
            $sortedFavorites = $favoritesData->sortByDesc('favorited_at')->values();

            // Implement pagination manually
            $currentPage = $page;
            $perPage = $limit;
            $total = $sortedFavorites->count();
            $offset = ($currentPage - 1) * $perPage;
            $paginatedFavorites = $sortedFavorites->slice($offset, $perPage)->values();

            $lastPage = ceil($total / $perPage);
            $hasMore = $currentPage < $lastPage;

            return response()->json([
                'success' => true,
                'data' => [
                    'favorites' => $paginatedFavorites,
                    'pagination' => [
                        'current_page' => $currentPage,
                        'last_page' => $lastPage,
                        'per_page' => $perPage,
                        'total' => $total,
                        'has_more' => $hasMore
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch favorites',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add item to favorites
     */
    public function addFavorite(Request $request)
    {
        try {
            $request->validate([
                'favoritable_type' => 'required|in:business,offering,attraction',
                'favoritable_id' => 'required|integer|min:1',
                'interaction_type' => 'required_if:favoritable_type,attraction|in:bookmark,like,wishlist',
                'notes' => 'nullable|string|max:1000',
                'is_public' => 'nullable|boolean',
                'priority' => 'nullable|in:low,medium,high',
                'planned_visit_date' => 'nullable|date|after:today'
            ]);

            $user = Auth::user();

            if ($request->favoritable_type === 'attraction') {
                // Handle attraction favorites through UserAttractionInteraction
                $attraction = Attraction::findOrFail($request->favoritable_id);
                $interactionType = $request->interaction_type ?? 'bookmark';

                // Check if interaction already exists
                $existing = UserAttractionInteraction::where('user_id', $user->id)
                    ->where('attraction_id', $request->favoritable_id)
                    ->where('interaction_type', $interactionType)
                    ->where('is_active', true)
                    ->first();

                if ($existing) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Attraction already ' . $interactionType . 'd'
                    ], 409);
                }

                // Prepare interaction data
                $interactionData = [];
                if ($interactionType === 'bookmark' || $interactionType === 'wishlist') {
                    $interactionData = [
                        'priority' => $request->priority ?? 'medium',
                        'planned_visit_date' => $request->planned_visit_date
                    ];
                }

                $interaction = UserAttractionInteraction::create([
                    'user_id' => $user->id,
                    'attraction_id' => $request->favoritable_id,
                    'interaction_type' => $interactionType,
                    'interaction_data' => $interactionData,
                    'notes' => $request->notes,
                    'is_public' => $request->is_public ?? true,
                    'interaction_date' => now()
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Attraction ' . $interactionType . 'd successfully',
                    'data' => [
                        'interaction_id' => $interaction->id,
                        'type' => 'attraction',
                        'interaction_type' => $interactionType
                    ]
                ]);
            } else {
                // Handle traditional favorites (business/offering)
                $type = $request->favoritable_type === 'business' ? 'App\\Models\\Business' : 'App\\Models\\BusinessOffering';
                
                // Check if item exists
                if ($type === 'App\\Models\\Business') {
                    $item = Business::findOrFail($request->favoritable_id);
                } else {
                    $item = BusinessOffering::findOrFail($request->favoritable_id);
                }

                // Check if already favorited
                $existing = Favorite::where('user_id', $user->id)
                    ->where('favoritable_type', $type)
                    ->where('favoritable_id', $request->favoritable_id)
                    ->first();

                if ($existing) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Item already in favorites'
                    ], 409);
                }

                $favorite = Favorite::create([
                    'user_id' => $user->id,
                    'favoritable_type' => $type,
                    'favoritable_id' => $request->favoritable_id
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Added to favorites',
                    'data' => [
                        'favorite_id' => $favorite->id,
                        'type' => $request->favoritable_type
                    ]
                ]);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to add favorite',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove item from favorites
     */
    public function removeFavorite(Request $request, $favoriteId)
    {
        try {
            $user = Auth::user();
            
            // Check if it's an attraction interaction ID first
            $attractionInteraction = UserAttractionInteraction::where('id', $favoriteId)
                ->where('user_id', $user->id)
                ->where('is_active', true)
                ->first();

            if ($attractionInteraction) {
                // Remove attraction favorite
                $attractionInteraction->update(['is_active' => false]);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Attraction removed from favorites'
                ]);
            }

            // Otherwise, handle traditional favorite
            $favorite = Favorite::where('id', $favoriteId)
                ->where('user_id', $user->id)
                ->first();

            if (!$favorite) {
                return response()->json([
                    'success' => false,
                    'message' => 'Favorite not found'
                ], 404);
            }

            $favorite->delete();

            return response()->json([
                'success' => true,
                'message' => 'Removed from favorites'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove favorite',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user's written reviews
     */
    public function reviews(Request $request)
    {
        try {
            $user = Auth::user();
            $type = $request->input('type'); // 'business', 'offering', or null for all
            $page = $request->input('page', 1);
            $limit = $request->input('limit', 20);

            $query = Review::where('user_id', $user->id)
                ->with(['reviewable']);

            // Filter by type if specified
            if ($type === 'business') {
                $query->where('reviewable_type', 'App\\Models\\Business');
            } elseif ($type === 'offering') {
                $query->where('reviewable_type', 'App\\Models\\BusinessOffering');
            }

            $reviews = $query->orderBy('created_at', 'desc')
                ->paginate($limit, ['*'], 'page', $page);

            // Map reviews to lightweight format
            $reviewsData = $reviews->getCollection()->map(function($review) {
                $item = $review->reviewable;
                if (!$item) return null;

                $baseReview = [
                    'id' => $review->id,
                    'overall_rating' => $review->overall_rating,
                    'title' => $review->title,
                    'review_text' => $review->review_text,
                    'is_recommended' => $review->is_recommended,
                    'helpful_count' => $review->helpful_count,
                    'not_helpful_count' => $review->not_helpful_count,
                    'status' => $review->status,
                    'created_at' => $review->created_at->format('Y-m-d H:i:s'),
                ];

                if ($review->reviewable_type === 'App\\Models\\Business') {
                    $baseReview['type'] = 'business';
                    $baseReview['business'] = [
                        'id' => $item->id,
                        'business_name' => $item->business_name,
                        'slug' => $item->slug,
                        'category_name' => $item->category->name ?? null,
                        'logo_image' => $item->logoImage->image_url ?? null,
                    ];
                } elseif ($review->reviewable_type === 'App\\Models\\BusinessOffering') {
                    $baseReview['type'] = 'offering';
                    $baseReview['offering'] = [
                        'id' => $item->id,
                        'name' => $item->name,
                        'offering_type' => $item->offering_type,
                        'business_name' => $item->business->business_name ?? null,
                        'image_url' => $item->image_url,
                    ];
                }

                return $baseReview;
            })->filter();

            return response()->json([
                'success' => true,
                'data' => [
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
     * Get user's points and history
     */
    public function points(Request $request)
    {
        try {
            $user = Auth::user();
            $type = $request->input('type'); // 'review', 'helpful_vote', 'referral', or null for all
            $page = $request->input('page', 1);
            $limit = $request->input('limit', 20);

            // Get total points
            $totalPoints = $user->total_points ?? 0;

            // Get points history
            $query = UserPoint::where('user_id', $user->id);

            if ($type) {
                $query->byType($type);
            }

            $pointsHistory = $query->orderBy('created_at', 'desc')
                ->paginate($limit, ['*'], 'page', $page);

            // Get points summary by type
            $pointsSummary = UserPoint::where('user_id', $user->id)
                ->selectRaw('point_type, SUM(points) as total_points, COUNT(*) as total_activities')
                ->groupBy('point_type')
                ->get()
                ->mapWithKeys(function($item) {
                    return [$item->point_type => [
                        'total_points' => (int) $item->total_points,
                        'total_activities' => (int) $item->total_activities
                    ]];
                });

            // Map points history to lightweight format
            $historyData = $pointsHistory->getCollection()->map(function($point) {
                return [
                    'id' => $point->id,
                    'points' => $point->points,
                    'point_type' => $point->point_type,
                    'description' => $point->description,
                    'earned_at' => $point->created_at->format('Y-m-d H:i:s'),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'total_points' => $totalPoints,
                    'points_summary' => $pointsSummary,
                    'recent_activities' => $historyData,
                    'pagination' => [
                        'current_page' => $pointsHistory->currentPage(),
                        'last_page' => $pointsHistory->lastPage(),
                        'per_page' => $pointsHistory->perPage(),
                        'total' => $pointsHistory->total(),
                        'has_more' => $pointsHistory->hasMorePages()
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch points',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
