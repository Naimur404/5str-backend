<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attraction;
use App\Models\AttractionReview;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class AttractionReviewController extends Controller
{
    /**
     * Get reviews for an attraction
     */
    public function index(Request $request, $attractionId)
    {
        try {
            $attraction = Attraction::active()->findOrFail($attractionId);
            
            $query = AttractionReview::with(['user'])
                ->where('attraction_id', $attractionId)
                ->active();

            // Filter by rating
            if ($request->has('rating')) {
                $query->withRating($request->rating);
            }

            if ($request->has('min_rating')) {
                $query->minRating($request->min_rating);
            }

            // Filter by featured reviews
            if ($request->has('featured') && $request->featured) {
                $query->featured();
            }

            // Filter by verified reviews
            if ($request->has('verified') && $request->verified) {
                $query->verified();
            }

            // Filter by experience tags
            if ($request->has('experience_tag')) {
                $query->byExperienceTag($request->experience_tag);
            }

            // Sorting
            $sortBy = $request->sort_by ?? 'helpful';
            switch ($sortBy) {
                case 'newest':
                    $query->orderBy('created_at', 'desc');
                    break;
                case 'oldest':
                    $query->orderBy('created_at', 'asc');
                    break;
                case 'rating_high':
                    $query->orderBy('rating', 'desc');
                    break;
                case 'rating_low':
                    $query->orderBy('rating', 'asc');
                    break;
                case 'helpful':
                default:
                    $query->orderBy('helpful_votes', 'desc')
                          ->orderBy('created_at', 'desc');
                    break;
            }

            $perPage = min($request->per_page ?? 10, 50);
            $reviews = $query->paginate($perPage);

            // Add user vote status if authenticated
            if (Auth::check()) {
                $userId = Auth::id();
                $reviews->getCollection()->transform(function ($review) use ($userId) {
                    $userVote = $review->getUserVote($userId);
                    $review->user_vote_status = [
                        'has_voted' => $userVote ? true : false,
                        'is_upvoted' => $userVote && $userVote->is_helpful ? true : false,
                        'is_downvoted' => $userVote && !$userVote->is_helpful ? true : false,
                        'vote_details' => $userVote
                    ];
                    return $review;
                });
            }

            return response()->json([
                'success' => true,
                'message' => 'Reviews retrieved successfully',
                'data' => $reviews,
                'meta' => [
                    'attraction' => [
                        'id' => $attraction->id,
                        'name' => $attraction->name,
                        'overall_rating' => $attraction->overall_rating,
                        'total_reviews' => $attraction->total_reviews
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve reviews',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a new review
     */
    public function store(Request $request, $attractionId = null)
    {
        // Handle both route patterns: POST /attraction-reviews/{attractionId}/reviews and POST /attraction-reviews
        $attractionIdFromRoute = $attractionId;
        $attractionIdFromBody = $request->attraction_id;
        
        // Use route parameter if provided, otherwise use body parameter
        $finalAttractionId = $attractionIdFromRoute ?? $attractionIdFromBody;
        
        if (!$finalAttractionId) {
            return response()->json([
                'success' => false,
                'message' => 'Attraction ID is required',
                'errors' => ['attraction_id' => ['The attraction id field is required.']]
            ], 422);
        }

        $validator = Validator::make(array_merge($request->all(), ['attraction_id' => $finalAttractionId]), [
            'attraction_id' => 'required|exists:attractions,id',
            'rating' => 'required|numeric|min:0.5|max:5',
            'title' => 'nullable|string|max:255',
            'comment' => 'required|string|min:10|max:2000',
            'visit_date' => 'nullable|date|before_or_equal:today',
            'experience_tags' => 'nullable|array',
            'experience_tags.*' => 'string|max:50',
            'visit_info' => 'nullable|array',
            'is_anonymous' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $attraction = Attraction::active()->findOrFail($finalAttractionId);
            $userId = Auth::id();

            // Check if user has already reviewed this attraction
            $existingReview = AttractionReview::where('attraction_id', $finalAttractionId)
                ->where('user_id', $userId)
                ->first();

            if ($existingReview) {
                return response()->json([
                    'success' => false,
                    'message' => 'You have already reviewed this attraction. You can update your existing review instead.'
                ], 409);
            }

            $reviewData = [
                'attraction_id' => $finalAttractionId,
                'user_id' => $userId,
                'rating' => $request->rating,
                'title' => $request->title,
                'comment' => $request->comment,
                'visit_date' => $request->visit_date,
                'experience_tags' => $request->experience_tags ?? [],
                'visit_info' => $request->visit_info ?? [],
                'is_anonymous' => $request->is_anonymous ?? false,
                'status' => 'active'
            ];

            $review = AttractionReview::create($reviewData);

            // Load the review with user relationship
            $review->load('user');

            return response()->json([
                'success' => true,
                'message' => 'Review submitted successfully',
                'data' => $review
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit review',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show a specific review
     */
    public function show($attractionId, $reviewId)
    {
        try {
            $review = AttractionReview::with(['user', 'attraction'])
                ->where('attraction_id', $attractionId)
                ->where('id', $reviewId)
                ->active()
                ->firstOrFail();

            // Add user vote status if authenticated
            if (Auth::check()) {
                $userVote = $review->getUserVote(Auth::id());
                $review->user_vote_status = [
                    'has_voted' => $userVote ? true : false,
                    'is_upvoted' => $userVote && $userVote->is_helpful ? true : false,
                    'is_downvoted' => $userVote && !$userVote->is_helpful ? true : false,
                    'vote_details' => $userVote
                ];
            }

            return response()->json([
                'success' => true,
                'message' => 'Review retrieved successfully',
                'data' => $review
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Review not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Update a review
     */
    public function update(Request $request, $attractionId, $reviewId)
    {
        $validator = Validator::make($request->all(), [
            'rating' => 'sometimes|numeric|min:0.5|max:5',
            'title' => 'nullable|string|max:255',
            'comment' => 'sometimes|string|min:10|max:2000',
            'visit_date' => 'nullable|date|before_or_equal:today',
            'experience_tags' => 'nullable|array',
            'experience_tags.*' => 'string|max:50',
            'visit_info' => 'nullable|array',
            'is_anonymous' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $userId = Auth::id();
            
            $review = AttractionReview::where('attraction_id', $attractionId)
                ->where('id', $reviewId)
                ->where('user_id', $userId)
                ->active()
                ->firstOrFail();

            $updateData = array_filter([
                'rating' => $request->rating,
                'title' => $request->title,
                'comment' => $request->comment,
                'visit_date' => $request->visit_date,
                'experience_tags' => $request->experience_tags,
                'visit_info' => $request->visit_info,
                'is_anonymous' => $request->is_anonymous,
            ], function ($value) {
                return $value !== null;
            });

            $review->update($updateData);
            $review->load('user');

            return response()->json([
                'success' => true,
                'message' => 'Review updated successfully',
                'data' => $review
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update review',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a review
     */
    public function destroy($attractionId, $reviewId)
    {
        try {
            $userId = Auth::id();
            
            $review = AttractionReview::where('attraction_id', $attractionId)
                ->where('id', $reviewId)
                ->where('user_id', $userId)
                ->active()
                ->firstOrFail();

            $review->delete();

            return response()->json([
                'success' => true,
                'message' => 'Review deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete review',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark a review as helpful
     */
    public function markHelpful(Request $request, $attractionId, $reviewId)
    {
        try {
            $review = AttractionReview::where('attraction_id', $attractionId)
                ->where('id', $reviewId)
                ->active()
                ->firstOrFail();

            $userId = Auth::id();

            if ($review->user_id === $userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'You cannot vote on your own review'
                ], 400);
            }

            $success = $review->markAsHelpful($userId);

            if (!$success) {
                return response()->json([
                    'success' => false,
                    'message' => 'You have already voted on this review'
                ], 409);
            }

            return response()->json([
                'success' => true,
                'message' => 'Review marked as helpful',
                'data' => [
                    'helpful_votes' => $review->fresh()->helpful_votes,
                    'total_votes' => $review->fresh()->total_votes,
                    'helpful_percentage' => $review->fresh()->helpful_percentage,
                    'user_vote_status' => [
                        'has_voted' => true,
                        'is_upvoted' => true,
                        'is_downvoted' => false
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark review as helpful',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark a review as not helpful
     */
    public function markNotHelpful(Request $request, $attractionId, $reviewId)
    {
        try {
            $review = AttractionReview::where('attraction_id', $attractionId)
                ->where('id', $reviewId)
                ->active()
                ->firstOrFail();

            $userId = Auth::id();

            if ($review->user_id === $userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'You cannot vote on your own review'
                ], 400);
            }

            $success = $review->markAsNotHelpful($userId);

            if (!$success) {
                return response()->json([
                    'success' => false,
                    'message' => 'You have already voted on this review'
                ], 409);
            }

            return response()->json([
                'success' => true,
                'message' => 'Review marked as not helpful',
                'data' => [
                    'helpful_votes' => $review->fresh()->helpful_votes,
                    'total_votes' => $review->fresh()->total_votes,
                    'helpful_percentage' => $review->fresh()->helpful_percentage,
                    'user_vote_status' => [
                        'has_voted' => true,
                        'is_upvoted' => false,
                        'is_downvoted' => true
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark review as not helpful',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get review statistics for an attraction
     */
    public function getReviewStats($attractionId)
    {
        try {
            $attraction = Attraction::active()->findOrFail($attractionId);

            $ratingDistribution = AttractionReview::where('attraction_id', $attractionId)
                ->active()
                ->selectRaw('FLOOR(rating) as rating_floor, COUNT(*) as count')
                ->groupBy('rating_floor')
                ->orderBy('rating_floor', 'desc')
                ->get()
                ->pluck('count', 'rating_floor')
                ->toArray();

            // Fill missing ratings with 0
            for ($i = 1; $i <= 5; $i++) {
                if (!isset($ratingDistribution[$i])) {
                    $ratingDistribution[$i] = 0;
                }
            }
            ksort($ratingDistribution);

            $experienceTags = AttractionReview::where('attraction_id', $attractionId)
                ->active()
                ->whereNotNull('experience_tags')
                ->get()
                ->pluck('experience_tags')
                ->flatten()
                ->countBy()
                ->sortDesc()
                ->take(10);

            $monthlyStats = AttractionReview::where('attraction_id', $attractionId)
                ->active()
                ->selectRaw('YEAR(created_at) as year, MONTH(created_at) as month, COUNT(*) as count, AVG(rating) as avg_rating')
                ->groupBy('year', 'month')
                ->orderBy('year', 'desc')
                ->orderBy('month', 'desc')
                ->take(12)
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Review statistics retrieved successfully',
                'data' => [
                    'overall' => [
                        'total_reviews' => $attraction->total_reviews,
                        'overall_rating' => $attraction->overall_rating,
                        'rating_distribution' => $ratingDistribution
                    ],
                    'experience_tags' => $experienceTags,
                    'monthly_trends' => $monthlyStats
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve review statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
