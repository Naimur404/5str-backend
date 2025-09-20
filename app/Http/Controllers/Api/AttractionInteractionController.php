<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attraction;
use App\Models\UserAttractionInteraction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class AttractionInteractionController extends Controller
{
    /**
     * Toggle like/unlike an attraction
     */
    public function toggleLike(Request $request, $attractionId)
    {
        try {
            $attraction = Attraction::active()->findOrFail($attractionId);
            $userId = Auth::id();

            $result = UserAttractionInteraction::toggleInteraction(
                $userId, 
                $attractionId, 
                UserAttractionInteraction::TYPE_LIKE
            );

            return response()->json([
                'success' => true,
                'message' => $result['action'] === 'created' ? 'Attraction liked' : 'Like removed',
                'data' => [
                    'action' => $result['action'],
                    'is_liked' => $result['action'] === 'created',
                    'total_likes' => $attraction->fresh()->total_likes
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to toggle like',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Toggle dislike an attraction
     */
    public function toggleDislike(Request $request, $attractionId)
    {
        try {
            $attraction = Attraction::active()->findOrFail($attractionId);
            $userId = Auth::id();

            $result = UserAttractionInteraction::toggleInteraction(
                $userId, 
                $attractionId, 
                UserAttractionInteraction::TYPE_DISLIKE
            );

            return response()->json([
                'success' => true,
                'message' => $result['action'] === 'created' ? 'Attraction disliked' : 'Dislike removed',
                'data' => [
                    'action' => $result['action'],
                    'is_disliked' => $result['action'] === 'created',
                    'total_dislikes' => $attraction->fresh()->total_dislikes
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to toggle dislike',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Toggle bookmark an attraction
     */
    public function toggleBookmark(Request $request, $attractionId)
    {
        try {
            $attraction = Attraction::active()->findOrFail($attractionId);
            $userId = Auth::id();

            $result = UserAttractionInteraction::toggleInteraction(
                $userId, 
                $attractionId, 
                UserAttractionInteraction::TYPE_BOOKMARK,
                [
                    'notes' => $request->notes,
                    'is_public' => $request->is_public ?? true
                ]
            );

            return response()->json([
                'success' => true,
                'message' => $result['action'] === 'created' ? 'Attraction bookmarked' : 'Bookmark removed',
                'data' => [
                    'action' => $result['action'],
                    'is_bookmarked' => $result['action'] === 'created',
                    'interaction' => $result['interaction']
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to toggle bookmark',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Share an attraction
     */
    public function share(Request $request, $attractionId)
    {
        $validator = Validator::make($request->all(), [
            'platform' => 'nullable|string|in:facebook,twitter,instagram,whatsapp,email,sms,copy_link',
            'message' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $attraction = Attraction::active()->findOrFail($attractionId);
            $userId = Auth::id();

            $shareData = [
                'platform' => $request->platform,
                'message' => $request->message,
                'shared_at' => now(),
                'user_agent' => $request->userAgent(),
                'ip_address' => $request->ip()
            ];

            $interaction = UserAttractionInteraction::createOrUpdate(
                $userId, 
                $attractionId, 
                UserAttractionInteraction::TYPE_SHARE,
                [
                    'interaction_data' => $shareData,
                    'is_public' => true
                ]
            );

            // Generate share URLs
            $shareUrls = $this->generateShareUrls($attraction, $request->message);

            return response()->json([
                'success' => true,
                'message' => 'Attraction shared successfully',
                'data' => [
                    'interaction' => $interaction,
                    'share_urls' => $shareUrls,
                    'total_shares' => $attraction->fresh()->total_shares
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to share attraction',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark attraction as visited (been there)
     */
    public function markVisited(Request $request, $attractionId)
    {
        $validator = Validator::make($request->all(), [
            'visit_date' => 'nullable|date|before_or_equal:today',
            'rating' => 'nullable|numeric|min:0|max:5',
            'notes' => 'nullable|string|max:1000',
            'companions' => 'nullable|array',
            'duration_minutes' => 'nullable|integer|min:1',
            'is_public' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $attraction = Attraction::active()->findOrFail($attractionId);
            $userId = Auth::id();

            $visitData = [
                'visit_date' => $request->visit_date ?? now(),
                'companions' => $request->companions,
                'duration_minutes' => $request->duration_minutes,
                'weather' => $request->weather,
                'photos' => $request->photos ?? []
            ];

            $interaction = UserAttractionInteraction::createOrUpdate(
                $userId, 
                $attractionId, 
                UserAttractionInteraction::TYPE_BEEN_THERE,
                [
                    'visit_info' => $visitData,
                    'notes' => $request->notes,
                    'user_rating' => $request->rating,
                    'is_public' => $request->is_public ?? true,
                    'interaction_date' => $request->visit_date ?? now()
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Visit recorded successfully',
                'data' => [
                    'interaction' => $interaction,
                    'attraction' => $attraction->fresh()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to record visit',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add to wishlist
     */
    public function addToWishlist(Request $request, $attractionId)
    {
        try {
            $attraction = Attraction::active()->findOrFail($attractionId);
            $userId = Auth::id();

            $result = UserAttractionInteraction::toggleInteraction(
                $userId, 
                $attractionId, 
                UserAttractionInteraction::TYPE_WISHLIST,
                [
                    'notes' => $request->notes,
                    'priority' => $request->priority ?? 'medium',
                    'planned_visit_date' => $request->planned_visit_date
                ]
            );

            return response()->json([
                'success' => true,
                'message' => $result['action'] === 'created' ? 'Added to wishlist' : 'Removed from wishlist',
                'data' => [
                    'action' => $result['action'],
                    'is_wishlisted' => $result['action'] === 'created',
                    'interaction' => $result['interaction']
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update wishlist',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user's interactions with attractions
     */
    public function getUserInteractions(Request $request)
    {
        try {
            $userId = Auth::id();
            $type = $request->type; // like, bookmark, wishlist, been_there, etc.
            
            $query = UserAttractionInteraction::with(['attraction.gallery', 'attraction.coverImage'])
                ->where('user_id', $userId)
                ->where('is_active', true);

            if ($type) {
                $query->byType($type);
            }

            $interactions = $query->orderBy('created_at', 'desc')
                ->paginate($request->per_page ?? 15);

            return response()->json([
                'success' => true,
                'message' => 'User interactions retrieved successfully',
                'data' => $interactions
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve interactions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get interaction statistics for an attraction
     */
    public function getInteractionStats($attractionId)
    {
        try {
            $attraction = Attraction::active()->findOrFail($attractionId);

            $stats = [
                'total_likes' => $attraction->total_likes,
                'total_dislikes' => $attraction->total_dislikes,
                'total_shares' => $attraction->total_shares,
                'total_visits' => UserAttractionInteraction::where('attraction_id', $attractionId)
                    ->byType(UserAttractionInteraction::TYPE_BEEN_THERE)->count(),
                'total_bookmarks' => UserAttractionInteraction::where('attraction_id', $attractionId)
                    ->byType(UserAttractionInteraction::TYPE_BOOKMARK)->count(),
                'total_wishlisted' => UserAttractionInteraction::where('attraction_id', $attractionId)
                    ->byType(UserAttractionInteraction::TYPE_WISHLIST)->count(),
            ];

            // Recent activity (last 30 days)
            $recentStats = [
                'recent_likes' => UserAttractionInteraction::where('attraction_id', $attractionId)
                    ->byType(UserAttractionInteraction::TYPE_LIKE)
                    ->recent(30)->count(),
                'recent_visits' => UserAttractionInteraction::where('attraction_id', $attractionId)
                    ->byType(UserAttractionInteraction::TYPE_BEEN_THERE)
                    ->recent(30)->count(),
            ];

            return response()->json([
                'success' => true,
                'message' => 'Interaction stats retrieved successfully',
                'data' => [
                    'overall' => $stats,
                    'recent' => $recentStats
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve interaction stats',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate share URLs for different platforms
     */
    private function generateShareUrls($attraction, $customMessage = null)
    {
        $url = url('/attractions/' . $attraction->id);
        $defaultMessage = "Check out {$attraction->name}! " . ($attraction->description ? substr($attraction->description, 0, 100) . '...' : '');
        $message = $customMessage ?? $defaultMessage;

        return [
            'facebook' => 'https://www.facebook.com/sharer/sharer.php?u=' . urlencode($url),
            'twitter' => 'https://twitter.com/intent/tweet?text=' . urlencode($message) . '&url=' . urlencode($url),
            'whatsapp' => 'https://wa.me/?text=' . urlencode($message . ' ' . $url),
            'email' => 'mailto:?subject=' . urlencode('Check out ' . $attraction->name) . '&body=' . urlencode($message . '\n\n' . $url),
            'direct_link' => $url
        ];
    }
}
