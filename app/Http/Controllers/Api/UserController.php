<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Favorite;
use App\Models\UserPoint;
use App\Models\Review;
use App\Models\Business;
use App\Models\BusinessOffering;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * Get user's favorite businesses and offerings
     */
    public function favorites(Request $request)
    {
        try {
            $user = auth()->user();
            $type = $request->input('type'); // 'business', 'offering', or null for all
            $page = $request->input('page', 1);
            $limit = $request->input('limit', 20);

            $query = Favorite::where('user_id', $user->id)
                ->with(['favoritable']);

            // Filter by type if specified
            if ($type === 'business') {
                $query->where('favoritable_type', 'App\\Models\\Business');
            } elseif ($type === 'offering') {
                $query->where('favoritable_type', 'App\\Models\\BusinessOffering');
            }

            $favorites = $query->orderBy('created_at', 'desc')
                ->paginate($limit, ['*'], 'page', $page);

            // Map favorites to lightweight format
            $favoritesData = $favorites->getCollection()->map(function($favorite) {
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

            return response()->json([
                'success' => true,
                'data' => [
                    'favorites' => $favoritesData,
                    'pagination' => [
                        'current_page' => $favorites->currentPage(),
                        'last_page' => $favorites->lastPage(),
                        'per_page' => $favorites->perPage(),
                        'total' => $favorites->total(),
                        'has_more' => $favorites->hasMorePages()
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
                'favoritable_type' => 'required|in:business,offering',
                'favoritable_id' => 'required|integer|min:1'
            ]);

            $user = auth()->user();
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
                    'favorite_id' => $favorite->id
                ]
            ]);

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
            $user = auth()->user();
            
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
            $user = auth()->user();
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
            $user = auth()->user();
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
