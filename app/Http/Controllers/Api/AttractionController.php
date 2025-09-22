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
            $userInteractions = [];
            if (Auth::check()) {
                $userId = Auth::id();
                
                // Add user interaction data
                $userInteractions = [
                    'user_interactions' => UserAttractionInteraction::getUserInteractionTypes($userId, $attraction->id),
                    'user_has_liked' => UserAttractionInteraction::hasUserInteraction($userId, $attraction->id, 'like'),
                    'user_has_bookmarked' => UserAttractionInteraction::hasUserInteraction($userId, $attraction->id, 'bookmark'),
                    'user_has_visited' => UserAttractionInteraction::hasUserInteraction($userId, $attraction->id, 'been_there'),
                ];
                
                // Create view interaction
                UserAttractionInteraction::createOrUpdate($userId, $attraction->id, 'visit', [
                    'interaction_data' => [
                        'user_agent' => $request->userAgent(),
                        'ip_address' => $request->ip(),
                        'viewed_at' => now()
                    ]
                ]);
            }

            // Format the response with proper JSON structure
            $formattedData = [
                'id' => $attraction->id,
                'name' => $attraction->name,
                'slug' => $attraction->slug,
                'description' => $attraction->description,
                'type' => $attraction->type,
                'category' => $attraction->category,
                'subcategory' => $attraction->subcategory,
                'location' => [
                    'latitude' => (float) $attraction->latitude,
                    'longitude' => (float) $attraction->longitude,
                    'address' => $attraction->address,
                    'city' => $attraction->city,
                    'area' => $attraction->area,
                    'district' => $attraction->district,
                    'country' => $attraction->country,
                ],
                'pricing' => [
                    'is_free' => $attraction->is_free,
                    'entry_fee' => (float) $attraction->entry_fee,
                    'currency' => $attraction->currency,
                ],
                'schedule' => [
                    'opening_hours' => $this->parseJsonField($attraction->opening_hours),
                ],
                'contact' => $this->parseJsonField($attraction->contact_info),
                'visit_info' => [
                    'facilities' => $this->parseJsonField($attraction->facilities),
                    'best_time_to_visit' => $this->parseJsonField($attraction->best_time_to_visit),
                    'estimated_duration_minutes' => $attraction->estimated_duration_minutes,
                    'difficulty_level' => $attraction->difficulty_level,
                ],
                'accessibility' => $this->parseJsonField($attraction->accessibility_info),
                'ratings' => [
                    'overall_rating' => (float) $attraction->overall_rating,
                    'total_reviews' => $attraction->total_reviews,
                ],
                'engagement' => [
                    'total_likes' => $attraction->total_likes,
                    'total_dislikes' => $attraction->total_dislikes,
                    'total_shares' => $attraction->total_shares,
                    'total_views' => $attraction->total_views,
                ],
                'media' => [
                    'cover_image_url' => $attraction->cover_image_url,
                    'gallery_count' => $attraction->gallery_count,
                    'gallery' => $attraction->gallery->map(function ($image) {
                        return [
                            'id' => $image->id,
                            'image_url' => $image->image_url,
                            'title' => $image->title,
                            'description' => $image->description,
                            'is_cover' => $image->is_cover,
                            'sort_order' => $image->sort_order,
                            'full_image_url' => $image->full_image_url,
                            'thumbnail_url' => $image->thumbnail_url,
                        ];
                    }),
                ],
                'status_flags' => [
                    'is_verified' => $attraction->is_verified,
                    'is_featured' => $attraction->is_featured,
                    'is_active' => $attraction->is_active,
                    'status' => $attraction->status,
                ],
                'discovery_score' => (float) $attraction->discovery_score,
                'google_maps_url' => $attraction->google_maps_url,
                'free_maps' => $this->generateFreeMapsData($attraction),
                'meta_data' => $this->parseJsonField($attraction->meta_data),
                'reviews' => $attraction->reviews->map(function ($review) {
                    return [
                        'id' => $review->id,
                        'user' => [
                            'id' => $review->user->id,
                            'name' => $review->user->name,
                            'profile_image' => $review->user->profile_image,
                            'trust_level' => $review->user->trust_level,
                        ],
                        'rating' => (float) $review->rating,
                        'title' => $review->title,
                        'comment' => $review->comment,
                        'visit_date' => $review->visit_date,
                        'experience_tags' => is_array($review->experience_tags) ? $review->experience_tags : json_decode($review->experience_tags, true) ?? [],
                        'visit_info' => is_array($review->visit_info) ? $review->visit_info : json_decode($review->visit_info, true) ?? [],
                        'helpful_votes' => $review->helpful_votes,
                        'total_votes' => $review->total_votes,
                        'helpful_percentage' => (float) $review->helpful_percentage,
                        'is_verified' => $review->is_verified,
                        'is_featured' => $review->is_featured,
                        'is_anonymous' => $review->is_anonymous,
                        'time_ago' => $review->time_ago,
                        'is_recent' => $review->is_recent,
                        'created_at' => $review->created_at,
                    ];
                }),
                'created_at' => $attraction->created_at,
                'updated_at' => $attraction->updated_at,
            ];

            // Merge user interactions if authenticated
            if (!empty($userInteractions)) {
                $formattedData = array_merge($formattedData, $userInteractions);
            }

            return response()->json([
                'success' => true,
                'message' => 'Attraction retrieved successfully',
                'data' => $formattedData
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
            
            $query = Attraction::active()
                ->featured()
                ->with(['gallery', 'coverImage']);
            
            // Filter by city if provided
            if ($request->has('city')) {
                $query->inCity($request->city);
            }
            
            // Filter by area if provided
            if ($request->has('area')) {
                $query->inArea($request->area);
            }
            
            // Location-based filtering
            if ($request->has('latitude') && $request->has('longitude')) {
                $latitude = $request->latitude;
                $longitude = $request->longitude;
                $radius = $request->radius ?? 25; // Default 25km radius for featured
                
                $query->nearbyWithDistance($latitude, $longitude, $radius);
                $attractions = $query->orderBy('distance')
                    ->orderBy('discovery_score', 'desc')
                    ->take($limit)
                    ->get();
                
                // Update discovery scores based on user location
                $attractions->each(function ($attraction) use ($latitude, $longitude) {
                    $attraction->updateDiscoveryScore($latitude, $longitude);
                });
            } else {
                // No location provided - use discovery score only
                $attractions = $query->orderBy('discovery_score', 'desc')
                    ->take($limit)
                    ->get();
            }

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

            $meta = [
                'total_count' => $attractions->count()
            ];
            
            // Add location info to meta if provided
            if ($request->has('latitude') && $request->has('longitude')) {
                $meta['search_location'] = [
                    'latitude' => (float) $request->latitude,
                    'longitude' => (float) $request->longitude,
                    'radius_km' => $request->radius ?? 25
                ];
            }
            
            // Add city/area filters to meta
            if ($request->has('city') || $request->has('area')) {
                $meta['location_filters'] = array_filter([
                    'city' => $request->city,
                    'area' => $request->area
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Featured attractions retrieved successfully',
                'data' => $attractions,
                'meta' => $meta
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
     * Get popular attractions based on rating, likes, and recent activity
     */
    public function popular(Request $request)
    {
        try {
            $limit = min($request->limit ?? 10, 20);
            $timeframe = $request->timeframe ?? '30'; // days
            
            $query = Attraction::active()
                ->with(['gallery', 'coverImage'])
                ->where('overall_rating', '>=', 3.5) // Minimum rating for popularity
                ->where('total_reviews', '>=', 1); // Must have at least 1 review
            
            // Location-based filtering
            if ($request->has('latitude') && $request->has('longitude')) {
                $latitude = $request->latitude;
                $longitude = $request->longitude;
                $radius = $request->radius ?? 50; // Default 50km radius for popular
                
                $query->nearbyWithDistance($latitude, $longitude, $radius);
            }
            
            // Filter by city if provided
            if ($request->has('city')) {
                $query->inCity($request->city);
            }
            
            // Filter by area if provided
            if ($request->has('area')) {
                $query->inArea($request->area);
            }
            
            // Filter by timeframe for recent popularity
            if ($timeframe !== 'all') {
                $cutoffDate = now()->subDays((int)$timeframe);
                $query->where('updated_at', '>=', $cutoffDate);
            }
            
            // Sort by popularity metrics
            $sortBy = $request->sort_by ?? 'combined';
            
            switch ($sortBy) {
                case 'rating':
                    $query->orderBy('overall_rating', 'desc')
                          ->orderBy('total_reviews', 'desc');
                    break;
                case 'likes':
                    $query->orderBy('total_likes', 'desc')
                          ->orderBy('overall_rating', 'desc');
                    break;
                case 'views':
                    $query->orderBy('total_views', 'desc')
                          ->orderBy('total_likes', 'desc');
                    break;
                case 'reviews':
                    $query->orderBy('total_reviews', 'desc')
                          ->orderBy('overall_rating', 'desc');
                    break;
                case 'distance':
                    // Only available when location is provided
                    if ($request->has('latitude') && $request->has('longitude')) {
                        $query->orderBy('distance');
                    } else {
                        $query->orderBy('discovery_score', 'desc');
                    }
                    break;
                case 'combined':
                default:
                    if ($request->has('latitude') && $request->has('longitude')) {
                        // Location-aware combined score: balance popularity with distance
                        $query->orderByRaw('((overall_rating * total_reviews * total_likes * total_views) / (distance + 1)) DESC')
                              ->orderBy('discovery_score', 'desc');
                    } else {
                        // Standard weighted popularity score
                        $query->orderByRaw('(overall_rating * total_reviews * total_likes * total_views) DESC')
                              ->orderBy('discovery_score', 'desc');
                    }
                    break;
            }
            
            $attractions = $query->take($limit)->get();
            
            // Update discovery scores if location provided
            if ($request->has('latitude') && $request->has('longitude')) {
                $attractions->each(function ($attraction) use ($request) {
                    $attraction->updateDiscoveryScore($request->latitude, $request->longitude);
                });
            }

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

            $meta = [
                'total_count' => $attractions->count(),
                'timeframe_days' => $timeframe,
                'sorted_by' => $sortBy,
                'filters_applied' => [
                    'min_rating' => 3.5,
                    'min_reviews' => 1
                ]
            ];
            
            // Add location info to meta if provided
            if ($request->has('latitude') && $request->has('longitude')) {
                $meta['search_location'] = [
                    'latitude' => (float) $request->latitude,
                    'longitude' => (float) $request->longitude,
                    'radius_km' => $request->radius ?? 50
                ];
            }
            
            // Add city/area filters to meta
            if ($request->has('city') || $request->has('area')) {
                $meta['location_filters'] = array_filter([
                    'city' => $request->city,
                    'area' => $request->area
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Popular attractions retrieved successfully',
                'data' => $attractions,
                'meta' => $meta
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve popular attractions',
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

    /**
     * Generate comprehensive free maps data including OpenStreetMap URL and Leaflet configuration
     */
    private function generateFreeMapsData($attraction)
    {
        $latitude = (float) $attraction->latitude;
        $longitude = (float) $attraction->longitude;
        
        if (!$latitude || !$longitude) {
            return null;
        }
        
        $popupContent = $attraction->name;
        if ($attraction->address) {
            $popupContent .= '<br>' . $attraction->address;
        }
        
        return [
            'openstreetmap_url' => "https://www.openstreetmap.org/?mlat={$latitude}&mlon={$longitude}&zoom=15",
            'leaflet_data' => [
                'center' => [
                    'lat' => $latitude,
                    'lng' => $longitude,
                ],
                'zoom' => 15,
                'marker' => [
                    'lat' => $latitude,
                    'lng' => $longitude,
                    'popup' => $popupContent,
                ],
                'tile_url' => 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
                'attribution' => '© OpenStreetMap contributors',
            ],
        ];
    }

    /**
     * Generate OpenStreetMap URL using latitude and longitude
     */
    private function generateOpenStreetMapUrl($latitude, $longitude)
    {
        if (!$latitude || !$longitude) {
            return null;
        }
        
        // OpenStreetMap URL format: https://www.openstreetmap.org/?mlat=lat&mlon=lon&zoom=15
        return "https://www.openstreetmap.org/?mlat={$latitude}&mlon={$longitude}&zoom=20";
    }

    /**
     * Helper method to parse JSON fields safely
     */
    private function parseJsonField($field)
    {
        if (is_array($field)) {
            return $field;
        }
        
        if (is_string($field)) {
            $decoded = json_decode($field, true);
            return $decoded !== null ? $decoded : $field;
        }
        
        return $field;
    }
}
