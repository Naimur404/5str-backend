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
     * Store a new attraction interaction
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'attraction_id' => 'required|exists:attractions,id',
            'interaction_type' => 'required|in:like,dislike,bookmark,share,visit,wishlist',
            'notes' => 'nullable|string|max:1000',
            'visit_date' => 'nullable|date|before_or_equal:today',
            'rating' => 'nullable|numeric|between:0,5',
            'platform' => 'nullable|string|max:50',
            'message' => 'nullable|string|max:500',
            'is_public' => 'nullable|boolean',
            'duration_minutes' => 'nullable|integer|min:1|max:1440',
            'companions' => 'nullable|array',
            'priority' => 'nullable|in:low,medium,high',
            'planned_visit_date' => 'nullable|date|after:today'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $attraction = Attraction::active()->findOrFail($request->attraction_id);
            $userId = Auth::id();

            // Prepare interaction data based on type
            $interactionData = [];
            
            switch ($request->interaction_type) {
                case 'share':
                    $interactionData = [
                        'platform' => $request->platform,
                        'message' => $request->message,
                        'shared_at' => now()
                    ];
                    break;
                case 'visit':
                    $interactionData = [
                        'visit_date' => $request->visit_date ?? today(),
                        'duration_minutes' => $request->duration_minutes,
                        'companions' => $request->companions ?? [],
                        'weather' => $request->weather
                    ];
                    break;
                case 'bookmark':
                case 'wishlist':
                    $interactionData = [
                        'priority' => $request->priority ?? 'medium',
                        'planned_visit_date' => $request->planned_visit_date
                    ];
                    break;
            }

            // Create interaction
            $interaction = UserAttractionInteraction::create([
                'user_id' => $userId,
                'attraction_id' => $request->attraction_id,
                'interaction_type' => $request->interaction_type,
                'interaction_data' => $interactionData,
                'notes' => $request->notes,
                'user_rating' => $request->rating,
                'is_public' => $request->is_public ?? true,
                'interaction_date' => now()
            ]);

            // Update attraction counts
            $this->updateAttractionCounts($attraction, $request->interaction_type, 'increment');

            return response()->json([
                'success' => true,
                'message' => 'Interaction recorded successfully',
                'data' => [
                    'interaction' => $interaction,
                    'attraction' => [
                        'id' => $attraction->id,
                        'name' => $attraction->name,
                        'total_likes' => $attraction->refresh()->total_likes,
                        'total_shares' => $attraction->total_shares
                    ]
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to record interaction',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Toggle interaction (like/bookmark/etc)
     */
    public function toggle(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'attraction_id' => 'required|exists:attractions,id',
            'interaction_type' => 'required|in:like,dislike,bookmark,wishlist',
            'notes' => 'nullable|string|max:1000',
            'is_public' => 'nullable|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $attraction = Attraction::active()->findOrFail($request->attraction_id);
            $userId = Auth::id();

            // Check if interaction already exists
            $existingInteraction = UserAttractionInteraction::where([
                'user_id' => $userId,
                'attraction_id' => $request->attraction_id,
                'interaction_type' => $request->interaction_type,
                'is_active' => true
            ])->first();

            if ($existingInteraction) {
                // Remove interaction
                $existingInteraction->update(['is_active' => false]);
                $this->updateAttractionCounts($attraction, $request->interaction_type, 'decrement');
                
                $action = 'removed';
                $interaction = null;
                $message = ucfirst($request->interaction_type) . ' removed';
            } else {
                // Create new interaction
                $interaction = UserAttractionInteraction::create([
                    'user_id' => $userId,
                    'attraction_id' => $request->attraction_id,
                    'interaction_type' => $request->interaction_type,
                    'notes' => $request->notes,
                    'is_public' => $request->is_public ?? true,
                    'interaction_date' => now()
                ]);
                
                $this->updateAttractionCounts($attraction, $request->interaction_type, 'increment');
                
                $action = 'created';
                $message = 'Attraction ' . $request->interaction_type . 'd';
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'action' => $action,
                    'is_liked' => $request->interaction_type === 'like' ? $action === 'created' : null,
                    'interaction' => $interaction,
                    'attraction_stats' => [
                        'total_likes' => $attraction->refresh()->total_likes,
                        'total_dislikes' => $attraction->total_dislikes
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to toggle interaction',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove specific interaction
     */
    public function remove(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'attraction_id' => 'required|exists:attractions,id',
            'interaction_type' => 'required|string'
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
            
            $interaction = UserAttractionInteraction::where([
                'user_id' => $userId,
                'attraction_id' => $request->attraction_id,
                'interaction_type' => $request->interaction_type,
                'is_active' => true
            ])->first();

            if (!$interaction) {
                return response()->json([
                    'success' => false,
                    'message' => 'Interaction not found'
                ], 404);
            }

            $interaction->update(['is_active' => false]);
            
            // Update attraction counts
            $attraction = Attraction::find($request->attraction_id);
            if ($attraction) {
                $this->updateAttractionCounts($attraction, $request->interaction_type, 'decrement');
            }

            return response()->json([
                'success' => true,
                'message' => 'Interaction removed successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove interaction',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user interactions for specific user
     */
    public function userInteractions(Request $request, $userId)
    {
        try {
            $query = UserAttractionInteraction::with(['attraction.gallery', 'attraction.coverImage'])
                ->where('user_id', $userId)
                ->where('is_active', true)
                ->orderBy('created_at', 'desc');

            if ($request->has('interaction_type')) {
                $query->where('interaction_type', $request->interaction_type);
            }

            $interactions = $query->paginate($request->per_page ?? 15);

            return response()->json([
                'success' => true,
                'message' => 'User interactions retrieved successfully',
                'data' => $interactions
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve user interactions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all interactions for specific attraction
     */
    public function attractionInteractions(Request $request, $attractionId)
    {
        try {
            $attraction = Attraction::findOrFail($attractionId);
            
            $query = UserAttractionInteraction::with(['user'])
                ->where('attraction_id', $attractionId)
                ->where('is_active', true)
                ->where('is_public', true)
                ->orderBy('created_at', 'desc');

            if ($request->has('interaction_type')) {
                $query->where('interaction_type', $request->interaction_type);
            }

            $interactions = $query->paginate($request->per_page ?? 20);

            return response()->json([
                'success' => true,
                'message' => 'Attraction interactions retrieved successfully',
                'data' => $interactions,
                'meta' => [
                    'attraction' => [
                        'id' => $attraction->id,
                        'name' => $attraction->name,
                        'total_likes' => $attraction->total_likes,
                        'total_shares' => $attraction->total_shares
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve attraction interactions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user's liked attractions
     */
    public function likedAttractions(Request $request)
    {
        try {
            $userId = Auth::id();
            
            $interactions = UserAttractionInteraction::with(['attraction.gallery', 'attraction.coverImage'])
                ->where('user_id', $userId)
                ->where('interaction_type', 'like')
                ->where('is_active', true)
                ->orderBy('created_at', 'desc')
                ->paginate($request->per_page ?? 15);

            return response()->json([
                'success' => true,
                'message' => 'Liked attractions retrieved successfully',
                'data' => $interactions
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve liked attractions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user's bookmarked attractions
     */
    public function bookmarkedAttractions(Request $request)
    {
        try {
            $userId = Auth::id();
            
            $interactions = UserAttractionInteraction::with(['attraction.gallery', 'attraction.coverImage'])
                ->where('user_id', $userId)
                ->where('interaction_type', 'bookmark')
                ->where('is_active', true)
                ->orderBy('created_at', 'desc')
                ->paginate($request->per_page ?? 15);

            return response()->json([
                'success' => true,
                'message' => 'Bookmarked attractions retrieved successfully',
                'data' => $interactions
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve bookmarked attractions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user's visited attractions
     */
    public function visitedAttractions(Request $request)
    {
        try {
            $userId = Auth::id();
            
            $interactions = UserAttractionInteraction::with(['attraction.gallery', 'attraction.coverImage'])
                ->where('user_id', $userId)
                ->where('interaction_type', 'visit')
                ->where('is_active', true)
                ->orderBy('interaction_date', 'desc')
                ->paginate($request->per_page ?? 15);

            return response()->json([
                'success' => true,
                'message' => 'Visited attractions retrieved successfully',
                'data' => $interactions
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve visited attractions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update attraction engagement counts
     */
    private function updateAttractionCounts($attraction, $interactionType, $action)
    {
        switch ($interactionType) {
            case 'like':
                if ($action === 'increment') {
                    $attraction->increment('total_likes');
                } else {
                    $attraction->decrement('total_likes');
                }
                break;
            case 'dislike':
                if ($action === 'increment') {
                    $attraction->increment('total_dislikes');
                } else {
                    $attraction->decrement('total_dislikes');
                }
                break;
            case 'share':
                if ($action === 'increment') {
                    $attraction->increment('total_shares');
                } else {
                    $attraction->decrement('total_shares');
                }
                break;
        }
    }
}