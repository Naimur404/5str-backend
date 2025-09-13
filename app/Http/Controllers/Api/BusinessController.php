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
use App\Services\LocationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\PersonalAccessToken;
use Illuminate\Support\Facades\Auth;

class BusinessController extends Controller
{
    protected $analyticsService;
    protected $locationService;

    public function __construct(AnalyticsService $analyticsService, LocationService $locationService)
    {
        $this->analyticsService = $analyticsService;
        $this->locationService = $locationService;
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

            // Include national businesses or location-based filtering
            if ($latitude && $longitude) {
                // Include both nearby local businesses and national businesses
                $query->where(function($q) use ($latitude, $longitude, $radiusKm) {
                    $q->where('is_national', true)
                      ->orWhere(function($subQ) use ($latitude, $longitude, $radiusKm) {
                          $subQ->where('is_national', false)
                               ->whereRaw(
                                   "( 6371 * acos( cos( radians(?) ) * 
                                     cos( radians( latitude ) ) * 
                                     cos( radians( longitude ) - radians(?) ) + 
                                     sin( radians(?) ) * 
                                     sin( radians( latitude ) ) ) ) < ?", 
                                   [$latitude, $longitude, $latitude, $radiusKm]
                               );
                      });
                });
            } else {
                // If no location provided, show only national businesses
                $query->national();
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

            // Transform businesses to include Google Maps data
            $transformedBusinesses = collect($businesses->items())->map(function($business) use ($latitude, $longitude) {
                $businessData = $business->toArray();
                
                // Add Google Maps links
                $businessData['google_maps'] = [
                    'view_url' => $business->google_maps_url,
                    'place_url' => $business->getGoogleMapsPlaceUrl(),
                    'directions_url' => $business->getGoogleMapsDirectionsUrl($latitude, $longitude),
                    'embed_url' => $business->getGoogleMapsEmbedUrl(null, 'place'),
                    'simple_url' => $business->getGoogleMapsSimpleUrl(),
                ];

                // Add free map alternatives
                $businessData['free_maps'] = [
                    'openstreetmap_url' => $business->getOpenStreetMapUrl(),
                    'leaflet_data' => $business->getLeafletMapData(),
                    'mapbox_url' => $business->getMapBoxUrl(), // Add your MapBox token in config if needed
                ];

                // Add distance if location provided and business has coordinates
                if ($latitude && $longitude && $business->latitude && $business->longitude) {
                    $distance = $this->calculateDistance($latitude, $longitude, $business->latitude, $business->longitude);
                    $businessData['distance'] = $this->formatDistance($distance * 1000); // Convert to meters
                }
                
                return $businessData;
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'businesses' => $transformedBusinesses,
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

            // Transform businesses to include Google Maps data
            $transformedBusinesses = collect($businesses->items())->map(function($business) use ($latitude, $longitude) {
                $businessData = $business->toArray();
                
                // Add Google Maps links
                $businessData['google_maps'] = [
                    'view_url' => $business->google_maps_url,
                    'place_url' => $business->getGoogleMapsPlaceUrl(),
                    'directions_url' => $business->getGoogleMapsDirectionsUrl($latitude, $longitude),
                    'embed_url' => $business->getGoogleMapsEmbedUrl(null, 'place'),
                    'simple_url' => $business->getGoogleMapsSimpleUrl(),
                ];

                // Add free map alternatives
                $businessData['free_maps'] = [
                    'openstreetmap_url' => $business->getOpenStreetMapUrl(),
                    'leaflet_data' => $business->getLeafletMapData(),
                    'mapbox_url' => $business->getMapBoxUrl(),
                ];

                // Add distance if location provided
                if ($latitude && $longitude && $business->latitude && $business->longitude) {
                    $distance = $this->calculateDistance($latitude, $longitude, $business->latitude, $business->longitude);
                    $businessData['distance'] = $this->formatDistance($distance * 1000);
                }
                
                return $businessData;
            });

            // Log the search
            $this->logSearch($request, $businesses->total());

            return response()->json([
                'success' => true,
                'data' => [
                    'search_term' => $searchTerm,
                    'businesses' => $transformedBusinesses,
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

            // Transform businesses to include Google Maps data
            $transformedBusinesses = $businesses->map(function($business) use ($latitude, $longitude) {
                $businessData = $business->toArray();
                
                // Add Google Maps links
                $businessData['google_maps'] = [
                    'view_url' => $business->google_maps_url,
                    'place_url' => $business->getGoogleMapsPlaceUrl(),
                    'directions_url' => $business->getGoogleMapsDirectionsUrl($latitude, $longitude),
                    'embed_url' => $business->getGoogleMapsEmbedUrl(null, 'place'),
                    'simple_url' => $business->getGoogleMapsSimpleUrl(),
                ];

                // Add free map alternatives
                $businessData['free_maps'] = [
                    'openstreetmap_url' => $business->getOpenStreetMapUrl(),
                    'leaflet_data' => $business->getLeafletMapData(),
                    'mapbox_url' => $business->getMapBoxUrl(),
                ];

                // Add distance
                if ($business->latitude && $business->longitude) {
                    $distance = $this->calculateDistance($latitude, $longitude, $business->latitude, $business->longitude);
                    $businessData['distance'] = $this->formatDistance($distance * 1000);
                }
                
                return $businessData;
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'location' => [
                        'latitude' => $latitude,
                        'longitude' => $longitude,
                        'radius_km' => $radiusKm
                    ],
                    'businesses' => $transformedBusinesses
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
     * Get national businesses (brands available across Bangladesh)
     */
    public function national(Request $request)
    {
        try {
            $page = $request->input('page', 1);
            $limit = $request->input('limit', 20);

            $query = Business::active()
                ->national()
                ->with(['category', 'logoImage']);

            // Category filter
            if ($request->has('category_id')) {
                $query->inCategory($request->category_id);
            }

            // Business model filter
            if ($request->has('business_model')) {
                $query->byBusinessModel($request->business_model);
            }

            // Product tag filters - NEW
            if ($request->has('product_tag')) {
                $query->withProductTag($request->product_tag);
            }

            if ($request->has('product_tags')) {
                $tags = is_array($request->product_tags) 
                    ? $request->product_tags 
                    : explode(',', $request->product_tags);
                $query->withAnyProductTag($tags);
            }

            // Business tag filters - NEW
            if ($request->has('business_tag')) {
                $query->withBusinessTag($request->business_tag);
            }

            // Specific item type filters - NEW
            if ($request->has('item_type')) {
                $itemType = $request->item_type;
                switch ($itemType) {
                    case 'ice_cream':
                        $query->withAnyProductTag(['ice cream', 'dairy', 'frozen dessert', 'gelato', 'kulfi']);
                        break;
                    case 'biscuits_snacks':
                        $query->withAnyProductTag(['biscuit', 'cookie', 'snack', 'chips', 'crackers', 'wafer']);
                        break;
                    case 'beverages':
                        $query->withAnyProductTag(['beverage', 'soft drink', 'juice', 'water', 'tea', 'coffee']);
                        break;
                    case 'food_processing':
                        $query->withAnyProductTag(['food processing', 'manufacturing', 'packaged food', 'ready meals']);
                        break;
                }
            }

            // Rating filter
            if ($request->has('min_rating')) {
                $query->where('overall_rating', '>=', $request->min_rating);
            }

            // Sort options
            $sortBy = $request->input('sort', 'rating');
            switch ($sortBy) {
                case 'rating':
                    $query->orderByDesc('overall_rating')
                          ->orderByDesc('total_reviews');
                    break;
                case 'popular':
                    $query->orderByDesc('total_reviews')
                          ->orderByDesc('overall_rating');
                    break;
                case 'name':
                    $query->orderBy('business_name');
                    break;
                case 'featured':
                    $query->orderByDesc('is_featured')
                          ->orderByDesc('overall_rating');
                    break;
                default:
                    $query->orderByDesc('overall_rating');
            }

            $businesses = $query->paginate($limit, ['*'], 'page', $page);

            // Transform businesses
            $transformedBusinesses = collect($businesses->items())->map(function($business) {
                $businessData = $business->toArray();
                
                // Add additional data for national businesses
                $businessData['is_national'] = true;
                $businessData['service_coverage'] = $business->service_coverage;
                $businessData['business_model'] = $business->business_model;
                $businessData['service_areas'] = $business->service_areas;
                $businessData['product_tags'] = $business->product_tags; // NEW - Include tags in response
                $businessData['business_tags'] = $business->business_tags; // NEW - Include tags in response
                
                // Add free map alternatives (national businesses may not have specific location)
                if ($business->latitude && $business->longitude) {
                    $businessData['free_maps'] = [
                        'openstreetmap_url' => $business->getOpenStreetMapUrl(),
                        'leaflet_data' => $business->getLeafletMapData(),
                        'mapbox_url' => $business->getMapBoxUrl(),
                    ];
                } else {
                    $businessData['free_maps'] = null;
                }
                
                return $businessData;
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'businesses' => $transformedBusinesses,
                    'pagination' => [
                        'current_page' => $businesses->currentPage(),
                        'last_page' => $businesses->lastPage(),
                        'per_page' => $businesses->perPage(),
                        'total' => $businesses->total(),
                        'has_more' => $businesses->hasMorePages()
                    ],
                    'available_filters' => [ // NEW - Show available filter options
                        'item_types' => [
                            'ice_cream' => 'Ice Cream & Dairy',
                            'biscuits_snacks' => 'Biscuits & Snacks', 
                            'beverages' => 'Beverages',
                            'food_processing' => 'Food Processing'
                        ],
                        'business_models' => ['manufacturing', 'brand', 'delivery_only', 'online_service'],
                        'sort_options' => ['rating', 'popular', 'name', 'featured']
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch national businesses',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get national business filter options and statistics
     */
    public function nationalFilters(Request $request)
    {
        try {
            // Get available product tags from existing national businesses
            $productTags = Business::active()
                ->national()
                ->whereNotNull('product_tags')
                ->pluck('product_tags')
                ->flatten()
                ->unique()
                ->sort()
                ->values();

            // Get available business tags from existing national businesses  
            $businessTags = Business::active()
                ->national()
                ->whereNotNull('business_tags')
                ->pluck('business_tags')
                ->flatten()
                ->unique()
                ->sort()
                ->values();

            // Get counts for each item type
            $itemTypeCounts = [
                'ice_cream' => Business::active()->national()->withAnyProductTag(['ice cream', 'dairy', 'frozen dessert', 'gelato', 'kulfi'])->count(),
                'biscuits_snacks' => Business::active()->national()->withAnyProductTag(['biscuit', 'cookie', 'snack', 'chips', 'crackers', 'wafer'])->count(),
                'beverages' => Business::active()->national()->withAnyProductTag(['beverage', 'soft drink', 'juice', 'water', 'tea', 'coffee'])->count(),
                'food_processing' => Business::active()->national()->withAnyProductTag(['food processing', 'manufacturing', 'packaged food', 'ready meals'])->count(),
            ];

            // Get business model counts
            $businessModelCounts = Business::active()
                ->national()
                ->selectRaw('business_model, COUNT(*) as count')
                ->groupBy('business_model')
                ->pluck('count', 'business_model');

            return response()->json([
                'success' => true,
                'data' => [
                    'item_types' => [
                        'ice_cream' => [
                            'label' => 'Ice Cream & Dairy',
                            'count' => $itemTypeCounts['ice_cream'],
                            'tags' => ['ice cream', 'dairy', 'frozen dessert', 'gelato', 'kulfi']
                        ],
                        'biscuits_snacks' => [
                            'label' => 'Biscuits & Snacks',
                            'count' => $itemTypeCounts['biscuits_snacks'],
                            'tags' => ['biscuit', 'cookie', 'snack', 'chips', 'crackers', 'wafer']
                        ],
                        'beverages' => [
                            'label' => 'Beverages',
                            'count' => $itemTypeCounts['beverages'],
                            'tags' => ['beverage', 'soft drink', 'juice', 'water', 'tea', 'coffee']
                        ],
                        'food_processing' => [
                            'label' => 'Food Processing',
                            'count' => $itemTypeCounts['food_processing'],
                            'tags' => ['food processing', 'manufacturing', 'packaged food', 'ready meals']
                        ]
                    ],
                    'available_product_tags' => $productTags,
                    'available_business_tags' => $businessTags,
                    'business_models' => $businessModelCounts,
                    'sort_options' => [
                        'rating' => 'Highest Rated',
                        'popular' => 'Most Popular',
                        'name' => 'Alphabetical',
                        'featured' => 'Featured First'
                    ],
                    'total_national_businesses' => Business::active()->national()->count()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch national business filters',
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

            // Track business view for analytics with location data
            try {
                $latitude = $request->input('latitude');
                $longitude = $request->input('longitude');
                $userArea = $this->locationService->determineUserAreaPrecise($latitude, $longitude);

                // Use enhanced tracking for trending analysis
                $this->analyticsService->logBusinessView(
                    businessId: $businessId,
                    userLatitude: $latitude ? (float) $latitude : null,
                    userLongitude: $longitude ? (float) $longitude : null,
                    userArea: $userArea,
                    request: $request
                );
                
                Log::info("Business view tracked for business ID: {$businessId} in area: {$userArea}");
            } catch (\Exception $e) {
                Log::error("Failed to track business view: " . $e->getMessage());
            }

            // Update discovery score if user location is provided
            if ($request->has('latitude') && $request->has('longitude')) {
                $business->updateDiscoveryScore($request->latitude, $request->longitude);
            }

            // Check if business is in user's favorites
            $isFavorite = $this->checkIsFavorite($businessId, $request);

            // Convert business to array and add additional data
            $businessData = $business->toArray();
            $businessData['is_favorite'] = $isFavorite;
            
            // Add Google Maps links
            $userLatitude = $request->input('latitude');
            $userLongitude = $request->input('longitude');
            
            $businessData['google_maps'] = [
                'view_url' => $business->google_maps_url,
                'place_url' => $business->getGoogleMapsPlaceUrl(),
                'directions_url' => $business->getGoogleMapsDirectionsUrl($userLatitude, $userLongitude),
                'embed_url' => $business->getGoogleMapsEmbedUrl(null, 'place'),
                'simple_url' => $business->getGoogleMapsSimpleUrl(),
            ];

            // Add free map alternatives
            $businessData['free_maps'] = [
                'openstreetmap_url' => $business->getOpenStreetMapUrl(),
                'leaflet_data' => $business->getLeafletMapData(),
                'mapbox_url' => $business->getMapBoxUrl(),
            ];

            // Add distance information if user location provided
            if ($userLatitude && $userLongitude && $business->latitude && $business->longitude) {
                $distance = $this->calculateDistance($userLatitude, $userLongitude, $business->latitude, $business->longitude);
                $businessData['distance'] = $this->formatDistance($distance * 1000); // Convert to meters
            }

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

        // Handle sorting
        $sortBy = $request->get('sort_by', 'rating'); // default to rating
        
        switch ($sortBy) {
            case 'name':
                $query->orderBy('name', 'ASC');
                break;
            case 'rating':
            default:
                // Sort by highest rating (rated items first, then by rating value DESC)
                // This handles NULL values properly by putting them last
                $query->orderByRaw('CASE WHEN average_rating IS NULL THEN 1 ELSE 0 END, average_rating DESC');
                break;
        }
        
        $offerings = $query->with(['category', 'variants'])->get();

        // Add is_favorite flag to each offering
        $offeringsWithFavorites = $offerings->map(function($offering) use ($request) {
            $offeringData = $offering->toArray();
            $offeringData['is_favorite'] = $this->checkIsOfferingFavorite($offering->id, $request);
            return $offeringData;
        });

        return response()->json([
            'success' => true,
            'data' => [
                'business_id' => (string)$business->id,
                'business' => $business->only(['id', 'business_name']),
                'offerings' => $offeringsWithFavorites,
                'sort_by' => $sortBy,
                'total_count' => $offerings->count()
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
            // Track enhanced view with location data
            $latitude = $request->input('latitude');
            $longitude = $request->input('longitude');
            $userArea = $this->locationService->determineUserAreaPrecise($latitude, $longitude);

            $this->analyticsService->logBusinessView(
                businessId: $business->id,
                userLatitude: $latitude ? (float) $latitude : null,
                userLongitude: $longitude ? (float) $longitude : null,
                userArea: $userArea,
                request: $request
            );

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
     * Track business view for trending analysis
     */
    public function trackBusinessView(Request $request, $businessId)
    {
        try {
            $business = Business::findOrFail($businessId);
            $latitude = $request->input('latitude');
            $longitude = $request->input('longitude');
            $userArea = $this->locationService->determineUserAreaPrecise($latitude, $longitude);

            // Track the view event for trending analysis
            $this->analyticsService->logBusinessView(
                businessId: $businessId,
                userLatitude: $latitude ? (float) $latitude : null,
                userLongitude: $longitude ? (float) $longitude : null,
                userArea: $userArea,
                request: $request
            );

            return response()->json([
                'success' => true,
                'message' => 'Business view tracked',
                'data' => [
                    'business_id' => $businessId,
                    'user_area' => $userArea
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to track business view: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to track business view'
            ], 500);
        }
    }

    /**
     * Determine user area from coordinates
     */
    
    /**
     * Get business analytics by area
     * Uses LocationService for precise area detection and analytics
     */
    public function getBusinessAnalyticsByArea(Request $request)
    {
        try {
            $latitude = $request->input('latitude');
            $longitude = $request->input('longitude');
            $radiusKm = $request->input('radius', 20);
            $categoryId = $request->input('category_id');

            if (!$latitude || !$longitude) {
                return response()->json([
                    'success' => false,
                    'message' => 'Latitude and longitude are required'
                ], 422);
            }

            // Get detailed location insights
            $locationInsights = $this->locationService->getAreaInsights($latitude, $longitude);
            
            // Get businesses in the area
            $query = Business::active()
                ->nearbyWithDistance($latitude, $longitude, $radiusKm)
                ->with(['category:id,name,slug', 'subcategory:id,name']);

            if ($categoryId) {
                $query->where('category_id', $categoryId);
            }

            $businesses = $query->get();

            // Calculate area statistics
            $stats = [
                'total_businesses' => $businesses->count(),
                'avg_rating' => $businesses->avg('overall_rating') ? round($businesses->avg('overall_rating'), 2) : 0,
                'total_reviews' => $businesses->sum('total_reviews'),
                'featured_count' => $businesses->where('is_featured', true)->count(),
                'category_distribution' => $businesses->groupBy('category.name')->map->count(),
                'rating_distribution' => [
                    '5_star' => $businesses->where('overall_rating', '>=', 4.5)->count(),
                    '4_star' => $businesses->whereBetween('overall_rating', [3.5, 4.49])->count(),
                    '3_star' => $businesses->whereBetween('overall_rating', [2.5, 3.49])->count(),
                    'below_3' => $businesses->where('overall_rating', '<', 2.5)->count(),
                ],
                'distance_distribution' => [
                    'within_5km' => $businesses->where('distance_km', '<=', 5)->count(),
                    '5_to_10km' => $businesses->whereBetween('distance_km', [5.01, 10])->count(),
                    '10_to_15km' => $businesses->whereBetween('distance_km', [10.01, 15])->count(),
                    'above_15km' => $businesses->where('distance_km', '>', 15)->count(),
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'location_details' => $locationInsights,
                    'search_parameters' => [
                        'center_coordinates' => ['latitude' => $latitude, 'longitude' => $longitude],
                        'radius_km' => $radiusKm,
                        'category_filter' => $categoryId
                    ],
                    'business_statistics' => $stats,
                    'sample_businesses' => $businesses->take(5)->map(function ($business) {
                        return [
                            'id' => $business->id,
                            'name' => $business->business_name,
                            'category' => $business->category->name ?? null,
                            'rating' => $business->overall_rating,
                            'distance_km' => round($business->distance_km, 2),
                            'is_featured' => $business->is_featured
                        ];
                    })
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Business area analytics error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get business analytics'
            ], 500);
        }
    }

    /**
     * Get businesses with enhanced area-based filtering
     * Uses LocationService for precise area detection
     */
    public function getBusinessesByPreciseArea(Request $request)
    {
        try {
            $latitude = $request->input('latitude');
            $longitude = $request->input('longitude');
            $specificArea = $request->input('specific_area'); // Optional: filter by specific area name
            $radiusKm = $request->input('radius', 15);
            $limit = $request->input('limit', 20);
            $sortBy = $request->input('sort_by', 'relevance'); // relevance, rating, distance, reviews

            if (!$latitude || !$longitude) {
                return response()->json([
                    'success' => false,
                    'message' => 'Latitude and longitude are required'
                ], 422);
            }

            // Get user's precise location info
            $userArea = $this->locationService->determineUserAreaPrecise($latitude, $longitude);
            $locationInsights = $this->locationService->getAreaInsights($latitude, $longitude);

            // Get businesses in the area
            $query = Business::active()
                ->nearbyWithDistance($latitude, $longitude, $radiusKm)
                ->with([
                    'category:id,name,slug,icon_image,color_code',
                    'subcategory:id,name,slug',
                    'logoImage:id,business_id,image_url',
                    'bannerImages:id,business_id,image_url'
                ]);

            // Optional: Filter by specific area if businesses have area information
            if ($specificArea) {
                // This could be enhanced to store area information in business table
                // For now, we'll filter by proximity to area center
            }

            $businesses = $query->get();

            // Filter businesses that are actually in the same area if precise area filtering is requested
            if ($specificArea && $specificArea === $userArea) {
                $businesses = $businesses->filter(function ($business) use ($userArea) {
                    $businessArea = $this->locationService->determineUserAreaPrecise(
                        $business->latitude, 
                        $business->longitude
                    );
                    return $businessArea === $userArea;
                });
            }

            // Apply sorting
            switch ($sortBy) {
                case 'rating':
                    $businesses = $businesses->sortByDesc('overall_rating');
                    break;
                case 'distance':
                    $businesses = $businesses->sortBy('distance_km');
                    break;
                case 'reviews':
                    $businesses = $businesses->sortByDesc('total_reviews');
                    break;
                case 'relevance':
                default:
                    // Custom relevance scoring based on area, rating, distance, and reviews
                    $businesses = $businesses->map(function ($business) use ($userArea) {
                        $business->relevance_score = $this->calculateBusinessRelevanceScore($business, $userArea);
                        return $business;
                    })->sortByDesc('relevance_score');
                    break;
            }

            $businesses = $businesses->take($limit)->values();

            // Track this search for analytics
            $this->analyticsService->logSearch(
                searchTerm: null,
                categoryId: null,
                userLatitude: (float) $latitude,
                userLongitude: (float) $longitude,
                userArea: $userArea,
                filtersApplied: [
                    'area_based_search' => true,
                    'specific_area' => $specificArea,
                    'radius_km' => $radiusKm,
                    'sort_by' => $sortBy
                ],
                resultsCount: $businesses->count(),
                request: $request
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'user_location' => $locationInsights,
                    'businesses' => $businesses->map(function ($business) {
                        return [
                            'id' => $business->id,
                            'business_name' => $business->business_name,
                            'slug' => $business->slug,
                            'phone' => $business->phone,
                            'landmark' => $business->landmark,
                            'latitude' => $business->latitude,
                            'longitude' => $business->longitude,
                            'distance_km' => round($business->distance_km, 2),
                            'overall_rating' => $business->overall_rating,
                            'total_reviews' => $business->total_reviews,
                            'price_range' => $business->price_range,
                            'is_featured' => $business->is_featured,
                            'category' => $business->category,
                            'subcategory' => $business->subcategory,
                            'logo_image' => $business->logoImage->image_url ?? null,
                            'banner_image' => $business->bannerImages->first()->image_url ?? null,
                            'relevance_score' => $business->relevance_score ?? null,
                            'business_area' => $this->locationService->determineUserAreaPrecise(
                                $business->latitude, 
                                $business->longitude
                            )
                        ];
                    }),
                    'metadata' => [
                        'total_found' => $businesses->count(),
                        'search_radius_km' => $radiusKm,
                        'sort_by' => $sortBy,
                        'area_filtered' => $specificArea !== null
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Precise area business search error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get businesses by area'
            ], 500);
        }
    }

    /**
     * Calculate business relevance score for area-based recommendations
     */
    private function calculateBusinessRelevanceScore($business, $userArea)
    {
        $score = 0;

        // Base rating score (0-40 points)
        $score += ($business->overall_rating / 5) * 40;

        // Review count factor (0-20 points)
        $reviewScore = min(($business->total_reviews / 100) * 20, 20);
        $score += $reviewScore;

        // Distance factor (0-20 points, closer is better)
        $distanceScore = max(0, 20 - ($business->distance_km * 1.5));
        $score += $distanceScore;

        // Featured business bonus (0-10 points)
        if ($business->is_featured) {
            $score += 10;
        }

        // Same area bonus (0-10 points)
        $businessArea = $this->locationService->determineUserAreaPrecise(
            $business->latitude, 
            $business->longitude
        );
        if ($businessArea === $userArea) {
            $score += 10;
        }

        return round($score, 2);
    }

    /**
     * Get area comparison analytics
     * Compare business metrics across different areas
     */
    public function getAreaComparisonAnalytics(Request $request)
    {
        try {
            $areas = $request->input('areas', []); // Array of coordinates [{lat, lng, name}]
            $radiusKm = $request->input('radius', 10);

            if (empty($areas) || count($areas) < 2) {
                return response()->json([
                    'success' => false,
                    'message' => 'At least 2 areas are required for comparison'
                ], 422);
            }

            $comparison = [];

            foreach ($areas as $area) {
                if (!isset($area['latitude']) || !isset($area['longitude'])) {
                    continue;
                }

                $lat = $area['latitude'];
                $lng = $area['longitude'];
                $areaName = $area['name'] ?? 'Unnamed Area';

                // Get location insights
                $insights = $this->locationService->getAreaInsights($lat, $lng);

                // Get businesses in this area
                $businesses = Business::active()
                    ->nearbyWithDistance($lat, $lng, $radiusKm)
                    ->with('category:id,name')
                    ->get();

                $comparison[] = [
                    'area_info' => [
                        'custom_name' => $areaName,
                        'detected_area' => $insights['specific_area'] ?? 'Unknown',
                        'district' => $insights['district'] ?? 'Unknown',
                        'division' => $insights['division'] ?? 'Unknown',
                        'coordinates' => ['latitude' => $lat, 'longitude' => $lng]
                    ],
                    'business_metrics' => [
                        'total_businesses' => $businesses->count(),
                        'avg_rating' => $businesses->avg('overall_rating') ? round($businesses->avg('overall_rating'), 2) : 0,
                        'total_reviews' => $businesses->sum('total_reviews'),
                        'featured_count' => $businesses->where('is_featured', true)->count(),
                        'top_categories' => $businesses->groupBy('category.name')
                            ->map->count()
                            ->sortDesc()
                            ->take(3)
                            ->toArray()
                    ]
                ];
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'comparison_results' => $comparison,
                    'summary' => [
                        'areas_compared' => count($comparison),
                        'search_radius_km' => $radiusKm,
                        'best_area_by_business_count' => collect($comparison)->sortByDesc('business_metrics.total_businesses')->first()['area_info']['custom_name'] ?? null,
                        'best_area_by_avg_rating' => collect($comparison)->sortByDesc('business_metrics.avg_rating')->first()['area_info']['custom_name'] ?? null
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Area comparison analytics error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate area comparison'
            ], 500);
        }
    }

    /**
     * Log search activity with enhanced location tracking
     */
    private function logSearch(Request $request, $resultsCount)
    {
        try {
            $latitude = $request->input('latitude');
            $longitude = $request->input('longitude');
            $userArea = null;

            if ($latitude && $longitude) {
                $userArea = $this->locationService->determineUserAreaPrecise($latitude, $longitude);
            }

            $this->analyticsService->logSearch(
                searchTerm: $request->input('q'),
                categoryId: $request->input('category_id'),
                userLatitude: $latitude ? (float) $latitude : null,
                userLongitude: $longitude ? (float) $longitude : null,
                userArea: $userArea,
                filtersApplied: $request->except(['q', 'page', 'limit']),
                resultsCount: $resultsCount,
                request: $request
            );
        } catch (\Exception $e) {
            // Log error but don't fail the request
            Log::error('Failed to log search: ' . $e->getMessage());
        }
    }

    /**
     * Calculate distance between two points in kilometers using Haversine formula
     */
    private function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371; // Earth's radius in kilometers

        $lat1Rad = deg2rad($lat1);
        $lon1Rad = deg2rad($lon1);
        $lat2Rad = deg2rad($lat2);
        $lon2Rad = deg2rad($lon2);

        $deltaLat = $lat2Rad - $lat1Rad;
        $deltaLon = $lon2Rad - $lon1Rad;

        $a = sin($deltaLat/2) * sin($deltaLat/2) + cos($lat1Rad) * cos($lat2Rad) * sin($deltaLon/2) * sin($deltaLon/2);
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));

        return $earthRadius * $c;
    }

    /**
     * Format distance from meters to readable format
     */
    private function formatDistance(?float $distanceInMeters): ?array
    {
        if ($distanceInMeters === null) {
            return null;
        }

        if ($distanceInMeters < 1000) {
            return [
                'value' => round($distanceInMeters),
                'unit' => 'm',
                'formatted' => round($distanceInMeters) . ' m'
            ];
        } else {
            $km = $distanceInMeters / 1000;
            return [
                'value' => round($km, 1),
                'unit' => 'km',
                'formatted' => round($km, 1) . ' km'
            ];
        }
    }
}
