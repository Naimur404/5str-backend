<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attraction;
use App\Models\UserAttractionInteraction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AttractionController extends Controller
{
    /**
     * Display a listing of attractions with optional filters
     */
    public function index(Request $request)
    {
        try {
            $query = Attraction::active()->with(['gallery', 'coverImage']);
            
            // Location-based filtering
            if ($request->has('latitude') && $request->has('longitude')) {
                $latitude = $request->latitude;
                $longitude = $request->longitude;
                $radius = $request->radius ?? 10; // Default 10km radius
                
                $query->nearbyWithDistance($latitude, $longitude, $radius);
                
                // Update discovery scores based on user location
                $query->get()->each(function ($attraction) use ($latitude, $longitude) {
                    $attraction->updateDiscoveryScore($latitude, $longitude);
                });
            }
            
            // Filter by type
            if ($request->has('type')) {
                $query->byType($request->type);
            }
            
            // Filter by category
            if ($request->has('category')) {
                $query->byCategory($request->category);
            }
            
            // Filter by city
            if ($request->has('city')) {
                $query->inCity($request->city);
            }
            
            // Filter by area
            if ($request->has('area')) {
                $query->inArea($request->area);
            }
            
            // Filter by free/paid
            if ($request->has('is_free')) {
                if ($request->is_free == 'true' || $request->is_free == 1) {
                    $query->free();
                } else {
                    $query->paid();
                }
            }
            
            // Filter by rating
            if ($request->has('min_rating')) {
                $query->withRating($request->min_rating);
            }
            
            // Filter by difficulty
            if ($request->has('difficulty')) {
                $query->byDifficulty($request->difficulty);
            }
            
            // Featured attractions
            if ($request->has('featured') && $request->featured) {
                $query->featured();
            }
            
            // Verified attractions
            if ($request->has('verified') && $request->verified) {
                $query->verified();
            }
            
            // Search by name or description
            if ($request->has('search')) {
                $searchTerm = $request->search;
                $query->where(function($q) use ($searchTerm) {
                    $q->where('name', 'LIKE', '%' . $searchTerm . '%')
                      ->orWhere('description', 'LIKE', '%' . $searchTerm . '%');
                });
            }
            
            // Sorting
            $sortBy = $request->sort_by ?? 'discovery_score';
            $sortOrder = $request->sort_order ?? 'desc';
            
            switch ($sortBy) {
                case 'distance':
                    // Already sorted by distance if location provided
                    break;
                case 'rating':
                    $query->orderBy('overall_rating', $sortOrder);
                    break;
                case 'reviews':
                    $query->orderBy('total_reviews', $sortOrder);
                    break;
                case 'likes':
                    $query->orderBy('total_likes', $sortOrder);
                    break;
                case 'newest':
                    $query->orderBy('created_at', 'desc');
                    break;
                case 'name':
                    $query->orderBy('name', $sortOrder);
                    break;
                default:
                    $query->orderBy('discovery_score', 'desc');
                    break;
            }
            
            // Pagination
            $perPage = min($request->per_page ?? 15, 50); // Max 50 items per page
            $attractions = $query->paginate($perPage);
            
            // Add user interaction data if authenticated
            if (Auth::check()) {
                $userId = Auth::id();
                $attractions->getCollection()->transform(function ($attraction) use ($userId) {
                    $attraction->user_interactions = UserAttractionInteraction::getUserInteractionTypes($userId, $attraction->id);
                    $attraction->user_has_liked = UserAttractionInteraction::hasUserInteraction($userId, $attraction->id, 'like');
                    $attraction->user_has_bookmarked = UserAttractionInteraction::hasUserInteraction($userId, $attraction->id, 'bookmark');
                    return $attraction;
                });
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Attractions retrieved successfully',
                'data' => $attractions,
                'meta' => [
                    'total_count' => $attractions->total(),
                    'current_page' => $attractions->currentPage(),
                    'per_page' => $attractions->perPage(),
                    'last_page' => $attractions->lastPage(),
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve attractions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get nearby attractions based on user's current location
     */
    public function nearby(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'radius' => 'nullable|numeric|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $latitude = $request->latitude;
            $longitude = $request->longitude;
            $radius = $request->radius ?? 10;
            $limit = min($request->limit ?? 20, 50);

            $attractions = Attraction::active()
                ->with(['gallery', 'coverImage'])
                ->nearbyWithDistance($latitude, $longitude, $radius)
                ->take($limit)
                ->get();

            // Update discovery scores and add user interaction data
            if (Auth::check()) {
                $userId = Auth::id();
                $attractions->transform(function ($attraction) use ($latitude, $longitude, $userId) {
                    $attraction->updateDiscoveryScore($latitude, $longitude);
                    $attraction->user_interactions = UserAttractionInteraction::getUserInteractionTypes($userId, $attraction->id);
                    $attraction->user_has_liked = UserAttractionInteraction::hasUserInteraction($userId, $attraction->id, 'like');
                    $attraction->user_has_bookmarked = UserAttractionInteraction::hasUserInteraction($userId, $attraction->id, 'bookmark');
                    return $attraction;
                });
            } else {
                $attractions->each(function ($attraction) use ($latitude, $longitude) {
                    $attraction->updateDiscoveryScore($latitude, $longitude);
                });
            }

            return response()->json([
                'success' => true,
                'message' => 'Nearby attractions retrieved successfully',
                'data' => $attractions,
                'meta' => [
                    'search_location' => [
                        'latitude' => $latitude,
                        'longitude' => $longitude,
                        'radius_km' => $radius
                    ],
                    'total_count' => $attractions->count()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve nearby attractions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified attraction
     */
    public function show(Request $request, $id)
    {
        try {
            $attraction = Attraction::active()
                ->with(['gallery.uploader', 'reviews.user', 'creator', 'verifier'])
                ->findOrFail($id);

            // Increment view count
            $attraction->incrementViews();

            // Track user view if authenticated
            if (Auth::check()) {
                $userId = Auth::id();
                
                // Add user interaction data
                $attraction->user_interactions = UserAttractionInteraction::getUserInteractionTypes($userId, $attraction->id);
                $attraction->user_has_liked = UserAttractionInteraction::hasUserInteraction($userId, $attraction->id, 'like');
                $attraction->user_has_bookmarked = UserAttractionInteraction::hasUserInteraction($userId, $attraction->id, 'bookmark');
                $attraction->user_has_visited = UserAttractionInteraction::hasUserInteraction($userId, $attraction->id, 'been_there');
                
                // Create view interaction
                UserAttractionInteraction::createOrUpdate($userId, $attraction->id, 'visit', [
                    'interaction_data' => [
                        'user_agent' => $request->userAgent(),
                        'ip_address' => $request->ip(),
                        'viewed_at' => now()
                    ]
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Attraction retrieved successfully',
                'data' => $attraction
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Attraction not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Get featured attractions
     */
    public function featured(Request $request)
    {
        try {
            $limit = min($request->limit ?? 10, 20);
            
            $attractions = Attraction::active()
                ->featured()
                ->with(['gallery', 'coverImage'])
                ->orderBy('discovery_score', 'desc')
                ->take($limit)
                ->get();

            // Add user interaction data if authenticated
            if (Auth::check()) {
                $userId = Auth::id();
                $attractions->transform(function ($attraction) use ($userId) {
                    $attraction->user_interactions = UserAttractionInteraction::getUserInteractionTypes($userId, $attraction->id);
                    $attraction->user_has_liked = UserAttractionInteraction::hasUserInteraction($userId, $attraction->id, 'like');
                    $attraction->user_has_bookmarked = UserAttractionInteraction::hasUserInteraction($userId, $attraction->id, 'bookmark');
                    return $attraction;
                });
            }

            return response()->json([
                'success' => true,
                'message' => 'Featured attractions retrieved successfully',
                'data' => $attractions,
                'meta' => [
                    'total_count' => $attractions->count()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve featured attractions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get attraction categories and types
     */
    public function categories(Request $request)
    {
        try {
            $categories = DB::table('attractions')
                ->select('category', DB::raw('count(*) as count'))
                ->where('is_active', true)
                ->where('status', 'active')
                ->whereNotNull('category')
                ->groupBy('category')
                ->orderBy('count', 'desc')
                ->get();

            $types = DB::table('attractions')
                ->select('type', DB::raw('count(*) as count'))
                ->where('is_active', true)
                ->where('status', 'active')
                ->groupBy('type')
                ->orderBy('count', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Categories and types retrieved successfully',
                'data' => [
                    'categories' => $categories,
                    'types' => $types
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve categories',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get popular destinations (cities/areas)
     */
    public function destinations(Request $request)
    {
        try {
            $cities = DB::table('attractions')
                ->select('city', DB::raw('count(*) as attractions_count'))
                ->where('is_active', true)
                ->where('status', 'active')
                ->whereNotNull('city')
                ->groupBy('city')
                ->orderBy('attractions_count', 'desc')
                ->limit(20)
                ->get();

            $areas = DB::table('attractions')
                ->select('area', 'city', DB::raw('count(*) as attractions_count'))
                ->where('is_active', true)
                ->where('status', 'active')
                ->whereNotNull('area')
                ->groupBy('area', 'city')
                ->orderBy('attractions_count', 'desc')
                ->limit(30)
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Popular destinations retrieved successfully',
                'data' => [
                    'cities' => $cities,
                    'areas' => $areas
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve destinations',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
