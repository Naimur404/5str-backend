<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\BusinessOffering;
use App\Models\Category;
use App\Services\AnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SearchController extends Controller
{
    protected $analyticsService;

    public function __construct(AnalyticsService $analyticsService)
    {
        $this->analyticsService = $analyticsService;
    }

    /**
     * Universal search for businesses and offerings
     */
    public function search(Request $request)
    {
        try {
            $searchTerm = $request->input('q');
            $searchType = $request->input('type', 'all'); // all, businesses, offerings
            $latitude = $request->input('latitude');
            $longitude = $request->input('longitude');
            $categoryId = $request->input('category_id');
            $radiusKm = $request->input('radius', 20);
            $page = $request->input('page', 1);
            $limit = $request->input('limit', 20);
            $sortBy = $request->input('sort', 'relevance');

            $results = [];

            // Search businesses if type is 'all' or 'businesses'
            if (in_array($searchType, ['all', 'businesses'])) {
                $businessResults = $this->searchBusinesses($request, $searchTerm, $latitude, $longitude, $categoryId, $radiusKm, $page, $limit, $sortBy);
                $results['businesses'] = $businessResults;
            }

            // Search offerings if type is 'all' or 'offerings'
            if (in_array($searchType, ['all', 'offerings'])) {
                $offeringResults = $this->searchOfferings($request, $searchTerm, $latitude, $longitude, $categoryId, $radiusKm, $page, $limit, $sortBy);
                $results['offerings'] = $offeringResults;
            }

            // Get search suggestions if search term is provided
            $suggestions = [];
            if ($searchTerm && strlen($searchTerm) >= 2) {
                $suggestions = $this->getSearchSuggestions($searchTerm, $categoryId, 10);
            }

            // Get total results count for analytics
            $totalResults = 0;
            if (isset($results['businesses'])) {
                $totalResults += $results['businesses']['pagination']['total'];
            }
            if (isset($results['offerings'])) {
                $totalResults += $results['offerings']['pagination']['total'];
            }

            // Log the search
            $this->logSearch($request, $totalResults);

            return response()->json([
                'success' => true,
                'data' => [
                    'search_term' => $searchTerm,
                    'search_type' => $searchType,
                    'total_results' => $totalResults,
                    'results' => $results,
                    'suggestions' => $suggestions,
                    'filters_applied' => [
                        'category_id' => $categoryId,
                        'location' => $latitude && $longitude ? [
                            'latitude' => $latitude,
                            'longitude' => $longitude,
                            'radius_km' => $radiusKm
                        ] : null,
                        'sort' => $sortBy
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Search failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Search failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Search suggestions (autocomplete)
     */
    public function suggestions(Request $request)
    {
        try {
            $searchTerm = $request->input('q');
            $categoryId = $request->input('category_id');
            $limit = $request->input('limit', 10);

            if (!$searchTerm || strlen($searchTerm) < 2) {
                return response()->json([
                    'success' => true,
                    'data' => []
                ]);
            }

            $suggestions = $this->getSearchSuggestions($searchTerm, $categoryId, $limit);

            return response()->json([
                'success' => true,
                'data' => $suggestions
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch suggestions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Popular searches
     */
    public function popular(Request $request)
    {
        try {
            $limit = $request->input('limit', 20);
            $categoryId = $request->input('category_id');

            // Get popular search terms from analytics
            $popularSearches = $this->analyticsService->getPopularSearchTerms($limit, $categoryId);

            return response()->json([
                'success' => true,
                'data' => $popularSearches
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch popular searches',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Search businesses
     */
    protected function searchBusinesses(Request $request, $searchTerm, $latitude, $longitude, $categoryId, $radiusKm, $page, $limit, $sortBy)
    {
        $query = Business::active()
            ->with(['category:id,name,slug', 'logoImage']);

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

        // Apply additional filters
        if ($request->has('min_rating')) {
            $query->withRating($request->min_rating);
        }

        if ($request->boolean('is_verified')) {
            $query->verified();
        }

        if ($request->boolean('has_delivery')) {
            $query->where('has_delivery', true);
        }

        if ($request->boolean('has_pickup')) {
            $query->where('has_pickup', true);
        }

        // Sort options
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
            case 'popular':
                $query->orderBy('total_reviews', 'desc');
                break;
            case 'newest':
                $query->orderBy('created_at', 'desc');
                break;
            default: // relevance
                if ($searchTerm) {
                    // Order by relevance: exact name matches first, then partial matches
                    $query->orderByRaw("CASE 
                        WHEN business_name LIKE ? THEN 1 
                        WHEN business_name LIKE ? THEN 2 
                        WHEN description LIKE ? THEN 3 
                        ELSE 4 
                    END", [
                        $searchTerm,
                        "%{$searchTerm}%",
                        "%{$searchTerm}%"
                    ]);
                } else {
                    $query->orderBy('discovery_score', 'desc');
                }
        }

        $businesses = $query->paginate($limit, ['*'], 'page', $page);

        // Format business data
        $businessData = $businesses->getCollection()->map(function($business) {
            return [
                'id' => $business->id,
                'business_name' => $business->business_name,
                'slug' => $business->slug,
                'description' => $business->description,
                'business_type' => $business->business_type,
                'full_address' => $business->full_address,
                'area' => $business->area,
                'city' => $business->city,
                'latitude' => $business->latitude,
                'longitude' => $business->longitude,
                'phone' => $business->phone,
                'email' => $business->email,
                'website' => $business->website,
                'overall_rating' => $business->overall_rating,
                'total_reviews' => $business->total_reviews,
                'is_verified' => $business->is_verified,
                'is_featured' => $business->is_featured,
                'has_delivery' => $business->has_delivery,
                'has_pickup' => $business->has_pickup,
                'has_parking' => $business->has_parking,
                'opening_hours' => $business->opening_hours,
                'category' => $business->category ? [
                    'id' => $business->category->id,
                    'name' => $business->category->name,
                    'slug' => $business->category->slug,
                ] : null,
                'logo_image' => $business->logoImage ? [
                    'id' => $business->logoImage->id,
                    'image_url' => $business->logoImage->image_url,
                ] : null,
                'distance_km' => $business->distance_km ?? null,
                'type' => 'business'
            ];
        });

        return [
            'data' => $businessData,
            'pagination' => [
                'current_page' => $businesses->currentPage(),
                'last_page' => $businesses->lastPage(),
                'per_page' => $businesses->perPage(),
                'total' => $businesses->total(),
                'has_more' => $businesses->hasMorePages()
            ]
        ];
    }

    /**
     * Search offerings
     */
    protected function searchOfferings(Request $request, $searchTerm, $latitude, $longitude, $categoryId, $radiusKm, $page, $limit, $sortBy)
    {
        $query = BusinessOffering::available()
            ->with([
                'business:id,business_name,slug,latitude,longitude,city,area',
                'category:id,name,slug'
            ]);

        // Text search
        if ($searchTerm) {
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('description', 'LIKE', "%{$searchTerm}%");
            });
        }

        // Category filter
        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        // Location-based filtering (filter by business location)
        if ($latitude && $longitude) {
            $query->whereHas('business', function ($q) use ($latitude, $longitude, $radiusKm) {
                $q->nearby($latitude, $longitude, $radiusKm);
            });
        }

        // Apply additional filters
        if ($request->has('min_rating')) {
            $query->where('average_rating', '>=', $request->min_rating);
        }

        if ($request->has('price_min') || $request->has('price_max')) {
            $priceMin = $request->input('price_min', 0);
            $priceMax = $request->input('price_max', 999999);
            $query->whereBetween('price', [$priceMin, $priceMax]);
        }

        if ($request->has('offering_type')) {
            $query->where('offering_type', $request->offering_type);
        }

        if ($request->boolean('is_popular')) {
            $query->where('is_popular', true);
        }

        if ($request->boolean('is_featured')) {
            $query->where('is_featured', true);
        }

        // Sort options
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
                $query->orderBy('total_reviews', 'desc');
                break;
            case 'name':
                $query->orderBy('name');
                break;
            case 'newest':
                $query->orderBy('created_at', 'desc');
                break;
            default: // relevance
                if ($searchTerm) {
                    // Order by relevance: exact name matches first
                    $query->orderByRaw("CASE 
                        WHEN name LIKE ? THEN 1 
                        WHEN name LIKE ? THEN 2 
                        WHEN description LIKE ? THEN 3 
                        ELSE 4 
                    END", [
                        $searchTerm,
                        "%{$searchTerm}%",
                        "%{$searchTerm}%"
                    ]);
                } else {
                    $query->orderBy('sort_order')->orderBy('name');
                }
        }

        $offerings = $query->paginate($limit, ['*'], 'page', $page);

        // Format offering data
        $offeringData = $offerings->getCollection()->map(function($offering) use ($latitude, $longitude) {
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
                'business' => [
                    'id' => $offering->business->id,
                    'business_name' => $offering->business->business_name,
                    'slug' => $offering->business->slug,
                    'city' => $offering->business->city,
                    'area' => $offering->business->area,
                ],
                'category' => $offering->category ? [
                    'id' => $offering->category->id,
                    'name' => $offering->category->name,
                    'slug' => $offering->category->slug,
                ] : null,
                'type' => 'offering'
            ];

            // Calculate distance if user location is provided
            if ($latitude && $longitude && $offering->business->latitude && $offering->business->longitude) {
                $data['business']['distance_km'] = $this->calculateDistance(
                    $latitude,
                    $longitude,
                    $offering->business->latitude,
                    $offering->business->longitude
                );
            }

            return $data;
        });

        return [
            'data' => $offeringData,
            'pagination' => [
                'current_page' => $offerings->currentPage(),
                'last_page' => $offerings->lastPage(),
                'per_page' => $offerings->perPage(),
                'total' => $offerings->total(),
                'has_more' => $offerings->hasMorePages()
            ]
        ];
    }

    /**
     * Get search suggestions
     */
    protected function getSearchSuggestions($searchTerm, $categoryId = null, $limit = 10)
    {
        $suggestions = [];

        // Business name suggestions
        $businessQuery = Business::active()
            ->where('business_name', 'LIKE', "%{$searchTerm}%")
            ->select('business_name as suggestion', 'id')
            ->distinct();

        if ($categoryId) {
            $businessQuery->inCategory($categoryId);
        }

        $businessSuggestions = $businessQuery->take($limit / 2)->get()->map(function($item) {
            return [
                'suggestion' => $item->suggestion,
                'type' => 'business',
                'id' => $item->id
            ];
        });

        // Offering name suggestions
        $offeringQuery = BusinessOffering::available()
            ->where('name', 'LIKE', "%{$searchTerm}%")
            ->select('name as suggestion', 'id')
            ->distinct();

        if ($categoryId) {
            $offeringQuery->where('category_id', $categoryId);
        }

        $offeringSuggestions = $offeringQuery->take($limit / 2)->get()->map(function($item) {
            return [
                'suggestion' => $item->suggestion,
                'type' => 'offering',
                'id' => $item->id
            ];
        });

        // Category suggestions
        $categorySuggestions = Category::active()
            ->where('name', 'LIKE', "%{$searchTerm}%")
            ->select('name as suggestion', 'id')
            ->take(3)
            ->get()
            ->map(function($item) {
                return [
                    'suggestion' => $item->suggestion,
                    'type' => 'category',
                    'id' => $item->id
                ];
            });

        // Combine and limit suggestions
        $suggestions = collect()
            ->merge($businessSuggestions)
            ->merge($offeringSuggestions)
            ->merge($categorySuggestions)
            ->take($limit)
            ->values()
            ->toArray();

        return $suggestions;
    }

    /**
     * Calculate distance between two points
     */
    protected function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371; // km

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));

        return round($earthRadius * $c, 2);
    }

    /**
     * Log search activity
     */
    private function logSearch(Request $request, $resultsCount)
    {
        try {
            $this->analyticsService->logSearch(
                searchTerm: $request->input('q'),
                categoryId: $request->input('category_id'),
                userLatitude: $request->input('latitude') ? (float) $request->input('latitude') : null,
                userLongitude: $request->input('longitude') ? (float) $request->input('longitude') : null,
                filtersApplied: $request->except(['q', 'page', 'limit']),
                resultsCount: $resultsCount,
                request: $request
            );
        } catch (\Exception $e) {
            // Log error but don't fail the request
            Log::error('Failed to log search: ' . $e->getMessage());
        }
    }
}
