<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\Business;
use App\Models\BusinessOffering;
use App\Models\Attraction;
use App\Models\ReviewImage;
use App\Models\Favorite;
use App\Models\ReviewHelpfulVote;
use App\Services\PushNotificationService;
use App\Support\R2Storage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class ReviewController extends Controller
{
    protected PushNotificationService $pushService;

    public function __construct(PushNotificationService $pushService)
    {
        $this->pushService = $pushService;
    }
    /**
     * Check if the given item is in user's favorites
     */
    private function checkIsFavorite($userId, $favoritable_type, $favoritable_id)
    {
        return Favorite::where('user_id', $userId)
            ->where('favoritable_type', $favoritable_type)
            ->where('favoritable_id', $favoritable_id)
            ->exists();
    }

    /**
     * Approve a review (Admin/Moderator function with push notification)
     * Example usage of the push notification service
     */
    public function approveReview($reviewId)
    {
        try {
            $review = Review::with(['user', 'reviewable'])->findOrFail($reviewId);
            
            // Check if review is already approved
            if ($review->status === 'approved') {
                return response()->json([
                    'success' => false,
                    'message' => 'Review is already approved'
                ], 400);
            }

            DB::beginTransaction();

            // Update review status
            $review->update(['status' => 'approved', 'approved_at' => now()]);

            // Get the reviewed item title for the notification
            $itemTitle = '';
            if ($review->reviewable_type === 'App\\Models\\Business') {
                $itemTitle = $review->reviewable->business_name ?? 'Business';
            } elseif ($review->reviewable_type === 'App\\Models\\BusinessOffering') {
                $itemTitle = $review->reviewable->name ?? 'Service';
            }

            // Create and save notification to database (for notification list)
            $review->user->notifications()->create([
                'title' => 'Review Approved! 🎉',
                'body' => "Your review for \"{$itemTitle}\" has been approved and is now visible to other users. Thank you for your valuable feedback!",
                'icon' => 'heroicon-o-check-circle',
                'color' => 'success',
                'type' => 'review_approved',
                'data' => [
                    'review_id' => $review->id,
                    'item_title' => $itemTitle,
                    'item_type' => $review->reviewable_type === 'App\\Models\\Business' ? 'business' : 'offering'
                ],
            ]);

            // Send push notification (for real-time alert)
            try {
                $notification = $this->pushService->createReviewApprovalNotification($itemTitle);
                $this->pushService->sendToUser($review->user, $notification);
                Log::info("Push notification sent for review approval", [
                    'review_id' => $review->id,
                    'user_id' => $review->user_id
                ]);
            } catch (\Exception $e) {
                // Log error but don't fail the approval process
                Log::error("Failed to send push notification for review approval", [
                    'review_id' => $review->id,
                    'error' => $e->getMessage()
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => '✅ Review approved successfully! User has been notified.',
                'data' => [
                    'review' => [
                        'id' => $review->id,
                        'status' => $review->status,
                        'approved_at' => $review->approved_at?->format('Y-m-d H:i:s'),
                        'user_name' => $review->user->name,
                        'item_title' => $itemTitle,
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to approve review',
                'error' => config('app.debug') ? $e->getMessage() : 'Something went wrong'
            ], 500);
        }
    }

    /**
     * Submit a new review for a business or offering
     */
    public function store(Request $request)
    {
        try {
            $request->validate([
                'reviewable_type' => 'required|in:business,offering,attraction,brand',
                'reviewable_id' => 'required|integer|min:1',
                'overall_rating' => 'required|integer|min:1|max:5',
                'title' => 'nullable|string|max:255',
                'is_recommended' => 'nullable|boolean',
                'images' => 'nullable|array|max:5',
                'images.*' => 'image|mimes:jpeg,png,jpg,webp|max:5120', // 5MB max per image
            ]);

            $user = Auth::user();
            
            $reviewableType = null;
            $reviewableItem = null;
            if ($request->reviewable_type === 'brand') {
                $reviewableItem = Business::where('business_model', 'brand')->findOrFail($request->reviewable_id);
                $reviewableType = 'App\\Models\\Business';
            } elseif ($request->reviewable_type === 'business') {
                $reviewableItem = Business::findOrFail($request->reviewable_id);
                $reviewableType = 'App\\Models\\Business';
            } elseif ($request->reviewable_type === 'offering') {
                $reviewableItem = BusinessOffering::findOrFail($request->reviewable_id);
                $reviewableType = 'App\\Models\\BusinessOffering';
            } elseif ($request->reviewable_type === 'attraction') {
                $reviewableItem = Attraction::findOrFail($request->reviewable_id);
                $reviewableType = 'App\\Models\\Attraction';
            }

            // Extract dynamic rating criteria
            $criteria = Review::resolveCriteria($request->reviewable_type, $request->reviewable_id);

            // Decode ratings input if sent as a JSON string
            $ratingsInput = $request->input('ratings');
            if (is_string($ratingsInput)) {
                $decoded = json_decode($ratingsInput, true);
                if (is_array($decoded)) {
                    $ratingsInput = $decoded;
                }
            }

            $validRatings = null;
            if (count($criteria) > 0) {
                if (empty($ratingsInput) || !is_array($ratingsInput)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Validation failed',
                        'errors' => [
                            'ratings' => ['The ratings field is required and must contain ratings for: ' . implode(', ', $criteria)]
                        ]
                    ], 422);
                }

                $validationErrors = [];
                $validRatings = [];
                foreach ($criteria as $criterion) {
                    if (!isset($ratingsInput[$criterion])) {
                        $validationErrors["ratings.{$criterion}"] = ["The {$criterion} rating is required."];
                    } else {
                        $val = $ratingsInput[$criterion];
                        if (!is_numeric($val) || $val < 1 || $val > 5) {
                            $validationErrors["ratings.{$criterion}"] = ["The {$criterion} rating must be an integer between 1 and 5."];
                        } else {
                            $validRatings[$criterion] = (int)$val;
                        }
                    }
                }

                if (count($validationErrors) > 0) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Validation failed',
                        'errors' => $validationErrors
                    ], 422);
                }
            }

            // Check if user has already reviewed this item
            $existingReview = Review::where('user_id', $user->id)
                ->where('reviewable_type', $reviewableType)
                ->where('reviewable_id', $request->reviewable_id)
                ->first();

            if ($existingReview) {
                return response()->json([
                    'success' => false,
                    'message' => 'You have already reviewed this item. You can edit your existing review instead.'
                ], 409);
            }

            DB::beginTransaction();

            // Create the review
            $review = Review::create([
                'user_id' => $user->id,
                'reviewable_type' => $reviewableType,
                'reviewable_id' => $request->reviewable_id,
                'overall_rating' => $request->overall_rating,
                'ratings' => $validRatings,
                'title' => $request->title,
                'is_recommended' => $request->is_recommended ?? true,
                'status' => 'approved', // Reviews need approval by default
            ]);

            // Handle image uploads if provided
            $uploadedImages = [];
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $image) {
                    // Generate unique filename
                    $filename = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
                    
                    // Store the image on the active default disk
                    $path = $image->storeAs('review-images', $filename, R2Storage::active());

                    // Create review image record
                    $reviewImage = ReviewImage::create([
                        'review_id' => $review->id,
                        'image_url' => R2Storage::storage()->url($path),
                        'original_filename' => $image->getClientOriginalName(),
                    ]);

                    $uploadedImages[] = [
                        'id' => $reviewImage->id,
                        'image_url' => $reviewImage->image_url,
                    ];
                }
            }

            DB::commit();

            // Check if the reviewed item is in user's favorites
            $isFavorite = $this->checkIsFavorite($user->id, $reviewableType, $request->reviewable_id);

            // Load the created review with relationships
            $review->load(['reviewable', 'images']);

            return response()->json([
                'success' => true,
                'message' => 'Review submitted successfully. It will be visible now.',
                'data' => [
                    'review' => [
                        'id' => $review->id,
                        'overall_rating' => $review->overall_rating,
                        'ratings' => $review->ratings,
                        'title' => $review->title,
                        'is_recommended' => $review->is_recommended,
                        'status' => $review->status,
                        'is_favorite' => $isFavorite,
                        'images' => $uploadedImages,
                        'created_at' => $review->created_at->format('Y-m-d H:i:s'),
                    ]
                ]
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit review',
                'error' => config('app.debug') ? $e->getMessage() : 'Something went wrong'
            ], 500);
        }
    }

    /**
     * Update an existing review
     */
    public function update(Request $request, $reviewId)
    {
        try {
            $request->validate([
                'overall_rating' => 'required|integer|min:1|max:5',
                'title' => 'nullable|string|max:255',
                'is_recommended' => 'nullable|boolean',
            ]);

            $user = Auth::user();
            
            $review = Review::where('id', $reviewId)
                ->where('user_id', $user->id)
                ->first();

            if (!$review) {
                return response()->json([
                    'success' => false,
                    'message' => 'Review not found or you do not have permission to edit it'
                ], 404);
            }

            // Check if review can be edited (only pending or rejected reviews can be edited)
            if ($review->status === 'approved') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot edit an approved review. Please contact support if you need to make changes.'
                ], 403);
            }

            // Extract dynamic rating criteria
            $criteria = Review::resolveCriteria($review->reviewable_type, $review->reviewable_id);

            // Decode ratings input if sent as a JSON string
            $ratingsInput = $request->input('ratings');
            if (is_string($ratingsInput)) {
                $decoded = json_decode($ratingsInput, true);
                if (is_array($decoded)) {
                    $ratingsInput = $decoded;
                }
            }

            $validRatings = null;
            if (count($criteria) > 0) {
                if (empty($ratingsInput) || !is_array($ratingsInput)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Validation failed',
                        'errors' => [
                            'ratings' => ['The ratings field is required and must contain ratings for: ' . implode(', ', $criteria)]
                        ]
                    ], 422);
                }

                $validationErrors = [];
                $validRatings = [];
                foreach ($criteria as $criterion) {
                    if (!isset($ratingsInput[$criterion])) {
                        $validationErrors["ratings.{$criterion}"] = ["The {$criterion} rating is required."];
                    } else {
                        $val = $ratingsInput[$criterion];
                        if (!is_numeric($val) || $val < 1 || $val > 5) {
                            $validationErrors["ratings.{$criterion}"] = ["The {$criterion} rating must be an integer between 1 and 5."];
                        } else {
                            $validRatings[$criterion] = (int)$val;
                        }
                    }
                }

                if (count($validationErrors) > 0) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Validation failed',
                        'errors' => $validationErrors
                    ], 422);
                }
            }

            // Update the review
            $review->update([
                'overall_rating' => $request->overall_rating,
                'ratings' => $validRatings,
                'title' => $request->title,
                'is_recommended' => $request->is_recommended ?? true,
                'status' => 'pending', // Reset to pending after edit
            ]);

            // Check if the reviewed item is in user's favorites
            $isFavorite = $this->checkIsFavorite($user->id, $review->reviewable_type, $review->reviewable_id);

            return response()->json([
                'success' => true,
                'message' => 'Review updated successfully. It will be re-reviewed for approval.',
                'data' => [
                    'review' => [
                        'id' => $review->id,
                        'overall_rating' => $review->overall_rating,
                        'ratings' => $review->ratings,
                        'title' => $review->title,
                        'is_recommended' => $review->is_recommended,
                        'status' => $review->status,
                        'is_favorite' => $isFavorite,
                        'updated_at' => $review->updated_at->format('Y-m-d H:i:s'),
                    ]
                ]
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update review',
                'error' => config('app.debug') ? $e->getMessage() : 'Something went wrong'
            ], 500);
        }
    }

    /**
     * Delete a review
     */
    public function destroy($reviewId)
    {
        try {
            $user = Auth::user();
            
            $review = Review::where('id', $reviewId)
                ->where('user_id', $user->id)
                ->first();

            if (!$review) {
                return response()->json([
                    'success' => false,
                    'message' => 'Review not found or you do not have permission to delete it'
                ], 404);
            }

            // Delete associated images
            foreach ($review->images as $image) {
                R2Storage::delete($image->image_url);
                $image->delete();
            }

            // Delete the review
            $review->delete();

            return response()->json([
                'success' => true,
                'message' => 'Review deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete review',
                'error' => config('app.debug') ? $e->getMessage() : 'Something went wrong'
            ], 500);
        }
    }

    /**
     * Submit a new review for a specific business
     */
    public function storeBusinessReview(Request $request, $businessId)
    {
        try {
            $request->validate([
                'overall_rating' => 'required|integer|min:1|max:5',
                'title' => 'nullable|string|max:255',
                'is_recommended' => 'nullable|boolean',
                'images' => 'nullable|array|max:5',
                'images.*' => 'image|mimes:jpeg,png,jpg,webp|max:5120', // 5MB max per image
            ]);

            // Verify business exists
            $business = Business::findOrFail($businessId);
            
            // Set the reviewable data and call the main store method
            $request->merge([
                'reviewable_type' => 'business',
                'reviewable_id' => $businessId
            ]);

            return $this->store($request);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit business review',
                'error' => config('app.debug') ? $e->getMessage() : 'Something went wrong'
            ], 500);
        }
    }

    /**
     * Submit a new review for a specific offering
     */
    public function storeOfferingReview(Request $request, $businessId, $offeringId)
    {
        try {
            $request->validate([
                'overall_rating' => 'required|integer|min:1|max:5',
                'title' => 'nullable|string|max:255',
                'is_recommended' => 'nullable|boolean',
                'images' => 'nullable|array|max:5',
                'images.*' => 'image|mimes:jpeg,png,jpg,webp|max:5120', // 5MB max per image
            ]);

            // Verify business and offering exist and are related
            $business = Business::findOrFail($businessId);
            $offering = BusinessOffering::where('id', $offeringId)
                ->where('business_id', $businessId)
                ->firstOrFail();
            
            // Set the reviewable data and call the main store method
            $request->merge([
                'reviewable_type' => 'offering',
                'reviewable_id' => $offeringId
            ]);

            return $this->store($request);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit offering review',
                'error' => config('app.debug') ? $e->getMessage() : 'Something went wrong'
            ], 500);
        }
    }

    /**
     * Get a specific review details
     */
    public function show($reviewId)
    {
        try {
            $user = Auth::user();
            
            $review = Review::where('id', $reviewId)
                ->where('user_id', $user->id)
                ->with(['reviewable', 'images'])
                ->first();

            if (!$review) {
                return response()->json([
                    'success' => false,
                    'message' => 'Review not found or you do not have permission to view it'
                ], 404);
            }

            $reviewableData = null;
            if ($review->reviewable_type === 'App\\Models\\Business') {
                $item = $review->reviewable;
                $reviewableData = [
                    'type' => ($item && $item->business_model === 'brand') ? 'brand' : 'business',
                    'id' => $item->id ?? null,
                    'business_name' => $item->business_name ?? null,
                    'slug' => $item->slug ?? null,
                    'category_name' => $item->category->name ?? null,
                    'logo_image' => $item->logoImage->image_url ?? null,
                ];
            } elseif ($review->reviewable_type === 'App\\Models\\BusinessOffering') {
                $item = $review->reviewable;
                $reviewableData = [
                    'type' => 'offering',
                    'id' => $item->id ?? null,
                    'name' => $item->name ?? null,
                    'offering_type' => $item->offering_type ?? null,
                    'business_name' => $item->business->business_name ?? null,
                    'image_url' => $item->image_url ?? null,
                ];
            } elseif ($review->reviewable_type === 'App\\Models\\Attraction') {
                $item = $review->reviewable;
                $reviewableData = [
                    'type' => 'attraction',
                    'id' => $item->id ?? null,
                    'name' => $item->name ?? null,
                    'slug' => $item->slug ?? null,
                    'image_url' => $item->cover_image_url ?? null,
                ];
            }

            $images = $review->images->map(function($image) {
                return [
                    'id' => $image->id,
                    'image_url' => $image->image_url,
                    'original_filename' => $image->original_filename,
                ];
            });

            // Check if the reviewed item is in user's favorites
            $isFavorite = $this->checkIsFavorite($user->id, $review->reviewable_type, $review->reviewable_id);

            return response()->json([
                'success' => true,
                'data' => [
                    'review' => [
                        'id' => $review->id,
                        'overall_rating' => $review->overall_rating,
                        'ratings' => $review->ratings,
                        'title' => $review->title,
                        'is_recommended' => $review->is_recommended,
                        'helpful_count' => $review->helpful_count,
                        'not_helpful_count' => $review->not_helpful_count,
                        'status' => $review->status,
                        'is_favorite' => $isFavorite,
                        'images' => $images,
                        'reviewable' => $reviewableData,
                        'created_at' => $review->created_at->format('Y-m-d H:i:s'),
                        'updated_at' => $review->updated_at->format('Y-m-d H:i:s'),
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch review',
                'error' => config('app.debug') ? $e->getMessage() : 'Something went wrong'
            ], 500);
        }
    }

      public function showFull($reviewId)
    {
        try {
            
            $review = Review::where('id', $reviewId)
                ->with(['reviewable', 'images'])
                ->first();

            if (!$review) {
                return response()->json([
                    'success' => false,
                    'message' => 'Review not found or you do not have permission to view it'
                ], 404);
            }

            $reviewableData = null;
            if ($review->reviewable_type === 'App\\Models\\Business') {
                $item = $review->reviewable;
                $reviewableData = [
                    'type' => ($item && $item->business_model === 'brand') ? 'brand' : 'business',
                    'id' => $item->id ?? null,
                    'business_name' => $item->business_name ?? null,
                    'slug' => $item->slug ?? null,
                    'category_name' => $item->category->name ?? null,
                    'logo_image' => $item->logoImage->image_url ?? null,
                ];
            } elseif ($review->reviewable_type === 'App\\Models\\BusinessOffering') {
                $item = $review->reviewable;
                $reviewableData = [
                    'type' => 'offering',
                    'id' => $item->id ?? null,
                    'name' => $item->name ?? null,
                    'offering_type' => $item->offering_type ?? null,
                    'business_name' => $item->business->business_name ?? null,
                    'image_url' => $item->image_url ?? null,
                ];
            } elseif ($review->reviewable_type === 'App\\Models\\Attraction') {
                $item = $review->reviewable;
                $reviewableData = [
                    'type' => 'attraction',
                    'id' => $item->id ?? null,
                    'name' => $item->name ?? null,
                    'slug' => $item->slug ?? null,
                    'image_url' => $item->cover_image_url ?? null,
                ];
            }

            $images = $review->images->map(function($image) {
                return [
                    'id' => $image->id,
                    'image_url' => $image->image_url,
                    'original_filename' => $image->original_filename,
                ];
            });

            // Check if the reviewed item is in user's favorites

            return response()->json([
                'success' => true,
                'data' => [
                    'review' => [
                        'id' => $review->id,
                        'overall_rating' => $review->overall_rating,
                        'ratings' => $review->ratings,
                        'title' => $review->title,
                        'is_recommended' => $review->is_recommended,
                        'helpful_count' => $review->helpful_count,
                        'not_helpful_count' => $review->not_helpful_count,
                        'status' => $review->status,
                        'images' => $images,
                        'reviewable' => $reviewableData,
                        'created_at' => $review->created_at->format('Y-m-d H:i:s'),
                        'updated_at' => $review->updated_at->format('Y-m-d H:i:s'),
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch review',
                'error' => config('app.debug') ? $e->getMessage() : 'Something went wrong'
            ], 500);
        }
    }

    /**
     * Vote on review helpfulness
     */
    public function voteHelpful(Request $request, $reviewId)
    {
        try {
            $request->validate([
                'is_helpful' => 'required|boolean'
            ]);

            $user = Auth::user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required'
                ], 401);
            }

            $review = Review::findOrFail($reviewId);

            // Check if user is trying to vote on their own review
            if ($review->user_id === $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You cannot vote on your own review'
                ], 400);
            }

            DB::beginTransaction();

            // Debug: Log initial state
            $initialHelpfulCount = ReviewHelpfulVote::where('review_id', $reviewId)
                ->where('is_helpful', true)
                ->count();
            
            Log::info("Initial helpful count for review {$reviewId}: {$initialHelpfulCount}");

            // Check if user has already voted on this review
            $existingVote = ReviewHelpfulVote::where('review_id', $reviewId)
                ->where('user_id', $user->id)
                ->first();

            $message = '';
            if ($existingVote) {
                // Update existing vote if different
                if ($existingVote->is_helpful !== $request->is_helpful) {
                    $existingVote->update(['is_helpful' => $request->is_helpful]);
                    $message = 'Vote updated successfully';
                    Log::info("Updated vote for user {$user->id} on review {$reviewId}");
                } else {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'You have already voted this way on this review'
                    ], 400);
                }
            } else {
                // Create new vote
                ReviewHelpfulVote::create([
                    'review_id' => $reviewId,
                    'user_id' => $user->id,
                    'is_helpful' => $request->is_helpful
                ]);
                $message = 'Vote recorded successfully';
                Log::info("Created new vote for user {$user->id} on review {$reviewId}");
            }

            // Recalculate helpful counts
            $helpfulCount = ReviewHelpfulVote::where('review_id', $reviewId)
                ->where('is_helpful', true)
                ->count();
            
            $notHelpfulCount = ReviewHelpfulVote::where('review_id', $reviewId)
                ->where('is_helpful', false)
                ->count();

            Log::info("Recalculated counts for review {$reviewId}: helpful={$helpfulCount}, not_helpful={$notHelpfulCount}");

            // Update review helpful counts
            $review->update([
                'helpful_count' => $helpfulCount,
                'not_helpful_count' => $notHelpfulCount
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'review_id' => $reviewId,
                    'user_vote' => $request->is_helpful,
                    'helpful_count' => $helpfulCount,
                    'not_helpful_count' => $notHelpfulCount,
                    'total_votes' => $helpfulCount + $notHelpfulCount,
                    'debug' => [
                        'initial_count' => $initialHelpfulCount,
                        'final_count' => $helpfulCount,
                        'user_id' => $user->id,
                        'had_existing_vote' => $existingVote !== null
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to record vote',
                'error' => config('app.debug') ? $e->getMessage() : 'Something went wrong'
            ], 500);
        }
    }

    /**
     * Remove vote from review
     */
    public function removeVote(Request $request, $reviewId)
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required'
                ], 401);
            }

            $review = Review::findOrFail($reviewId);

            $existingVote = ReviewHelpfulVote::where('review_id', $reviewId)
                ->where('user_id', $user->id)
                ->first();

            if (!$existingVote) {
                return response()->json([
                    'success' => false,
                    'message' => 'No vote found to remove'
                ], 404);
            }

            DB::beginTransaction();

            // Remove the vote
            $existingVote->delete();

            // Recalculate helpful counts
            $helpfulCount = ReviewHelpfulVote::where('review_id', $reviewId)
                ->where('is_helpful', true)
                ->count();
            
            $notHelpfulCount = ReviewHelpfulVote::where('review_id', $reviewId)
                ->where('is_helpful', false)
                ->count();

            // Update review helpful counts
            $review->update([
                'helpful_count' => $helpfulCount,
                'not_helpful_count' => $notHelpfulCount
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Vote removed successfully',
                'data' => [
                    'review_id' => $reviewId,
                    'helpful_count' => $helpfulCount,
                    'not_helpful_count' => $notHelpfulCount,
                    'total_votes' => $helpfulCount + $notHelpfulCount
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove vote',
                'error' => config('app.debug') ? $e->getMessage() : 'Something went wrong'
            ], 500);
        }
    }

    /**
     * Get user's vote status for a review
     */
    public function getVoteStatus(Request $request, $reviewId)
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required'
                ], 401);
            }

            $review = Review::findOrFail($reviewId);

            $userVote = ReviewHelpfulVote::where('review_id', $reviewId)
                ->where('user_id', $user->id)
                ->first();

            return response()->json([
                'success' => true,
                'data' => [
                    'review_id' => $reviewId,
                    'has_voted' => $userVote !== null,
                    'user_vote' => $userVote ? $userVote->is_helpful : null,
                    'helpful_count' => $review->helpful_count,
                    'not_helpful_count' => $review->not_helpful_count,
                    'total_votes' => $review->helpful_count + $review->not_helpful_count
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get vote status',
                'error' => config('app.debug') ? $e->getMessage() : 'Something went wrong'
            ], 500);
        }
    }
}
