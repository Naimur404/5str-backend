<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Business;
use App\Models\BusinessOffering;
use App\Models\FeaturedSection;
use App\Models\Banner;
use App\Models\Offer;
use App\Models\Review;
use App\Models\TrendingData;
use App\Models\Attraction;
use App\Services\AnalyticsService;
use App\Services\LocationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HomeController extends Controller
{
    protected $analyticsService;
    protected $locationService;

    public function __construct(AnalyticsService $analyticsService, LocationService $locationService)
    {
        $this->analyticsService = $analyticsService;
        $this->locationService = $locationService;
    }

    /**
     * Determine user's specific area/ward from coordinates with enhanced precision for full Bangladesh
     * Covers all 8 divisions, 64 districts, and major cities with ward-level precision
     */
   

    /**
     * Track endpoint analytics with location data
     */
    private function trackEndpointAnalytics($endpoint, $latitude = null, $longitude = null, $additionalData = [])
    {
        try {
            $userArea = $this->locationService->determineUserAreaPrecise($latitude, $longitude);
            
            $analyticsData = [
                'endpoint' => $endpoint,
                'user_id' => Auth::id(),
                'user_area' => $userArea,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'additional_data' => json_encode($additionalData),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            // Store in database for analytics
            DB::table('endpoint_analytics')->insert($analyticsData);

            // Also log for immediate debugging
            Log::info("Endpoint analytics", [
                'endpoint' => $endpoint,
                'user_area' => $userArea,
                'coordinates' => $latitude && $longitude ? "{$latitude},{$longitude}" : null,
                'user_id' => Auth::id()
            ]);

        } catch (\Exception $e) {
            Log::error("Failed to track endpoint analytics", [
                'endpoint' => $endpoint,
                'error' => $e->getMessage()
            ]);
        }
    }
    /**
     * Get home screen data (Public access)
     * Includes: banners, featured sections, top categories, nearby businesses, offers
     * Area is automatically determined from lat/lng coordinates
     * Implements smart business placement - each business appears in only ONE section
     */
    public function index(Request $request)
    {
        try {
            $latitude = $request->input('latitude');
            $longitude = $request->input('longitude');
            $radiusKm = $request->input('radius', 10);

            // Determine user area from coordinates with enhanced precision
            $userArea = $this->locationService->determineUserAreaPrecise($latitude, $longitude);

            // Track this API call for analytics with location data
            $this->trackEndpointAnalytics('home_index', $latitude, $longitude, [
                'radius_km' => $radiusKm,
                'user_area' => $userArea
            ]);

            // Track legacy interaction for compatibility
            $this->trackUserInteraction('home_view', null, $userArea, $latitude, $longitude);

            // Get active banners
            $banners = Banner::where('is_active', true)
                ->where('start_date', '<=', now())
                ->where('end_date', '>=', now())
                ->orderBy('sort_order')
                ->get();

            // Initialize arrays to track used businesses
            $usedBusinessIds = [];
            $sectionData = [];

            // Get all potential businesses for analysis (both local and national)
            $allBusinesses = $this->getAllPotentialBusinesses($latitude, $longitude, $radiusKm);

            // NOTE: National businesses are handled separately in their own section
            // All other sections (trending, featured, popular, dynamic) will only show LOCAL businesses
            
            // 1. PRIORITY 1: Get trending businesses (highest priority - LOCAL ONLY)
            $trendingBusinesses = $this->getTrendingBusinessesForHome($userArea, $allBusinesses, $usedBusinessIds);
            $sectionData['trending_businesses'] = $trendingBusinesses;

            // 2. PRIORITY 2: Get featured businesses (LOCAL ONLY - excluding already used)
            $featuredBusinesses = $this->getFeaturedBusinessesForHome($latitude, $longitude, $radiusKm, $allBusinesses, $usedBusinessIds);
            $sectionData['featured_businesses'] = $featuredBusinesses;

            // 3. PRIORITY 3: Get popular nearby (LOCAL ONLY - excluding already used)
            $popularNearby = [];
            if ($latitude && $longitude) {
                $popularNearby = $this->getPopularNearbyForHome($latitude, $longitude, $radiusKm, $allBusinesses, $usedBusinessIds);
            }
            $sectionData['popular_nearby'] = $popularNearby;

            // 4. PRIORITY 4: Get dynamic sections by category (LOCAL ONLY - excluding already used)
            $dynamicSections = [];
            if ($latitude && $longitude) {
                $dynamicSections = $this->getDynamicSectionsForHome($latitude, $longitude, $radiusKm, $allBusinesses, $usedBusinessIds);
            }
            $sectionData['dynamic_sections'] = $dynamicSections;

            // 5. Get top services (categories) - dynamic based on location and user behavior
            $topServices = $this->getTopServicesForHome($latitude, $longitude, $radiusKm, $userArea);

            // 6. Get special offers
            $specialOffers = $this->getSpecialOffersForHome($latitude, $longitude, $radiusKm, $usedBusinessIds);

            // 7. Get top national brands by category (ice cream, biscuits, drinks, etc.)
            $topNationalBrands = $this->getTopNationalBrandsForHome($usedBusinessIds);

            // 8. Get featured attractions
            $featuredAttractions = $this->getFeaturedAttractionsForHome($latitude, $longitude, $radiusKm);

            // 9. Get popular attractions nearby
            $popularAttractions = [];
            if ($latitude && $longitude) {
                $popularAttractions = $this->getPopularAttractionsForHome($latitude, $longitude, $radiusKm);
            }

            // Track section performance for future optimization
            $this->trackSectionPerformance($sectionData, $userArea);

            return response()->json([
                'success' => true,
                'data' => [
                    'banners' => $banners,
                    'top_services' => $topServices,
                    'trending_businesses' => $sectionData['trending_businesses'],
                    'featured_businesses' => $sectionData['featured_businesses'],
                    'popular_nearby' => $sectionData['popular_nearby'],
                    'dynamic_sections' => $sectionData['dynamic_sections'],
                    'special_offers' => $specialOffers,
                    'top_national_brands' => $topNationalBrands,
                    'featured_attractions' => $featuredAttractions,
                    'popular_attractions' => $popularAttractions,
                    'analytics' => [
                        'total_businesses_shown' => count($usedBusinessIds),
                        'unique_business_placement' => true,
                        'location_based' => $latitude && $longitude ? true : false,
                        'trending_data_driven' => true,
                        'attractions_included' => true
                    ],
                    'user_location' => [
                        'latitude' => $latitude,
                        'longitude' => $longitude,
                        'radius_km' => $radiusKm,
                        'determined_area' => $userArea,
                        'area_detection_method' => $latitude && $longitude ? 'coordinates' : 'default'
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load home data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get featured sections configuration
     */
    public function featuredSections()
    {
        try {
            $sections = FeaturedSection::active()
                ->orderBy('sort_order')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $sections
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load featured sections',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Track banner click
     */
    public function trackBannerClick(Request $request, $bannerId)
    {
        try {
            $banner = Banner::findOrFail($bannerId);
            $banner->increment('click_count');

            return response()->json([
                'success' => true,
                'message' => 'Banner click tracked'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to track banner click'
            ], 500);
        }
    }

    /**
     * Track business view from home page interactions
     */
    public function trackHomeBusinessView(Request $request, $businessId)
    {
        try {
            $latitude = $request->input('latitude');
            $longitude = $request->input('longitude');
            $section = $request->input('section', 'unknown'); // trending, featured, popular, etc.
            $userArea = $this->locationService->determineUserAreaPrecise($latitude, $longitude);

            // Track using AnalyticsService
            $this->analyticsService->logBusinessView(
                businessId: $businessId,
                userLatitude: $latitude ? (float) $latitude : null,
                userLongitude: $longitude ? (float) $longitude : null,
                userArea: $userArea,
                request: $request
            );

            return response()->json([
                'success' => true,
                'message' => 'Business view tracked from home page',
                'data' => [
                    'business_id' => $businessId,
                    'section' => $section,
                    'user_area' => $userArea
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to track home business view: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to track business view'
            ], 500);
        }
    }

    /**
     * Track trending data performance 
     */
    public function trackTrendingPerformance(Request $request)
    {
        try {
            $latitude = $request->input('latitude');
            $longitude = $request->input('longitude');
            $userArea = $this->locationService->determineUserAreaPrecise($latitude, $longitude);
            $interactionType = $request->input('interaction_type', 'view'); // view, click, etc.
            $sectionData = $request->input('section_data', []); // data about which sections were shown

            // Get current trending data for comparison
            $currentTrending = $this->analyticsService->getTrendingBusinesses($userArea, 'daily', 10);

            // Track the interaction
            $this->trackUserInteraction('trending_interaction', null, $userArea, $latitude, $longitude);

            return response()->json([
                'success' => true,
                'message' => 'Trending performance tracked',
                'data' => [
                    'user_area' => $userArea,
                    'interaction_type' => $interactionType,
                    'current_trending_count' => $currentTrending->count(),
                    'timestamp' => now()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to track trending performance: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to track trending performance'
            ], 500);
        }
    }

    /**
     * Get statistics for admin dashboard
     */
    public function statistics()
    {
        try {
            $totalBusinesses = Business::active()->count();
            $totalCategories = Category::active()->count();
            $totalActiveOffers = Offer::where('is_active', true)
                ->where('valid_from', '<=', now())
                ->where('valid_to', '>=', now())
                ->count();

            // Get popular categories by business count
            $popularCategories = Category::active()
                ->orderBy('total_businesses', 'desc')
                ->take(10)
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'total_businesses' => $totalBusinesses,
                    'total_categories' => $totalCategories,
                    'total_active_offers' => $totalActiveOffers,
                    'popular_categories' => $popularCategories
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get trending data
     */
    public function trending(Request $request)
    {
        try {
            $area = $request->input('area', 'Dhanmondi');
            $period = $request->input('period', 'daily'); // daily, weekly, monthly
            $type = $request->input('type'); // business, category, search_term, offering (optional filter)
            
            $query = TrendingData::where('time_period', $period)
                ->where('location_area', $area);

            // Set date based on period
            $dateField = match($period) {
                'daily' => now()->format('Y-m-d'),
                'weekly' => now()->startOfWeek()->format('Y-m-d'),
                'monthly' => now()->startOfMonth()->format('Y-m-d'),
                default => now()->format('Y-m-d')
            };
            
            $query->where('date_period', $dateField);

            if ($type) {
                $query->where('item_type', $type);
            }

            $trendingData = $query->orderBy('trend_score', 'desc')
                ->with(['business', 'category'])
                ->take(20)
                ->get()
                ->map(function($trend) {
                    $item = null;
                    
                    if ($trend->item_type === 'business' && $trend->business) {
                        $item = [
                            'id' => $trend->business->id,
                            'name' => $trend->business->business_name,
                            'slug' => $trend->business->slug,
                            'overall_rating' => $trend->business->overall_rating,
                            'type' => 'business'
                        ];
                    } elseif ($trend->item_type === 'category' && $trend->category) {
                        $item = [
                            'id' => $trend->category->id,
                            'name' => $trend->category->name,
                            'slug' => $trend->category->slug,
                            'icon_image' => $trend->category->icon_image,
                            'type' => 'category'
                        ];
                    } elseif ($trend->item_type === 'offering') {
                        $offering = \App\Models\BusinessOffering::find($trend->item_id);
                        if ($offering) {
                            $item = [
                                'id' => $offering->id,
                                'name' => $offering->name,
                                'offering_type' => $offering->offering_type,
                                'price' => $offering->price,
                                'business_name' => $offering->business->business_name ?? 'Unknown',
                                'type' => 'offering'
                            ];
                        }
                    } elseif ($trend->item_type === 'search_term') {
                        $item = [
                            'name' => $trend->item_name,
                            'type' => 'search_term'
                        ];
                    }

                    return [
                        'item_type' => $trend->item_type,
                        'trend_score' => $trend->trend_score,
                        'item' => $item
                    ];
                })
                ->filter(function($item) {
                    return $item['item'] !== null;
                })
                ->values();

            return response()->json([
                'success' => true,
                'data' => [
                    'trending_data' => $trendingData,
                    'area' => $area,
                    'period' => $period,
                    'date' => $dateField
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load trending data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Determine user area from latitude and longitude coordinates
     * Uses reverse geocoding logic for Bangladesh areas
     */
  

    /**
     * Get all top services (categories)
     */
    public function topServices(Request $request)
    {
        try {
            $latitude = $request->input('latitude');
            $longitude = $request->input('longitude');
            $radiusKm = $request->input('radius', 20);
            $limit = $request->input('limit', 50);

            // Determine user area and track analytics
            $userArea = $this->locationService->determineUserAreaPrecise($latitude, $longitude);
            $this->trackEndpointAnalytics('top_services', $latitude, $longitude, [
                'radius_km' => $radiusKm,
                'limit' => $limit,
                'user_area' => $userArea
            ]);

            $query = Category::active();

            if ($latitude && $longitude) {
                // Get categories that have businesses in the area
                $query->whereHas('businesses', function ($q) use ($latitude, $longitude, $radiusKm) {
                    $q->active()->nearby($latitude, $longitude, $radiusKm);
                })
                ->withCount(['businesses' => function ($q) use ($latitude, $longitude, $radiusKm) {
                    $q->active()->nearby($latitude, $longitude, $radiusKm);
                }])
                ->orderBy('businesses_count', 'desc');
            } else {
                // Fallback to featured categories
                $query->featured()->orderBy('sort_order');
            }

            $services = $query->take($limit)->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'services' => $services,
                    'location' => [
                        'latitude' => $latitude,
                        'longitude' => $longitude,
                        'radius_km' => $radiusKm
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch top services',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all popular businesses nearby
     */
    public function popularNearby(Request $request)
    {
        try {
            $latitude = $request->input('latitude');
            $longitude = $request->input('longitude');
            $radiusKm = $request->input('radius', 20);
            $limit = $request->input('limit', 50);

            if (!$latitude || !$longitude) {
                return response()->json([
                    'success' => false,
                    'message' => 'Latitude and longitude are required'
                ], 422);
            }

            // Determine user area and track analytics
            $userArea = $this->locationService->determineUserAreaPrecise($latitude, $longitude);
            $this->trackEndpointAnalytics('popular_nearby', $latitude, $longitude, [
                'radius_km' => $radiusKm,
                'limit' => $limit,
                'user_area' => $userArea
            ]);

            $businesses = Business::active()
                ->nearbyWithDistance($latitude, $longitude, $radiusKm)
                ->withRating(3.0)
                ->with([
                    'category:id,name,slug,icon_image,color_code',
                    'subcategory:id,name,slug',
                    'logoImage:id,business_id,image_url'
                ])
                ->orderBy('overall_rating', 'desc')
                ->paginate($limit);

            // Transform businesses to include formatted distance
            $transformedBusinesses = $businesses->getCollection()->map(function($business) {
                $businessData = [
                    'id' => $business->id,
                    'business_name' => $business->business_name,
                    'slug' => $business->slug,
                    'landmark' => $business->landmark,
                    'overall_rating' => $business->overall_rating,
                    'total_reviews' => $business->total_reviews,
                    'price_range' => $business->price_range,
                    'category' => $business->category,
                    'subcategory' => $business->subcategory,
                    'logo_image' => $business->logoImage->image_url ?? null,
                    'distance_km' => null // Will be set below
                ];

                // Add formatted distance if available
                if (isset($business->distance)) {
                    $distanceKm = $business->distance;
                    
                    // Format distance with proper units
                    if ($distanceKm < 1) {
                        // Show in meters if less than 1 km
                        $distanceFormatted = number_format($distanceKm * 1000, 2) . ' m';
                    } else {
                        // Show in kilometers if 1 km or more
                        $distanceFormatted = number_format($distanceKm, 2) . ' km';
                    }
                    
                    $businessData['distance'] = $distanceFormatted;
                    $businessData['distance_km'] = $distanceFormatted;
                }

                return $businessData;
            });

            $businesses->setCollection($transformedBusinesses);

            return response()->json([
                'success' => true,
                'data' => [
                    'businesses' => $businesses->items(),
                    'pagination' => [
                        'current_page' => $businesses->currentPage(),
                        'last_page' => $businesses->lastPage(),
                        'per_page' => $businesses->perPage(),
                        'total' => $businesses->total(),
                        'has_more' => $businesses->hasMorePages()
                    ],
                    'location' => [
                        'latitude' => $latitude,
                        'longitude' => $longitude,
                        'radius_km' => $radiusKm,
                        'user_area' => $userArea,
                        'determined_area' => $userArea
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch popular nearby businesses',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all top restaurants
     */
    public function topRestaurants(Request $request)
    {
        try {
            $latitude = $request->input('latitude');
            $longitude = $request->input('longitude');
            $radiusKm = $request->input('radius', 20);
            $limit = $request->input('limit', 50);

            $query = Business::active()
                ->whereHas('category', function ($q) {
                    $q->where('name', 'LIKE', '%restaurant%')
                      ->orWhere('name', 'LIKE', '%food%')
                      ->orWhere('name', 'LIKE', '%cafe%')
                      ->orWhere('name', 'LIKE', '%pizza%')
                      ->orWhere('name', 'LIKE', '%burger%');
                })
                ->with([
                    'category:id,name,slug,icon_image,color_code',
                    'subcategory:id,name,slug',
                    'logoImage:id,business_id,image_url'
                ]);

            if ($latitude && $longitude) {
                $query->nearbyWithDistance($latitude, $longitude, $radiusKm);
            }

            $restaurants = $query->orderBy('overall_rating', 'desc')
                ->paginate($limit);

            return response()->json([
                'success' => true,
                'data' => [
                    'restaurants' => $restaurants->items(),
                    'pagination' => [
                        'current_page' => $restaurants->currentPage(),
                        'last_page' => $restaurants->lastPage(),
                        'per_page' => $restaurants->perPage(),
                        'total' => $restaurants->total(),
                        'has_more' => $restaurants->hasMorePages()
                    ],
                    'location' => [
                        'latitude' => $latitude,
                        'longitude' => $longitude,
                        'radius_km' => $radiusKm
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch top restaurants',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all top shopping businesses
     */
    public function topShopping(Request $request)
    {
        try {
            $latitude = $request->input('latitude');
            $longitude = $request->input('longitude');
            $radiusKm = $request->input('radius', 20);
            $limit = $request->input('limit', 50);

            $query = Business::active()
                ->whereHas('category', function ($q) {
                    $q->where('name', 'LIKE', '%shopping%')
                      ->orWhere('name', 'LIKE', '%shop%')
                      ->orWhere('name', 'LIKE', '%store%')
                      ->orWhere('name', 'LIKE', '%clothing%')
                      ->orWhere('name', 'LIKE', '%fashion%')
                      ->orWhere('name', 'LIKE', '%retail%');
                })
                ->with([
                    'category:id,name,slug,icon_image,color_code',
                    'subcategory:id,name,slug',
                    'logoImage:id,business_id,image_url'
                ]);

            if ($latitude && $longitude) {
                $query->nearbyWithDistance($latitude, $longitude, $radiusKm);
            }

            $shopping = $query->orderBy('overall_rating', 'desc')
                ->paginate($limit);

            return response()->json([
                'success' => true,
                'data' => [
                    'shopping' => $shopping->items(),
                    'pagination' => [
                        'current_page' => $shopping->currentPage(),
                        'last_page' => $shopping->lastPage(),
                        'per_page' => $shopping->perPage(),
                        'total' => $shopping->total(),
                        'has_more' => $shopping->hasMorePages()
                    ],
                    'location' => [
                        'latitude' => $latitude,
                        'longitude' => $longitude,
                        'radius_km' => $radiusKm
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch top shopping businesses',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all featured businesses
     */
    public function featuredBusinesses(Request $request)
    {
        try {
            $latitude = $request->input('latitude');
            $longitude = $request->input('longitude');
            $radiusKm = $request->input('radius', 50);
            $limit = $request->input('limit', 50);

            $query = Business::active()->featured()
                ->with([
                    'category:id,name,slug,icon_image,color_code',
                    'subcategory:id,name,slug',
                    'logoImage:id,business_id,image_url'
                ]);

            if ($latitude && $longitude) {
                $query->nearbyWithDistance($latitude, $longitude, $radiusKm);
            }

            $businesses = $query->orderBy('overall_rating', 'desc')
                ->paginate($limit);

            return response()->json([
                'success' => true,
                'data' => [
                    'businesses' => $businesses->items(),
                    'pagination' => [
                        'current_page' => $businesses->currentPage(),
                        'last_page' => $businesses->lastPage(),
                        'per_page' => $businesses->perPage(),
                        'total' => $businesses->total(),
                        'has_more' => $businesses->hasMorePages()
                    ],
                    'location' => [
                        'latitude' => $latitude,
                        'longitude' => $longitude,
                        'radius_km' => $radiusKm
                    ]
                ]
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
     * Get all special offers
     */
    public function specialOffers(Request $request)
    {
        try {
            $latitude = $request->input('latitude');
            $longitude = $request->input('longitude');
            $radiusKm = $request->input('radius', 50);
            $limit = $request->input('limit', 50);

            $query = Offer::whereHas('business', function ($q) use ($latitude, $longitude, $radiusKm) {
                $q->active();
                if ($latitude && $longitude) {
                    $q->nearby($latitude, $longitude, $radiusKm);
                }
            })
            ->where('is_active', true)
            ->where('valid_from', '<=', now())
            ->where('valid_to', '>=', now())
            ->with(['business' => function($q) {
                $q->select(['id', 'business_name', 'slug', 'landmark', 'overall_rating', 'price_range', 'category_id', 'subcategory_id', 'latitude', 'longitude'])
                  ->with([
                      'category:id,name,slug,icon_image,color_code',
                      'subcategory:id,name,slug',
                      'logoImage:id,business_id,image_url'
                  ]);
            }]);

            $offers = $query->orderBy('created_at', 'desc')
                ->paginate($limit);

            return response()->json([
                'success' => true,
                'data' => [
                    'offers' => $offers->items(),
                    'pagination' => [
                        'current_page' => $offers->currentPage(),
                        'last_page' => $offers->lastPage(),
                        'per_page' => $offers->perPage(),
                        'total' => $offers->total(),
                        'has_more' => $offers->hasMorePages()
                    ],
                    'location' => [
                        'latitude' => $latitude,
                        'longitude' => $longitude,
                        'radius_km' => $radiusKm
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch special offers',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get dynamic sections based on actual database categories
     */
    public function dynamicSections(Request $request, $section)
    {
        try {
            $latitude = $request->input('latitude');
            $longitude = $request->input('longitude');
            $radiusKm = $request->input('radius', 20);
            $limit = $request->input('limit', 50);

            // Get all businesses with categories for dynamic section generation
            $businessesQuery = Business::with([
                'category:id,name,slug,icon_image,color_code',
                'subcategory:id,name,slug',
                'logoImage:id,business_id,image_url,image_type',
                'coverImage:id,business_id,image_url,image_type'
            ])->where('is_active', true);

            // Apply location filter if coordinates provided
            if ($latitude && $longitude) {
                $businessesQuery->selectRaw(
                    "businesses.*, ST_Distance_Sphere(POINT(longitude, latitude), POINT(?, ?)) / 1000 as distance",
                    [$longitude, $latitude]
                )
                ->whereRaw(
                    "ST_Distance_Sphere(POINT(longitude, latitude), POINT(?, ?)) <= ?",
                    [$longitude, $latitude, $radiusKm * 1000]
                )
                ->orderBy('distance');
            } else {
                $businessesQuery->select('businesses.*')
                    ->orderByDesc('overall_rating')
                    ->orderByDesc('is_featured');
            }

            $allBusinesses = $businessesQuery->get();
            
            // Group businesses by category
            $categorizedBusinesses = $allBusinesses->groupBy('category.name');
            
            // Generate dynamic section slug from category name
            $requestedSectionSlug = strtolower($section);
            $matchedCategoryName = null;
            
            // Normalize the requested section slug to handle different formats
            $normalizedRequestedSlug = $this->normalizeSectionSlug($requestedSectionSlug);
            
            // Find the category that matches the requested section slug
            foreach ($categorizedBusinesses->keys() as $categoryName) {
                if (!$categoryName) continue;
                
                $categorySlug = $this->normalizeSectionSlug(strtolower($categoryName));
                
                // Exact match
                if ($categorySlug === $normalizedRequestedSlug) {
                    $matchedCategoryName = $categoryName;
                    break;
                }
                
                // Partial match (e.g., "health" matches "Health & Wellness")
                if (strpos($categorySlug, $normalizedRequestedSlug) !== false || 
                    strpos($normalizedRequestedSlug, $categorySlug) !== false) {
                    $matchedCategoryName = $categoryName;
                    break;
                }
            }

            // If no match found, return available sections
            if (!$matchedCategoryName) {
                $availableSections = [];
                foreach ($categorizedBusinesses->keys() as $categoryName) {
                    if ($categoryName && $categorizedBusinesses[$categoryName]->count() >= 1) {
                        $availableSections[] = $this->normalizeSectionSlug(strtolower($categoryName));
                    }
                }
                
                return response()->json([
                    'success' => false,
                    'message' => 'Section not found. Available sections: ' . implode(', ', $availableSections),
                    'available_sections' => $availableSections
                ], 404);
            }

            // Get businesses for the matched category
            $categoryBusinesses = $categorizedBusinesses[$matchedCategoryName];
            
            // Sort businesses by rating and featured status
            $sortedBusinesses = $categoryBusinesses->sortBy([
                ['distance', 'asc'],
                ['overall_rating', 'desc'],
                ['is_featured', 'desc'],
                ['total_reviews', 'desc']
            ]);

            // Get page parameter
            $page = $request->input('page', 1);
            
            // Paginate the results
            $totalBusinesses = $sortedBusinesses->count();
            $paginatedBusinesses = $sortedBusinesses->forPage($page, $limit);

            // Transform businesses to include comprehensive data
            $transformedBusinesses = $paginatedBusinesses->map(function($business) {
                // Get offerings count
                $offeringsCount = 0;
                try {
                    $offeringsCount = \App\Models\BusinessOffering::where('business_id', $business->id)
                        ->where('is_active', true)
                        ->count();
                } catch (\Exception $e) {
                    $offeringsCount = 0;
                }

                // Format distance
                $formattedDistance = null;
                $distanceKm = null;
                if (isset($business->distance)) {
                    $distanceKm = round($business->distance, 4);
                    if ($distanceKm < 1) {
                        $meters = round($distanceKm * 1000, 0);
                        $formattedDistance = $meters . 'm';
                    } else {
                        $formattedDistance = number_format($distanceKm, 1) . 'km';
                    }
                }

                return [
                    'id' => $business->id,
                    'business_name' => $business->business_name,
                    'slug' => $business->slug,
                    'description' => $business->description,
                    'landmark' => $business->landmark,
                    'area' => $business->area ?? null,
                    'address' => $business->address ?? null,
                    'business_phone' => $business->business_phone ?? null,
                    'website_url' => $business->website_url ?? null,
                    'overall_rating' => $business->overall_rating,
                    'total_reviews' => $business->total_reviews ?? 0,
                    'price_range' => $business->price_range,
                    'is_featured' => $business->is_featured ?? false,
                    'is_verified' => $business->is_verified ?? false,
                    'is_open_now' => $business->is_open_now ?? null,
                    'category' => [
                        'id' => $business->category->id ?? null,
                        'name' => $business->category->name ?? null,
                        'slug' => $business->category->slug ?? null,
                        'icon_image' => $business->category->icon_image ?? null,
                        'color_code' => $business->category->color_code ?? null,
                    ],
                    'subcategory' => [
                        'id' => $business->subcategory->id ?? null,
                        'name' => $business->subcategory->name ?? null,
                        'slug' => $business->subcategory->slug ?? null,
                    ],
                    'images' => [
                        'logo' => $business->logoImage->image_url ?? null,
                        'cover' => $business->coverImage->image_url ?? null,
                    ],
                    'coordinates' => [
                        'latitude' => $business->latitude,
                        'longitude' => $business->longitude,
                    ],
                    'distance' => $formattedDistance,
                    'distance_km' => $distanceKm,
                    'offerings_count' => $offeringsCount,
                ];
            })->values();

            // Calculate pagination info
            $totalPages = ceil($totalBusinesses / $limit);
            $from = ($page - 1) * $limit + 1;
            $to = min($page * $limit, $totalBusinesses);

            return response()->json([
                'success' => true,
                'data' => [
                    'section' => $this->normalizeSectionSlug(strtolower($matchedCategoryName)),
                    'title' => "Top {$matchedCategoryName}",
                    'category_name' => $matchedCategoryName,
                    'description' => "Discover the best {$matchedCategoryName} in your area",
                    'businesses' => $transformedBusinesses,
                    'pagination' => [
                        'current_page' => $page,
                        'last_page' => $totalPages,
                        'per_page' => $limit,
                        'total' => $totalBusinesses,
                        'from' => $from > 0 ? $from : null,
                        'to' => $to > 0 ? $to : null,
                        'has_more' => $page < $totalPages
                    ],
                    'location' => [
                        'latitude' => $latitude,
                        'longitude' => $longitude,
                        'radius_km' => $radiusKm
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch section data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get today's trending businesses and offerings with enhanced filtering and accuracy
     * Supports multiple sorting options, filtering, and real-time data
     */
    public function todayTrending(Request $request)
    {
        try {
            // Validate and sanitize input parameters
            $request->validate([
                'latitude' => 'nullable|numeric|between:-90,90',
                'longitude' => 'nullable|numeric|between:-180,180',
                'area' => 'nullable|string|max:100',
                'category_id' => 'nullable|integer|exists:categories,id',
                'business_limit' => 'nullable|integer|min:1|max:50',
                'offering_limit' => 'nullable|integer|min:1|max:50',
                'sort_by' => 'nullable|in:trend_score,hybrid_score,rating,views,distance',
                'min_rating' => 'nullable|numeric|between:0,5',
                'max_distance' => 'nullable|numeric|min:1|max:100',
                'price_range' => 'nullable|in:$,$-$$,$$-$$$,$$$-$$$$',
                'time_period' => 'nullable|in:daily,weekly,monthly',
                'include_inactive' => 'nullable|boolean'
            ]);

            $latitude = $request->input('latitude');
            $longitude = $request->input('longitude');
            $area = $request->input('area');
            $categoryId = $request->input('category_id');
            $businessLimit = $request->input('business_limit', 15);
            $offeringLimit = $request->input('offering_limit', 10);
            $sortBy = $request->input('sort_by', 'hybrid_score');
            $minRating = $request->input('min_rating');
            $maxDistance = $request->input('max_distance', 50);
            $priceRange = $request->input('price_range');
            $timePeriod = $request->input('time_period', 'daily');
            $includeInactive = $request->input('include_inactive', false);
            
            // Determine user area if not provided using enhanced location detection
            if (!$area && $latitude && $longitude) {
                 $area = $this->locationService->determineUserAreaPrecise($latitude, $longitude);
            }

            $today = now()->format('Y-m-d');

            // Build base query for trending businesses with optimized joins
            $businessQuery = TrendingData::where('item_type', 'business')
                ->where('time_period', $timePeriod)
                ->where('date_period', $today)
                ->whereNotNull('trend_score')
                ->where('trend_score', '>', 0);

            // Apply area filter if provided (don't filter if no area specified)
            if ($area) {
                // Hybrid approach: Show businesses that are either:
                // 1. Trending among users in the specified area, OR
                // 2. Physically located in the specified area
                $businessQuery->where(function($query) use ($area) {
                    $query->where('location_area', $area) // User location
                          ->orWhereHas('business', function($subQuery) use ($area) {
                              $subQuery->where('area', $area); // Business location
                          });
                });
            }

            // Join with business table for better filtering
            $businessQuery->whereHas('business', function($query) use ($categoryId, $minRating, $priceRange, $includeInactive) {
                if (!$includeInactive) {
                    $query->where('is_active', true);
                }
                if ($categoryId) {
                    $query->where('category_id', $categoryId);
                }
                if ($minRating) {
                    $query->where('overall_rating', '>=', $minRating);
                }
                if ($priceRange) {
                    $query->where('price_range', $priceRange);
                }
            });

            // Apply sorting
            switch ($sortBy) {
                case 'trend_score':
                    $businessQuery->orderBy('trend_score', 'desc');
                    break;
                case 'hybrid_score':
                    $businessQuery->orderBy('hybrid_score', 'desc')->orderBy('trend_score', 'desc');
                    break;
                case 'rating':
                    $businessQuery->join('businesses', 'trending_data.item_id', '=', 'businesses.id')
                        ->orderBy('businesses.overall_rating', 'desc')
                        ->select('trending_data.*');
                    break;
                case 'views':
                    $businessQuery->orderBy('view_count', 'desc');
                    break;
                default:
                    $businessQuery->orderBy('hybrid_score', 'desc')->orderBy('trend_score', 'desc');
            }

            // Get trending businesses with enhanced data
            $trendingBusinessesData = $businessQuery
                ->with(['business' => function($query) use ($latitude, $longitude) {
                    $query->select(['id', 'business_name', 'slug', 'landmark', 'area', 'overall_rating', 'price_range', 'category_id', 'subcategory_id', 'latitude', 'longitude', 'business_phone', 'website_url', 'is_featured', 'is_verified'])
                          ->with([
                              'category:id,name,slug,icon_image,color_code',
                              'subcategory:id,name,slug',
                              'logoImage:id,business_id,image_url',
                              'coverImage:id,business_id,image_url',
                              'galleryImages:id,business_id,image_url'
                          ]);
                }])
                ->take($businessLimit)
                ->get();

            // Process trending businesses with distance calculation and filtering
            $trendingBusinesses = $trendingBusinessesData
                ->map(function($trend) use ($latitude, $longitude, $maxDistance) {
                    if (!$trend->business) return null;
                    
                    $business = $trend->business;
                    $distance = null;

                    // Calculate distance if coordinates provided
                    if ($latitude && $longitude && $business->latitude && $business->longitude) {
                        $distance = $this->calculateDistance(
                            $latitude, $longitude, 
                            $business->latitude, $business->longitude
                        );
                        
                        // Filter by max distance if specified
                        if ($maxDistance && $distance > $maxDistance) {
                            return null;
                        }
                    }

                    return [
                        'id' => $business->id,
                        'business_name' => $business->business_name,
                        'slug' => $business->slug,
                        'landmark' => $business->landmark,
                        'area' => $business->area,
                        'overall_rating' => $business->overall_rating,
                        'price_range' => $business->price_range,
                        'phone' => $business->business_phone,
                        'website' => $business->website_url,
                        'is_featured' => $business->is_featured,
                        'is_verified' => $business->is_verified,
                        'category' => [
                            'id' => $business->category->id ?? null,
                            'name' => $business->category->name ?? null,
                            'slug' => $business->category->slug ?? null,
                            'icon' => $business->category->icon_image ?? null,
                            'color' => $business->category->color_code ?? null,
                        ],
                        'subcategory' => [
                            'id' => $business->subcategory->id ?? null,
                            'name' => $business->subcategory->name ?? null,
                            'slug' => $business->subcategory->slug ?? null,
                        ],
                        'images' => [
                            'logo' => $business->logoImage->image_url ?? null,
                            'cover' => $business->coverImage->image_url ?? null,
                            'gallery' => $business->galleryImages->pluck('image_url')->take(5)->toArray()
                        ],
                        'trending' => [
                            'rank' => $trend->id,
                            'trend_score' => round($trend->trend_score, 2),
                            'hybrid_score' => round($trend->hybrid_score ?? 0, 2),
                            'view_count' => $trend->view_count ?? 0,
                            'search_count' => $trend->search_count ?? 0,
                            'last_updated' => $trend->updated_at,
                        ],
                        'distance' => $distance,
                        'coordinates' => [
                            'latitude' => $business->latitude,
                            'longitude' => $business->longitude,
                        ]
                    ];
                })
                ->filter()
                ->values();

            // Sort by distance if distance sorting is requested
            if ($sortBy === 'distance' && $latitude && $longitude) {
                $trendingBusinesses = $trendingBusinesses->sortBy('distance')->values();
            }

            // Build enhanced query for trending offerings
            $offeringQuery = TrendingData::where('item_type', 'offering')
                ->where('time_period', $timePeriod)
                ->where('date_period', $today)
                ->whereNotNull('trend_score')
                ->where('trend_score', '>', 0);

            // Apply area filter for offerings
            if ($area) {
                $offeringQuery->where('location_area', $area);
            }

            // Get trending offerings with enhanced data
            $trendingOfferings = $offeringQuery
                ->orderBy('hybrid_score', 'desc')
                ->orderBy('trend_score', 'desc')
                ->take($offeringLimit)
                ->get()
                ->map(function($trend) use ($latitude, $longitude, $maxDistance) {
                    // Get the offering with comprehensive business data
                    $offering = \App\Models\BusinessOffering::select(['id', 'name', 'offering_type', 'price', 'description', 'image_url', 'business_id', 'is_featured'])
                        ->with(['business' => function($query) {
                            $query->select(['id', 'business_name', 'slug', 'area', 'latitude', 'longitude', 'overall_rating', 'is_verified'])
                                  ->with([
                                      'category:id,name,slug,icon_image', 
                                      'logoImage:id,business_id,image_url',
                                      'coverImage:id,business_id,image_url'
                                  ]);
                        }])
                        ->find($trend->item_id);
                    
                    if (!$offering || !$offering->business) return null;

                    $distance = null;
                    
                    // Calculate distance and apply filter
                    if ($latitude && $longitude && $offering->business->latitude && $offering->business->longitude) {
                        $distance = $this->calculateDistance(
                            $latitude, $longitude, 
                            $offering->business->latitude, $offering->business->longitude
                        );
                        
                        if ($maxDistance && $distance > $maxDistance) {
                            return null;
                        }
                    }

                    return [
                        'id' => $offering->id,
                        'name' => $offering->name,
                        'offering_type' => $offering->offering_type,
                        'price' => $offering->price,
                        'description' => $offering->description,
                        'image_url' => $offering->image_url,
                        'is_featured' => $offering->is_featured,
                        'trending' => [
                            'rank' => $trend->id,
                            'trend_score' => round($trend->trend_score, 2),
                            'hybrid_score' => round($trend->hybrid_score ?? 0, 2),
                            'view_count' => $trend->view_count ?? 0,
                            'last_updated' => $trend->updated_at,
                        ],
                        'business' => [
                            'id' => $offering->business->id,
                            'business_name' => $offering->business->business_name,
                            'slug' => $offering->business->slug,
                            'area' => $offering->business->area,
                            'overall_rating' => $offering->business->overall_rating,
                            'is_verified' => $offering->business->is_verified,
                            'category' => [
                                'id' => $offering->business->category->id ?? null,
                                'name' => $offering->business->category->name ?? null,
                                'icon' => $offering->business->category->icon_image ?? null,
                            ],
                            'images' => [
                                'logo' => $offering->business->logoImage->image_url ?? null,
                                'cover' => $offering->business->coverImage->image_url ?? null,
                            ],
                            'distance' => $distance,
                            'coordinates' => [
                                'latitude' => $offering->business->latitude,
                                'longitude' => $offering->business->longitude,
                            ]
                        ]
                    ];
                })
                ->filter()
                ->values();

            // Get comprehensive summary statistics
            $allAreasCount = TrendingData::where('time_period', $timePeriod)
                ->where('date_period', $today)
                ->where('trend_score', '>', 0)
                ->count();

            $currentAreaCount = $area ? TrendingData::where('time_period', $timePeriod)
                ->where('date_period', $today)
                ->where('location_area', $area)
                ->where('trend_score', '>', 0)
                ->count() : $allAreasCount;

            // Get trending categories for the area
            $trendingCategories = TrendingData::where('item_type', 'business')
                ->where('time_period', $timePeriod)
                ->where('date_period', $today)
                ->when($area, function($query) use ($area) {
                    return $query->where('location_area', $area);
                })
                ->join('businesses', 'trending_data.item_id', '=', 'businesses.id')
                ->join('categories', 'businesses.category_id', '=', 'categories.id')
                ->select('categories.id', 'categories.name', 'categories.slug')
                ->selectRaw('COUNT(*) as trending_count, AVG(trending_data.trend_score) as avg_score')
                ->groupBy('categories.id', 'categories.name', 'categories.slug')
                ->orderBy('trending_count', 'desc')
                ->take(5)
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'trending_businesses' => $trendingBusinesses,
                    'trending_offerings' => $trendingOfferings,
                    'trending_categories' => $trendingCategories,
                    'summary' => [
                        'date' => $today,
                        'time_period' => $timePeriod,
                        'area' => $area,
                        'total_items_all_areas' => $allAreasCount,
                        'total_items_current_area' => $currentAreaCount,
                        'businesses_returned' => $trendingBusinesses->count(),
                        'offerings_returned' => $trendingOfferings->count(),
                        'categories_trending' => $trendingCategories->count(),
                        'filters_applied' => [
                            'category_id' => $categoryId,
                            'min_rating' => $minRating,
                            'max_distance' => $maxDistance,
                            'price_range' => $priceRange,
                            'sort_by' => $sortBy,
                        ]
                    ],
                    'location' => [
                        'provided' => [
                            'latitude' => $latitude,
                            'longitude' => $longitude,
                            'area' => $request->input('area'),
                        ],
                        'determined' => [
                            'area' => $area,
                            'coordinates_used' => $latitude && $longitude,
                        ]
                    ],
                    'meta' => [
                        'generated_at' => now()->toISOString(),
                        'cache_ttl' => 300, // 5 minutes
                        'next_update' => now()->addMinutes(30)->toISOString(),
                        'data_freshness' => 'real-time',
                    ]
                ]
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid input parameters',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Today trending API error: ' . $e->getMessage(), [
                'request' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch today\'s trending data',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Calculate distance between two coordinates in kilometers
     */
    private function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371; // Earth's radius in kilometers

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));

        return round($earthRadius * $c, 2);
    }

    /**
     * Determine category type for better organization
     */
    private function getCategoryType($categoryName)
    {
        $categoryName = strtolower($categoryName);
        
        if (strpos($categoryName, 'restaurant') !== false || 
            strpos($categoryName, 'food') !== false || 
            strpos($categoryName, 'cafe') !== false ||
            strpos($categoryName, 'pizza') !== false ||
            strpos($categoryName, 'burger') !== false) {
            return 'food';
        }
        
        if (strpos($categoryName, 'shopping') !== false || 
            strpos($categoryName, 'shop') !== false || 
            strpos($categoryName, 'store') !== false ||
            strpos($categoryName, 'clothing') !== false ||
            strpos($categoryName, 'fashion') !== false) {
            return 'retail';
        }
        
        if (strpos($categoryName, 'service') !== false || 
            strpos($categoryName, 'repair') !== false || 
            strpos($categoryName, 'maintenance') !== false) {
            return 'service';
        }
        
        if (strpos($categoryName, 'health') !== false || 
            strpos($categoryName, 'medical') !== false || 
            strpos($categoryName, 'hospital') !== false ||
            strpos($categoryName, 'pharmacy') !== false) {
            return 'healthcare';
        }
        
        return 'general';
    }

    /**
     * Check if category is restaurant/food related
     */
    private function isRestaurantCategory($categoryName)
    {
        $foodKeywords = ['restaurant', 'food', 'cafe', 'pizza', 'burger', 'dining', 'fast food', 'chinese', 'indian', 'bakery', 'sweet'];
        $categoryLower = strtolower($categoryName);
        
        foreach ($foodKeywords as $keyword) {
            if (strpos($categoryLower, $keyword) !== false) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Check if category is service related
     */
    private function isServiceCategory($categoryName)
    {
        $serviceKeywords = ['service', 'repair', 'maintenance', 'cleaning', 'consultation', 'therapy', 'training'];
        $categoryLower = strtolower($categoryName);
        
        foreach ($serviceKeywords as $keyword) {
            if (strpos($categoryLower, $keyword) !== false) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Determine display type based on category
     */
    private function getDisplayType($categoryName)
    {
        if ($this->isRestaurantCategory($categoryName)) {
            return 'menu_focused';
        }
        
        if ($this->isServiceCategory($categoryName)) {
            return 'service_focused';
        }
        
        return 'business_focused';
    }

    /**
     * Get layout type for frontend display
     */
    private function getLayoutType($categoryName)
    {
        if ($this->isRestaurantCategory($categoryName)) {
            return 'food_card';
        }
        
        $categoryType = $this->getCategoryType($categoryName);
        
        switch ($categoryType) {
            case 'retail':
                return 'retail_card';
            case 'service':
                return 'service_card';
            case 'healthcare':
                return 'healthcare_card';
            default:
                return 'standard_card';
        }
    }

    /**
     * Get all potential businesses for intelligent placement analysis
     */
    private function getAllPotentialBusinesses($latitude, $longitude, $radiusKm)
    {
        $query = Business::active()
            ->with([
                'category:id,name,slug,icon_image,color_code',
                'subcategory:id,name,slug',
                'logoImage:id,business_id,image_url',
                'coverImage:id,business_id,image_url'
            ]);

        if ($latitude && $longitude) {
            $query->nearbyWithDistance($latitude, $longitude, $radiusKm);
        }

        return $query->get();
    }

    /**
     * Get trending businesses with highest priority
     */
    private function getTrendingBusinessesForHome($userArea, $allBusinesses, &$usedBusinessIds)
    {
        $today = now()->format('Y-m-d');
        
        // Get trending data with hybrid scoring (trending + rating combination)
        $trendingData = TrendingData::where('item_type', 'business')
            ->where('time_period', 'daily')
            ->where('date_period', $today)
            ->where('location_area', $userArea)
            ->orderByRaw('COALESCE(hybrid_score, trend_score) DESC')
            ->orderBy('trend_score', 'desc')
            ->take(6)
            ->get();

        $trendingBusinesses = [];
        
        foreach ($trendingData as $trend) {
            $business = $allBusinesses->where('id', $trend->item_id)->first();
            
            // Exclude national businesses from trending - they have their own section
            if ($business && !in_array($business->id, $usedBusinessIds) && !$business->is_national) {
                $trendingBusinesses[] = [
                    'id' => $business->id,
                    'business_name' => $business->business_name,
                    'slug' => $business->slug,
                    'landmark' => $business->landmark,
                    'overall_rating' => $business->overall_rating,
                    'price_range' => $business->price_range,
                    'category_name' => $business->category->name ?? null,
                    'subcategory_name' => $business->subcategory->name ?? null,
                    'images' => [
                        'logo' => $business->logoImage->image_url ?? null,
                        'cover' => $business->coverImage->image_url ?? null,
                    ],
                    'distance' => $business->distance ?? null,
                    'trend_score' => $trend->trend_score,
                    'hybrid_score' => $trend->hybrid_score ?? ($trend->trend_score * 0.6 + ($business->overall_rating * 20) * 0.4),
                    'view_count' => $trend->view_count ?? 0,
                    'search_count' => $trend->search_count ?? 0,
                    'section_priority' => 'trending'
                ];
                
                $usedBusinessIds[] = $business->id;
            }
        }

        return $trendingBusinesses;
    }

    /**
     * Get featured businesses (excluding already used)
     */
    private function getFeaturedBusinessesForHome($latitude, $longitude, $radiusKm, $allBusinesses, &$usedBusinessIds)
    {
        // Exclude national businesses from featured - they have their own section
        $availableBusinesses = $allBusinesses->whereNotIn('id', $usedBusinessIds)
            ->where('is_featured', true)
            ->where('is_national', false); // Only local businesses

        $featuredBusinesses = [];
        
        foreach ($availableBusinesses->sortByDesc('overall_rating')->take(6) as $business) {
            $featuredBusinesses[] = [
                'id' => $business->id,
                'business_name' => $business->business_name,
                'slug' => $business->slug,
                'landmark' => $business->landmark,
                'overall_rating' => $business->overall_rating,
                'price_range' => $business->price_range,
                'category_name' => $business->category->name ?? null,
                'subcategory_name' => $business->subcategory->name ?? null,
                'images' => [
                    'logo' => $business->logoImage->image_url ?? null,
                    'cover' => $business->coverImage->image_url ?? null,
                ],
                'distance' => $business->distance ?? null,
                'section_priority' => 'featured'
            ];
            
            $usedBusinessIds[] = $business->id;
        }

        return $featuredBusinesses;
    }

    /**
     * Get popular nearby businesses (excluding already used)
     */
    private function getPopularNearbyForHome($latitude, $longitude, $radiusKm, $allBusinesses, &$usedBusinessIds)
    {
        // Exclude national businesses from popular nearby - they have their own section
        $availableBusinesses = $allBusinesses->whereNotIn('id', $usedBusinessIds)
            ->where('overall_rating', '>=', 3.5)
            ->where('is_national', false); // Only local businesses

        $popularNearby = [];
        
        foreach ($availableBusinesses->sortByDesc('overall_rating')->take(8) as $business) {
            $popularNearby[] = [
                'id' => $business->id,
                'business_name' => $business->business_name,
                'slug' => $business->slug,
                'landmark' => $business->landmark,
                'overall_rating' => $business->overall_rating,
                'price_range' => $business->price_range,
                'category_name' => $business->category->name ?? null,
                'subcategory_name' => $business->subcategory->name ?? null,
                'images' => [
                    'logo' => $business->logoImage->image_url ?? null,
                    'cover' => $business->coverImage->image_url ?? null,
                ],
                'distance' => $business->distance ?? null,
                'section_priority' => 'popular_nearby'
            ];
            
            $usedBusinessIds[] = $business->id;
        }

        return $popularNearby;
    }

    /**
     * Get dynamic sections by category (excluding already used)
     */
    private function getDynamicSectionsForHome($latitude, $longitude, $radiusKm, $allBusinesses, &$usedBusinessIds)
    {
        // Exclude national businesses from dynamic sections - they have their own section
        $availableBusinesses = $allBusinesses->whereNotIn('id', $usedBusinessIds)
            ->where('is_national', false); // Only local businesses
        $categorizedBusinesses = $availableBusinesses->groupBy('category.name');
        
        $dynamicSections = [];
        
        foreach ($categorizedBusinesses as $categoryName => $businesses) {
            if ($businesses->count() >= 2 && $categoryName) { // Only show categories with at least 2 businesses
                $sectionBusinesses = [];
                
                foreach ($businesses->sortByDesc('overall_rating')->take(6) as $business) {
                    // Get business offerings count for this business
                    $offeringsCount = 0;
                    try {
                        $offeringsCount = \App\Models\BusinessOffering::where('business_id', $business->id)
                            ->where('is_active', true)
                            ->count();
                    } catch (\Exception $e) {
                        // Fallback if offerings table doesn't exist or has issues
                        $offeringsCount = 0;
                    }
                    
                    // Format distance properly
                    $formattedDistance = null;
                    $distanceKm = null;
                    if (isset($business->distance)) {
                        $distanceKm = round($business->distance, 4); // Keep raw value with precision
                        if ($distanceKm < 1) {
                            $meters = round($distanceKm * 1000, 0);
                            $formattedDistance = $meters . 'm';
                        } else {
                            $formattedDistance = number_format($distanceKm, 1) . 'km';
                        }
                    }
                    
                    $sectionBusinesses[] = [
                        'id' => $business->id,
                        'business_name' => $business->business_name,
                        'slug' => $business->slug,
                        'landmark' => $business->landmark,
                        'area' => $business->area ?? null,
                        'address' => $business->address ?? null,
                        'business_phone' => $business->business_phone ?? null,
                        'website_url' => $business->website_url ?? null,
                        'overall_rating' => $business->overall_rating,
                        'total_reviews' => $business->total_reviews ?? 0,
                        'price_range' => $business->price_range,
                        'is_featured' => $business->is_featured ?? false,
                        'is_verified' => $business->is_verified ?? false,
                        'is_open_now' => $business->is_open_now ?? null,
                        'category' => [
                            'id' => $business->category->id ?? null,
                            'name' => $business->category->name ?? null,
                            'slug' => $business->category->slug ?? null,
                            'icon_image' => $business->category->icon_image ?? null,
                            'color_code' => $business->category->color_code ?? null,
                        ],
                        'subcategory' => [
                            'id' => $business->subcategory->id ?? null,
                            'name' => $business->subcategory->name ?? null,
                            'slug' => $business->subcategory->slug ?? null,
                        ],
                        'images' => [
                            'logo' => $business->logoImage->image_url ?? null,
                            'cover' => $business->coverImage->image_url ?? null,
                        ],
                        'coordinates' => [
                            'latitude' => $business->latitude,
                            'longitude' => $business->longitude,
                        ],
                        'distance' => $formattedDistance,
                        'distance_km' => $distanceKm,
                        'offerings_count' => $offeringsCount,
                        'section_priority' => 'dynamic_' . strtolower(str_replace(' ', '_', $categoryName)),
                        'metadata' => [
                            'category_type' => $this->getCategoryType($categoryName),
                            'has_menu' => $this->isRestaurantCategory($categoryName),
                            'has_services' => $this->isServiceCategory($categoryName),
                            'display_type' => $this->getDisplayType($categoryName)
                        ]
                    ];
                    
                    $usedBusinessIds[] = $business->id;
                }
                
                if (!empty($sectionBusinesses)) {
                    // Calculate section metrics
                    $avgRating = collect($sectionBusinesses)->avg('overall_rating');
                    $totalOfferings = collect($sectionBusinesses)->sum('offerings_count');
                    
                    $dynamicSections[] = [
                        'section_name' => "Top {$categoryName}",
                        'section_slug' => strtolower(str_replace(' ', '_', $categoryName)),
                        'section_id' => 'dynamic_' . strtolower(str_replace([' ', '&', '/'], ['_', 'and', '_'], $categoryName)),
                        'category_name' => $categoryName,
                        'category_type' => $this->getCategoryType($categoryName),
                        'display_config' => [
                            'show_ratings' => true,
                            'show_distance' => $latitude && $longitude,
                            'show_price_range' => true,
                            'show_offerings_count' => $totalOfferings > 0,
                            'layout_type' => $this->getLayoutType($categoryName)
                        ],
                        'metrics' => [
                            'count' => count($sectionBusinesses),
                            'avg_rating' => round($avgRating, 2),
                            'total_offerings' => $totalOfferings,
                            'has_verified' => collect($sectionBusinesses)->where('is_verified', true)->count() > 0
                        ],
                        'businesses' => $sectionBusinesses
                    ];
                }
            }
        }

        // Sort sections by relevance: restaurants first, then by rating and business count
        usort($dynamicSections, function($a, $b) {
            // Prioritize restaurant/food categories
            $aIsFood = $this->isRestaurantCategory($a['category_name']);
            $bIsFood = $this->isRestaurantCategory($b['category_name']);
            
            if ($aIsFood && !$bIsFood) return -1;
            if (!$aIsFood && $bIsFood) return 1;
            
            // Then sort by average rating and business count
            $aScore = ($a['metrics']['avg_rating'] * 0.7) + ($a['metrics']['count'] * 0.3);
            $bScore = ($b['metrics']['avg_rating'] * 0.7) + ($b['metrics']['count'] * 0.3);
            
            return $bScore <=> $aScore;
        });

        // Limit to top 8 dynamic sections to avoid overwhelming the user
        return array_slice($dynamicSections, 0, 8);
    }

    /**
     * Get top services (categories) based on location and user behavior
     */
    private function getTopServicesForHome($latitude, $longitude, $radiusKm, $userArea)
    {
        if ($latitude && $longitude) {
            // Get categories based on actual business availability and trending data
            return Category::active()
                ->whereHas('businesses', function ($query) use ($latitude, $longitude, $radiusKm) {
                    $query->active()->nearby($latitude, $longitude, $radiusKm);
                })
                ->withCount(['businesses' => function ($query) use ($latitude, $longitude, $radiusKm) {
                    $query->active()->nearby($latitude, $longitude, $radiusKm);
                }])
                ->with(['trendingData' => function($query) use ($userArea) {
                    $query->where('location_area', $userArea)
                          ->where('time_period', 'daily')
                          ->where('date_period', now()->format('Y-m-d'));
                }])
                ->get()
                ->map(function($category) {
                    $trendScore = $category->trendingData->sum('trend_score') ?? 0;
                    return [
                        'id' => $category->id,
                        'name' => $category->name,
                        'slug' => $category->slug,
                        'icon_image' => $category->icon_image,
                        'color_code' => $category->color_code,
                        'business_count' => $category->businesses_count,
                        'trend_score' => $trendScore,
                        'popularity_score' => ($category->businesses_count * 0.7) + ($trendScore * 0.3)
                    ];
                })
                ->sortByDesc('popularity_score')
                ->take(8)
                ->values();
        } else {
            // Fallback to featured categories with trending data
            return Category::active()
                ->featured()
                ->level(1)
                ->orderBy('sort_order')
                ->take(8)
                ->get();
        }
    }

    /**
     * Get special offers (can include used businesses as offers are separate)
     */
    private function getSpecialOffersForHome($latitude, $longitude, $radiusKm, $usedBusinessIds)
    {
        return Offer::whereHas('business', function ($query) use ($latitude, $longitude, $radiusKm) {
            $query->active();
            if ($latitude && $longitude) {
                $query->nearby($latitude, $longitude, $radiusKm);
            }
        })
            ->where('is_active', true)
            ->where('valid_from', '<=', now())
            ->where('valid_to', '>=', now())
            ->with(['business' => function($query) {
                $query->select(['id', 'business_name', 'slug', 'landmark', 'overall_rating', 'price_range', 'category_id', 'subcategory_id', 'latitude', 'longitude'])
                      ->with([
                          'category:id,name,slug,icon_image,color_code',
                          'subcategory:id,name,slug',
                          'logoImage:id,business_id,image_url'
                      ]);
            }])
            ->orderBy('created_at', 'desc')
            ->take(8)
            ->get()
            ->map(function($offer) {
                return [
                    'id' => $offer->id,
                    'title' => $offer->title,
                    'description' => $offer->description,
                    'offer_type' => $offer->offer_type,
                    'discount_percentage' => $offer->discount_percentage,
                    'valid_to' => $offer->valid_to,
                    'business' => $offer->business ? [
                        'id' => $offer->business->id,
                        'business_name' => $offer->business->business_name,
                        'slug' => $offer->business->slug,
                        'landmark' => $offer->business->landmark,
                        'overall_rating' => $offer->business->overall_rating,
                        'price_range' => $offer->business->price_range,
                        'category_name' => $offer->business->category->name ?? null,
                        'subcategory_name' => $offer->business->subcategory->name ?? null,
                        'logo_image' => $offer->business->logoImage->image_url ?? null,
                    ] : null
                ];
            })
            ->filter(function($offer) {
                return $offer['business'] !== null;
            });
    }

    /**
     * Get top national brands categorized by type (ice cream, biscuits, drinks, etc.)
     */
    private function getTopNationalBrandsForHome($usedBusinessIds = [])
    {
        try {
            $nationalBrandSections = [];

            // Define dynamic sections with their corresponding product tags
            $sectionConfigs = [
                [
                    'title' => 'Top Ice Cream Brands',
                    'type' => 'ice_cream',
                    'description' => 'Popular ice cream brands across Bangladesh',
                    'tags' => ['ice cream', 'dairy', 'frozen dessert', 'gelato', 'kulfi'],
                    'limit' => 5
                ],
                [
                    'title' => 'Top Biscuit & Snack Brands',
                    'type' => 'biscuits_snacks', 
                    'description' => 'Popular biscuit and snack brands nationwide',
                    'tags' => ['biscuit', 'cookie', 'snack', 'chips', 'crackers', 'wafer'],
                    'limit' => 5
                ],
                [
                    'title' => 'Top Beverage Brands',
                    'type' => 'beverages',
                    'description' => 'Leading beverage brands in Bangladesh',
                    'tags' => ['beverage', 'soft drink', 'juice', 'water', 'tea', 'coffee'],
                    'limit' => 4
                ],
                [
                    'title' => 'Top Food Manufacturers',
                    'type' => 'food_manufacturers',
                    'description' => 'Leading food manufacturers and processors',
                    'tags' => ['food processing', 'manufacturing', 'packaged food', 'ready meals'],
                    'limit' => 4
                ]
            ];

            $allUsedBusinessIds = $usedBusinessIds;

            foreach ($sectionConfigs as $config) {
                // Get businesses with any of the specified product tags
                $businesses = Business::active()
                    ->national()
                    ->withAnyProductTag($config['tags'])
                    ->whereNotIn('id', $allUsedBusinessIds)
                    ->with(['category', 'logoImage'])
                    ->orderByDesc('overall_rating')
                    ->orderByDesc('total_reviews')
                    ->take($config['limit'])
                    ->get();

                if ($businesses->count() > 0) {
                    $nationalBrandSections[] = [
                        'section_title' => $config['title'],
                        'section_type' => $config['type'],
                        'section_description' => $config['description'],
                        'businesses' => $businesses->map(function($business) {
                            return [
                                'id' => $business->id,
                                'business_name' => $business->business_name,
                                'slug' => $business->slug,
                                'description' => $business->description,
                                'overall_rating' => $business->overall_rating,
                                'total_reviews' => $business->total_reviews,
                                'is_national' => true,
                                'service_coverage' => $business->service_coverage,
                                'business_model' => $business->business_model,
                                'product_tags' => $business->product_tags,
                                'category' => $business->category ? [
                                    'id' => $business->category->id,
                                    'name' => $business->category->name
                                ] : null,
                                'logo_image' => $business->logoImage->image_url ?? null
                            ];
                        })
                    ];

                    // Add these business IDs to the used list for next sections
                    $allUsedBusinessIds = array_merge($allUsedBusinessIds, $businesses->pluck('id')->toArray());
                }
            }

            return $nationalBrandSections;

        } catch (\Exception $e) {
            Log::error('Failed to get national brands for home: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Track section performance for future optimization
     */
    private function trackSectionPerformance($sectionData, $userArea)
    {
        try {
            // Calculate performance metrics for each section
            $performanceData = [
                'user_area' => $userArea,
                'timestamp' => now(),
                'sections' => []
            ];

            foreach ($sectionData as $sectionName => $sectionContent) {
                $businessCount = 0;
                $avgRating = 0;
                $totalViews = 0;

                if (is_array($sectionContent)) {
                    if ($sectionName === 'dynamic_sections') {
                        // Handle dynamic sections (array of category sections)
                        foreach ($sectionContent as $dynamicSection) {
                            if (isset($dynamicSection['businesses'])) {
                                $businessCount += count($dynamicSection['businesses']);
                                $ratings = collect($dynamicSection['businesses'])->pluck('overall_rating')->filter();
                                if ($ratings->count() > 0) {
                                    $avgRating += $ratings->average();
                                }
                            }
                        }
                        if (count($sectionContent) > 0) {
                            $avgRating = $avgRating / count($sectionContent);
                        }
                    } else {
                        // Handle regular sections (direct business arrays)
                        $businessCount = count($sectionContent);
                        $ratings = collect($sectionContent)->pluck('overall_rating')->filter();
                        $avgRating = $ratings->count() > 0 ? $ratings->average() : 0;
                        $totalViews = collect($sectionContent)->sum('view_count') ?? 0;
                    }
                }

                $performanceData['sections'][$sectionName] = [
                    'business_count' => $businessCount,
                    'avg_rating' => round($avgRating, 2),
                    'total_views' => $totalViews,
                    'quality_score' => $this->calculateSectionQualityScore($businessCount, $avgRating, $totalViews)
                ];
            }

            // Log performance data for analytics
            Log::info('Home Section Performance', $performanceData);

            // Store in database for analytics (optional)
            DB::table('endpoint_analytics')->insert([
                'endpoint' => 'home_section_performance',
                'user_id' => Auth::id(),
                'user_area' => $userArea,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'additional_data' => json_encode($performanceData),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        } catch (\Exception $e) {
            Log::error("Failed to track section performance", [
                'error' => $e->getMessage(),
                'user_area' => $userArea
            ]);
        }
    }

    /**
     * Calculate section quality score based on business count, rating, and views
     */
    private function calculateSectionQualityScore($businessCount, $avgRating, $totalViews)
    {
        // Weight factors for quality calculation
        $countWeight = 0.3;
        $ratingWeight = 0.5;
        $viewWeight = 0.2;

        // Normalize values (0-100 scale)
        $countScore = min(($businessCount / 10) * 100, 100); // Max 10 businesses = 100%
        $ratingScore = ($avgRating / 5) * 100; // Max 5 rating = 100%
        $viewScore = min(($totalViews / 1000) * 100, 100); // Max 1000 views = 100%

        return round(
            ($countScore * $countWeight) + 
            ($ratingScore * $ratingWeight) + 
            ($viewScore * $viewWeight),
            2
        );
    }

    /**
     * Track user interaction for analytics
     */
    private function trackUserInteraction($action, $itemId, $area, $latitude = null, $longitude = null)
    {
        try {
            // Use the AnalyticsService for proper tracking
            switch ($action) {
                case 'home_view':
                    // Track home page view with location data
                    $this->analyticsService->logSearch(
                        searchTerm: null,
                        categoryId: null,
                        userLatitude: $latitude,
                        userLongitude: $longitude,
                        userArea: $area,
                        filtersApplied: ['action' => $action],
                        resultsCount: 0,
                        request: request()
                    );
                    break;
                    
                case 'business_view':
                    if ($itemId) {
                        $this->analyticsService->logBusinessView(
                            businessId: $itemId,
                            userLatitude: $latitude,
                            userLongitude: $longitude,
                            userArea: $area,
                            request: request()
                        );
                    }
                    break;
                    
                case 'offering_view':
                    if ($itemId) {
                        // Get offering to find business_id
                        $offering = \App\Models\BusinessOffering::find($itemId);
                        if ($offering) {
                            $this->analyticsService->logOfferingView(
                                offeringId: $itemId,
                                businessId: $offering->business_id,
                                userLatitude: $latitude,
                                userLongitude: $longitude,
                                userArea: $area,
                                request: request()
                            );
                        }
                    }
                    break;
                    
                default:
                    // Fallback to general view logging
                    if ($itemId) {
                        // Find the model type and log accordingly
                        $business = \App\Models\Business::find($itemId);
                        if ($business) {
                            $this->analyticsService->logView($business, request());
                        }
                    }
            }
            
            // Additional logging for debugging (can be removed in production)
            Log::info('User Interaction via AnalyticsService', [
                'action' => $action,
                'item_id' => $itemId,
                'area' => $area,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'timestamp' => now(),
                'date' => now()->format('Y-m-d'),
                'hour' => now()->format('H')
            ]);
        } catch (\Exception $e) {
            // Silent fail for analytics but log the error
            Log::error('Analytics tracking failed: ' . $e->getMessage());
        }
    }

    /**
     * Get analytics insights by area (Admin function)
     */
    public function getAreaAnalytics(Request $request)
    {
        try {
            $timeRange = $request->input('time_range', '7_days'); // 7_days, 30_days, 3_months
            $area = $request->input('area'); // Optional filter by specific area
            
            // Calculate date range
            $startDate = match($timeRange) {
                '7_days' => now()->subDays(7),
                '30_days' => now()->subDays(30),
                '3_months' => now()->subMonths(3),
                default => now()->subDays(7)
            };

            $query = DB::table('endpoint_analytics')
                ->where('created_at', '>=', $startDate);

            if ($area) {
                $query->where('user_area', $area);
            }

            // Get top areas by activity
            $topAreas = DB::table('endpoint_analytics')
                ->select('user_area', DB::raw('COUNT(*) as request_count'))
                ->whereNotNull('user_area')
                ->where('created_at', '>=', $startDate)
                ->groupBy('user_area')
                ->orderBy('request_count', 'desc')
                ->take(10)
                ->get();

            // Get endpoint usage by area
            $endpointsByArea = DB::table('endpoint_analytics')
                ->select('endpoint', 'user_area', DB::raw('COUNT(*) as usage_count'))
                ->whereNotNull('user_area')
                ->where('created_at', '>=', $startDate)
                ->when($area, function($q) use ($area) {
                    return $q->where('user_area', $area);
                })
                ->groupBy('endpoint', 'user_area')
                ->orderBy('usage_count', 'desc')
                ->get();

            // Get hourly patterns
            $hourlyPattern = DB::table('endpoint_analytics')
                ->select(DB::raw('HOUR(created_at) as hour'), DB::raw('COUNT(*) as requests'))
                ->where('created_at', '>=', $startDate)
                ->when($area, function($q) use ($area) {
                    return $q->where('user_area', $area);
                })
                ->groupBy(DB::raw('HOUR(created_at)'))
                ->orderBy('hour')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'time_range' => $timeRange,
                    'filtered_area' => $area,
                    'top_areas' => $topAreas,
                    'endpoint_usage_by_area' => $endpointsByArea,
                    'hourly_patterns' => $hourlyPattern,
                    'total_requests' => $query->count(),
                    'unique_areas' => DB::table('endpoint_analytics')
                        ->whereNotNull('user_area')
                        ->where('created_at', '>=', $startDate)
                        ->distinct('user_area')
                        ->count('user_area')
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch analytics',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get top-rated businesses with optional category filtering
     */
    public function topRated(Request $request)
    {
        try {
            $latitude = $request->input('latitude');
            $longitude = $request->input('longitude');
            $radiusKm = $request->input('radius', 50);
            $limit = $request->input('limit', 50);
            $categoryId = $request->input('category_id');
            $minRating = $request->input('min_rating', 4.0);

            // Determine user area and track analytics
            $userArea = $this->locationService->determineUserAreaPrecise($latitude, $longitude);
            $this->trackEndpointAnalytics('top_rated', $latitude, $longitude, [
                'radius_km' => $radiusKm,
                'limit' => $limit,
                'category_id' => $categoryId,
                'min_rating' => $minRating,
                'user_area' => $userArea
            ]);

            $query = Business::active()
                ->where('overall_rating', '>=', $minRating)
                ->with([
                    'category:id,name,slug,icon_image,color_code',
                    'subcategory:id,name,slug',
                    'logoImage:id,business_id,image_url',
                    'coverImage:id,business_id,image_url'
                ]);

            // Filter by category if provided
            if ($categoryId) {
                $query->where(function($q) use ($categoryId) {
                    $q->where('category_id', $categoryId)
                      ->orWhere('subcategory_id', $categoryId);
                });
            }

            // Add location filtering if coordinates provided
            if ($latitude && $longitude) {
                $query->nearbyWithDistance($latitude, $longitude, $radiusKm);
            }

            $businesses = $query->orderBy('overall_rating', 'desc')
                ->orderBy('total_reviews', 'desc')
                ->paginate($limit);

            // Transform the data to include images and formatted distance
            $transformedBusinesses = $businesses->getCollection()->map(function($business) use ($latitude, $longitude) {
                $businessData = [
                    'id' => $business->id,
                    'business_name' => $business->business_name,
                    'slug' => $business->slug,
                    'landmark' => $business->landmark,
                    'overall_rating' => $business->overall_rating,
                    'total_reviews' => $business->total_reviews,
                    'price_range' => $business->price_range,
                    'category_name' => $business->category->name ?? null,
                    'subcategory_name' => $business->subcategory->name ?? null,
                    'images' => [
                        'logo' => $business->logoImage->image_url ?? null,
                        'cover' => $business->coverImage->image_url ?? null,
                    ],
                    'distance_km' => null // Will be set below if coordinates provided
                ];

                // Add formatted distance if coordinates provided
                if ($latitude && $longitude && isset($business->distance)) {
                    $distanceKm = $business->distance;
                    
                    // Format distance with proper units
                    if ($distanceKm < 1) {
                        // Show in meters if less than 1 km
                        $distanceFormatted = number_format($distanceKm * 1000, 2) . ' m';
                    } else {
                        // Show in kilometers if 1 km or more
                        $distanceFormatted = number_format($distanceKm, 2) . ' km';
                    }
                    
                    $businessData['distance'] = $distanceFormatted;
                    $businessData['distance_km'] = $distanceFormatted;
                }

                return $businessData;
            });

            $businesses->setCollection($transformedBusinesses);

            return response()->json([
                'success' => true,
                'data' => [
                    'businesses' => $businesses->items(),
                    'pagination' => [
                        'current_page' => $businesses->currentPage(),
                        'last_page' => $businesses->lastPage(),
                        'per_page' => $businesses->perPage(),
                        'total' => $businesses->total(),
                        'has_more' => $businesses->hasMorePages()
                    ],
                    'filters' => [
                        'category_id' => $categoryId,
                        'min_rating' => $minRating,
                        'location' => $latitude && $longitude ? [
                            'latitude' => $latitude,
                            'longitude' => $longitude,
                            'radius_km' => $radiusKm
                        ] : null
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch top-rated businesses',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get businesses that are currently open - returns ONLY open businesses
     * Priority: Open status first, then rating, with optional category filtering
     */
    public function openNow(Request $request)
    {
        try {
            $latitude = $request->input('latitude');
            $longitude = $request->input('longitude');
            $radiusKm = $request->input('radius', 50);
            $limit = $request->input('limit', 50);
            $categoryId = $request->input('category_id'); // Restore category filtering

            // Determine user area and track analytics
            $userArea = $this->locationService->determineUserAreaPrecise($latitude, $longitude);
            $this->trackEndpointAnalytics('open_now', $latitude, $longitude, [
                'radius_km' => $radiusKm,
                'limit' => $limit,
                'category_id' => $categoryId,
                'user_area' => $userArea
            ]);

            // Use Bangladesh timezone for business hours calculation
            $bangladeshTime = now()->setTimezone('Asia/Dhaka');
            $currentDay = strtolower($bangladeshTime->format('l')); // monday, tuesday, etc.
            $currentTime = $bangladeshTime->format('H:i');

            // Base query - get active businesses with optional category filter
            $query = Business::active()
                ->with([
                    'category:id,name,slug,icon_image,color_code',
                    'subcategory:id,name,slug',
                    'logoImage:id,business_id,image_url',
                    'coverImage:id,business_id,image_url'
                ]);

            // Apply category filter if provided
            if ($categoryId) {
                $query->where(function($q) use ($categoryId) {
                    $q->where('category_id', $categoryId)
                      ->orWhere('subcategory_id', $categoryId);
                });
            }

            // Add location filtering if coordinates provided
            if ($latitude && $longitude) {
                $query->nearbyWithDistance($latitude, $longitude, $radiusKm);
            }

            // Get all businesses first, then filter in PHP for accurate opening hours check
            $allBusinesses = $query->get();

            // Filter to get ONLY businesses that are currently open
            $openBusinesses = $allBusinesses->filter(function($business) use ($currentDay, $currentTime) {
                return $this->isBusinessCurrentlyOpen($business, $currentDay, $currentTime);
            });

            // Sort by rating to show best businesses first
            $openBusinesses = $openBusinesses->sortByDesc('overall_rating');

            // Paginate the filtered results
            $perPage = $limit;
            $currentPage = $request->input('page', 1);
            $total = $openBusinesses->count();
            $offset = ($currentPage - 1) * $perPage;
            $paginatedBusinesses = $openBusinesses->slice($offset, $perPage)->values();

            // Transform the data - simplified response focused on open businesses
            $transformedBusinesses = $paginatedBusinesses->map(function($business) use ($latitude, $longitude, $currentDay, $currentTime) {
                $openingStatus = $this->getOpeningStatus($business, $currentDay, $currentTime);
                
                $businessData = [
                    'id' => $business->id,
                    'business_name' => $business->business_name,
                    'slug' => $business->slug,
                    'landmark' => $business->landmark,
                    'overall_rating' => $business->overall_rating,
                    'total_reviews' => $business->total_reviews,
                    'price_range' => $business->price_range,
                    'category_name' => $business->category->name ?? null,
                    'subcategory_name' => $business->subcategory->name ?? null,
                    'images' => [
                        'logo' => $business->logoImage->image_url ?? null,
                        'cover' => $business->coverImage->image_url ?? null,
                    ],
                    'is_open_now' => true, // All returned businesses are open
                    'opening_status' => [
                        'status' => $openingStatus['status']
                    ],
                    'closes_at' => $openingStatus['closes_at'],
                    'hours_today' => $this->getTodayHours($business, $currentDay),
                    'distance_km' => null // Will be set below if coordinates provided
                ];

                // Add distance if coordinates provided
                if ($latitude && $longitude && isset($business->distance)) {
                    $distanceKm = $business->distance;
                    
                    // Format distance with proper units
                    if ($distanceKm < 1) {
                        // Show in meters if less than 1 km
                        $distanceFormatted = number_format($distanceKm * 1000, 2) . ' m';
                    } else {
                        // Show in kilometers if 1 km or more
                        $distanceFormatted = number_format($distanceKm, 2) . ' km';
                    }
                    
                    $businessData['distance'] = $distanceFormatted;
                    $businessData['distance_km'] = $distanceFormatted; // Same formatted value with units
                }

                return $businessData;
            });

            return response()->json([
                'success' => true,
                'message' => "Found {$total} businesses currently open" . ($categoryId ? ' in selected category' : ''),
                'data' => [
                    'businesses' => $transformedBusinesses,
                    'meta' => [
                        'total_open_now' => $total,
                        'current_time' => $bangladeshTime->format('Y-m-d H:i:s'),
                        'current_time_bangladesh' => $bangladeshTime->format('Y-m-d H:i:s T'),
                        'current_day' => ucfirst($currentDay),
                        'checked_at' => $bangladeshTime->format('H:i A'),
                        'timezone' => 'Asia/Dhaka',
                        'user_area' => $userArea,
                        'user_location' => [
                            'latitude' => $latitude,
                            'longitude' => $longitude,
                            'determined_area' => $userArea
                        ],
                        'all_businesses_are_open' => true,
                        'filters_applied' => [
                            'category_id' => $categoryId,
                            'location' => $latitude && $longitude ? [
                                'latitude' => $latitude,
                                'longitude' => $longitude,
                                'radius_km' => $radiusKm
                            ] : null
                        ]
                    ],
                    'pagination' => [
                        'current_page' => $currentPage,
                        'last_page' => ceil($total / $perPage),
                        'per_page' => $perPage,
                        'total' => $total,
                        'has_more' => ($offset + $perPage) < $total
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch open businesses',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get opening status for a business
     */
    private function getOpeningStatus($business, $currentDay, $currentTime)
    {
        if (!$business->opening_hours) {
            return [
                'is_open' => true, // Assume open if no hours specified
                'status' => 'Hours not specified',
                'next_change' => null,
                'closes_at' => null,
                'opens_at' => null
            ];
        }

        // Handle both string and array formats for opening_hours
        $openingHours = $business->opening_hours;
        if (is_string($openingHours)) {
            $openingHours = json_decode($openingHours, true);
        } elseif (is_array($openingHours)) {
            // Already an array, use as is
            $openingHours = $openingHours;
        }
        
        if (!$openingHours || !isset($openingHours[$currentDay])) {
            return [
                'is_open' => true, // Assume open if no hours specified for today
                'status' => 'Hours not specified for today',
                'next_change' => null,
                'closes_at' => null,
                'opens_at' => null
            ];
        }

        $todayHours = $openingHours[$currentDay];

        // Handle the format: "7:00 AM - 10:00 PM"
        if (is_string($todayHours)) {
            $timeRange = $this->parseTimeRange($todayHours);
            if (!$timeRange) {
                return [
                    'is_open' => false,
                    'status' => 'Invalid hours format',
                    'next_change' => null,
                    'closes_at' => null,
                    'opens_at' => null
                ];
            }

            $openTime = $timeRange['open'];
            $closeTime = $timeRange['close'];
            $isOpen = $this->isTimeInRange($currentTime, $openTime, $closeTime);

            return [
                'is_open' => $isOpen,
                'status' => $isOpen ? "Open until {$this->formatTime12Hour($closeTime)}" : "Opens at {$this->formatTime12Hour($openTime)}",
                'next_change' => $isOpen ? $closeTime : $openTime,
                'closes_at' => $closeTime,
                'opens_at' => $openTime,
                'raw_hours' => $todayHours
            ];
        }

        // Handle object format with is_open, open_time, close_time, etc.
        if (is_array($todayHours)) {
            // Check if closed today
            if (!($todayHours['is_open'] ?? true)) {
                return [
                    'is_open' => false,
                    'status' => 'Closed today',
                    'next_change' => null,
                    'closes_at' => null,
                    'opens_at' => null
                ];
            }

            // Check if 24 hours
            if ($todayHours['is_24_hours'] ?? false) {
                return [
                    'is_open' => true,
                    'status' => 'Open 24 hours',
                    'next_change' => null,
                    'closes_at' => '23:59',
                    'opens_at' => '00:00'
                ];
            }

            $openTime = $todayHours['open_time'] ?? '09:00';
            $closeTime = $todayHours['close_time'] ?? '22:00';

            // Convert to 24-hour format if needed
            $openTime = $this->convertTo24Hour($openTime);
            $closeTime = $this->convertTo24Hour($closeTime);

            $isOpen = $this->isTimeInRange($currentTime, $openTime, $closeTime);

            return [
                'is_open' => $isOpen,
                'status' => $isOpen ? "Open until {$this->formatTime12Hour($closeTime)}" : "Opens at {$this->formatTime12Hour($openTime)}",
                'next_change' => $isOpen ? $closeTime : $openTime,
                'closes_at' => $closeTime,
                'opens_at' => $openTime
            ];
        }

        return [
            'is_open' => false,
            'status' => 'Unable to determine hours',
            'next_change' => null,
            'closes_at' => null,
            'opens_at' => null
        ];
    }

    /**
     * Check if a business is currently open
     */
    private function isBusinessCurrentlyOpen($business, $currentDay, $currentTime)
    {
        $status = $this->getOpeningStatus($business, $currentDay, $currentTime);
        return $status['is_open'];
    }

    /**
     * Parse time range string like "7:00 AM - 10:00 PM"
     */
    private function parseTimeRange($timeString)
    {
        if (empty($timeString) || strtolower($timeString) === 'closed') {
            return null;
        }

        // Handle "24 hours" or "24/7" cases
        if (preg_match('/24\s*(hours?|\/7)/i', $timeString)) {
            return ['open' => '00:00', 'close' => '23:59'];
        }

        // Parse "7:00 AM - 10:00 PM" format
        if (preg_match('/(.+?)\s*-\s*(.+)/', $timeString, $matches)) {
            $openTime = $this->convertTo24Hour(trim($matches[1]));
            $closeTime = $this->convertTo24Hour(trim($matches[2]));
            
            if ($openTime && $closeTime) {
                return ['open' => $openTime, 'close' => $closeTime];
            }
        }

        return null;
    }

    /**
     * Convert 12-hour format to 24-hour format
     */
    private function convertTo24Hour($timeString)
    {
        if (empty($timeString)) {
            return null;
        }

        // If already in 24-hour format (HH:MM), return as is
        if (preg_match('/^\d{1,2}:\d{2}$/', $timeString)) {
            return $timeString;
        }

        // Handle 12-hour format with AM/PM
        if (preg_match('/(\d{1,2}):(\d{2})\s*(AM|PM)/i', $timeString, $matches)) {
            $hours = (int) $matches[1];
            $minutes = $matches[2];
            $period = strtoupper($matches[3]);

            if ($period === 'AM') {
                if ($hours === 12) {
                    $hours = 0; // 12 AM = 00:00
                }
            } else { // PM
                if ($hours !== 12) {
                    $hours += 12; // Convert PM hours (except 12 PM)
                }
            }

            return sprintf('%02d:%s', $hours, $minutes);
        }

        return null;
    }

    /**
     * Check if current time is within the open range
     */
    private function isTimeInRange($currentTime, $openTime, $closeTime)
    {
        // Convert all times to minutes for easier comparison
        $currentMinutes = $this->timeToMinutes($currentTime);
        $openMinutes = $this->timeToMinutes($openTime);
        $closeMinutes = $this->timeToMinutes($closeTime);

        // Handle case where business closes after midnight (e.g., 23:00 - 02:00)
        if ($closeMinutes < $openMinutes) {
            // Business is open past midnight
            return ($currentMinutes >= $openMinutes) || ($currentMinutes <= $closeMinutes);
        } else {
            // Normal case: business opens and closes on the same day
            return ($currentMinutes >= $openMinutes) && ($currentMinutes <= $closeMinutes);
        }
    }

    /**
     * Convert time string (HH:MM) to minutes since midnight
     */
    private function timeToMinutes($timeString)
    {
        if (preg_match('/(\d{1,2}):(\d{2})/', $timeString, $matches)) {
            return ((int) $matches[1] * 60) + (int) $matches[2];
        }
        return 0;
    }

    /**
     * Format 24-hour time to 12-hour format for display
     */
    private function formatTime12Hour($time24)
    {
        if (preg_match('/(\d{1,2}):(\d{2})/', $time24, $matches)) {
            $hours = (int) $matches[1];
            $minutes = $matches[2];
            
            $period = $hours >= 12 ? 'PM' : 'AM';
            $displayHours = $hours === 0 ? 12 : ($hours > 12 ? $hours - 12 : $hours);
            
            return "{$displayHours}:{$minutes} {$period}";
        }
        return $time24;
    }

    /**
     * Get today's hours in a readable format
     */
    private function getTodayHours($business, $currentDay)
    {
        if (!$business->opening_hours) {
            return 'Hours not specified';
        }

        $openingHours = $business->opening_hours;
        if (is_string($openingHours)) {
            $openingHours = json_decode($openingHours, true);
        }

        if (!$openingHours || !isset($openingHours[$currentDay])) {
            return 'Closed today';
        }

        $todayHours = $openingHours[$currentDay];

        // If it's already a formatted string, return it
        if (is_string($todayHours)) {
            return $todayHours;
        }

        // If it's an array/object format
        if (is_array($todayHours)) {
            if (!($todayHours['is_open'] ?? true)) {
                return 'Closed today';
            }

            if ($todayHours['is_24_hours'] ?? false) {
                return '24 hours';
            }

            $openTime = $this->formatTime12Hour($this->convertTo24Hour($todayHours['open_time'] ?? '09:00'));
            $closeTime = $this->formatTime12Hour($this->convertTo24Hour($todayHours['close_time'] ?? '22:00'));

            return "{$openTime} - {$closeTime}";
        }

        return 'Hours not specified';
    }

    /**
     * Get detailed location insights for a given coordinate
     * Uses the enhanced LocationService for precise area detection
     */
    public function getLocationInsights(Request $request)
    {
        try {
            $latitude = $request->input('latitude');
            $longitude = $request->input('longitude');

            if (!$latitude || !$longitude) {
                return response()->json([
                    'success' => false,
                    'message' => 'Latitude and longitude are required'
                ], 422);
            }

            // Check if coordinates are within Bangladesh
            if (!$this->locationService->isWithinBangladesh($latitude, $longitude)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Coordinates are outside Bangladesh boundaries'
                ], 422);
            }

            // Get detailed location insights
            $insights = $this->locationService->getAreaInsights($latitude, $longitude);

            // Track this API call
            $this->trackEndpointAnalytics('location_insights', $latitude, $longitude, $insights);

            // Get business count in this area
            $businessCount = Business::active()
                ->nearbyWithDistance($latitude, $longitude, 5) // 5km radius
                ->count();

            // Get top categories in this area
            $topCategories = Business::active()
                ->nearbyWithDistance($latitude, $longitude, 10) // 10km radius
                ->with('category:id,name')
                ->get()
                ->groupBy('category.name')
                ->map(function ($businesses) {
                    return $businesses->count();
                })
                ->sortDesc()
                ->take(5);

            return response()->json([
                'success' => true,
                'data' => [
                    'location_details' => $insights,
                    'business_statistics' => [
                        'total_businesses_5km' => $businessCount,
                        'top_categories_10km' => $topCategories
                    ],
                    'coordinate_validation' => [
                        'is_within_bangladesh' => true,
                        'coordinate_quality' => 'Valid'
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Location insights error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get location insights'
            ], 500);
        }
    }

    /**
     * Get area-based business recommendations
     * Uses LocationService for precise area detection and targeted recommendations
     */
    public function getAreaBasedRecommendations(Request $request)
    {
        try {
            $latitude = $request->input('latitude');
            $longitude = $request->input('longitude');
            $radiusKm = $request->input('radius', 15);
            $limit = $request->input('limit', 20);

            if (!$latitude || !$longitude) {
                return response()->json([
                    'success' => false,
                    'message' => 'Latitude and longitude are required'
                ], 422);
            }

            // Get precise area information
            $userArea = $this->locationService->determineUserAreaPrecise($latitude, $longitude);
            $insights = $this->locationService->getAreaInsights($latitude, $longitude);

            // Track this API call
            $this->trackEndpointAnalytics('area_recommendations', $latitude, $longitude, [
                'radius_km' => $radiusKm,
                'limit' => $limit,
                'user_area' => $userArea,
                'precision_level' => $insights['precision_level'] ?? 'Unknown'
            ]);

            // Get businesses with area-specific scoring
            $businesses = Business::active()
                ->nearbyWithDistance($latitude, $longitude, $radiusKm)
                ->withRating(3.0) // Minimum rating threshold
                ->with([
                    'category:id,name,slug,icon_image,color_code',
                    'subcategory:id,name,slug',
                    'logoImage:id,business_id,image_url',
                    // 'bannerImages:id,business_id,image_url'
                ])
                ->get()
                ->map(function ($business) {
                    // Add area-specific scoring logic here
                    $areaScore = $this->calculateAreaSpecificScore($business);
                    
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
                        // 'banner_image' => $business->bannerImages->first()->image_url ?? null,
                        'area_relevance_score' => $areaScore,
                        'recommendation_reason' => $this->getRecommendationReason($business, $areaScore)
                    ];
                })
                ->sortByDesc('area_relevance_score')
                ->take($limit)
                ->values();

            return response()->json([
                'success' => true,
                'data' => [
                    'user_location' => $insights,
                    'recommendations' => $businesses,
                    'metadata' => [
                        'total_found' => $businesses->count(),
                        'search_radius_km' => $radiusKm,
                        'area_based_scoring' => true
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Area recommendations error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get area-based recommendations'. $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calculate area-specific relevance score for businesses
     */
    private function calculateAreaSpecificScore($business)
    {
        $score = 0;

        // Base score from rating (0-40 points)
        $score += ($business->overall_rating / 5) * 40;

        // Review count factor (0-20 points)
        $reviewScore = min(($business->total_reviews / 50) * 20, 20);
        $score += $reviewScore;

        // Distance factor (0-20 points, closer is better)
        $distanceScore = max(0, 20 - ($business->distance_km * 2));
        $score += $distanceScore;

        // Featured business bonus (0-10 points)
        if ($business->is_featured) {
            $score += 10;
        }

        // Price range consideration (0-10 points, mid-range gets bonus)
        if ($business->price_range === 'medium') {
            $score += 10;
        } elseif (in_array($business->price_range, ['low', 'high'])) {
            $score += 5;
        }

        return round($score, 2);
    }

    /**
     * Get recommendation reason based on score
     */
    private function getRecommendationReason($business, $score)
    {
        if ($score >= 80) {
            return 'Highly recommended in your area';
        } elseif ($score >= 60) {
            return 'Popular choice nearby';
        } elseif ($business->is_featured) {
            return 'Featured business in your area';
        } elseif ($business->distance_km <= 2) {
            return 'Very close to your location';
        } elseif ($business->overall_rating >= 4.5) {
            return 'Highly rated by customers';
        } else {
            return 'Available in your area';
        }
    }

    /**
     * Get division-wise business analytics
     * Administrative endpoint for insights across all Bangladesh divisions
     */
    public function getDivisionAnalytics(Request $request)
    {
        try {
            $timeframe = $request->input('timeframe', 'last_30_days');
            
            // Calculate date range
            $endDate = now();
            $startDate = match($timeframe) {
                'today' => now()->startOfDay(),
                'last_7_days' => now()->subDays(7),
                'last_30_days' => now()->subDays(30),
                'last_90_days' => now()->subDays(90),
                default => now()->subDays(30)
            };

            // Get all businesses with their coordinates
            $businesses = Business::active()
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->with('category:id,name')
                ->get();

            // Categorize businesses by division using LocationService
            $divisionStats = [];
            
            foreach ($businesses as $business) {
                $insights = $this->locationService->getAreaInsights($business->latitude, $business->longitude);
                $division = $insights['division'] ?? 'Unknown';
                
                if (!isset($divisionStats[$division])) {
                    $divisionStats[$division] = [
                        'total_businesses' => 0,
                        'categories' => [],
                        'avg_rating' => 0,
                        'total_reviews' => 0,
                        'featured_count' => 0
                    ];
                }
                
                $divisionStats[$division]['total_businesses']++;
                $divisionStats[$division]['avg_rating'] += $business->overall_rating;
                $divisionStats[$division]['total_reviews'] += $business->total_reviews;
                
                if ($business->is_featured) {
                    $divisionStats[$division]['featured_count']++;
                }
                
                // Track categories
                $categoryName = $business->category->name ?? 'Uncategorized';
                $divisionStats[$division]['categories'][$categoryName] = 
                    ($divisionStats[$division]['categories'][$categoryName] ?? 0) + 1;
            }

            // Calculate averages
            foreach ($divisionStats as &$stats) {
                if ($stats['total_businesses'] > 0) {
                    $stats['avg_rating'] = round($stats['avg_rating'] / $stats['total_businesses'], 2);
                }
                // Sort categories by count
                arsort($stats['categories']);
                $stats['top_categories'] = array_slice($stats['categories'], 0, 5, true);
                unset($stats['categories']); // Remove full list to reduce response size
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'timeframe' => $timeframe,
                    'period' => [
                        'start_date' => $startDate->format('Y-m-d H:i:s'),
                        'end_date' => $endDate->format('Y-m-d H:i:s')
                    ],
                    'division_statistics' => $divisionStats,
                    'summary' => [
                        'total_divisions_with_businesses' => count($divisionStats),
                        'total_businesses_analyzed' => $businesses->count(),
                        'most_active_division' => collect($divisionStats)->sortByDesc('total_businesses')->keys()->first()
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Division analytics error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate division analytics'
            ], 500);
        }
    }

    /**
     * Get national brands for home page display
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function nationalBrands(Request $request)
    {
        try {
            // If specific section type is requested, provide redirect information
            if ($request->has('section_type') || $request->has('item_type')) {
                $itemType = $request->input('section_type') ?: $request->input('item_type');
                
                return response()->json([
                    'success' => true,
                    'message' => 'Use the businesses/national endpoint for filtered results',
                    'redirect' => [
                        'endpoint' => '/api/v1/businesses/national',
                        'parameters' => [
                            'item_type' => $itemType,
                            'page' => $request->input('page', 1),
                            'limit' => $request->input('limit', 20),
                            'sort' => $request->input('sort', 'rating')
                        ],
                        'full_url' => "/api/v1/businesses/national?item_type={$itemType}&page=" . $request->input('page', 1) . "&limit=" . $request->input('limit', 20)
                    ]
                ]);
            }
            
            // Default behavior: return all sections summary
            $nationalBrands = $this->getTopNationalBrandsForHome();

            return response()->json([
                'success' => true,
                'message' => 'National brands retrieved successfully',
                'data' => [
                    'national_brand_sections' => $nationalBrands,
                    'total_sections' => count($nationalBrands),
                    'total_businesses' => collect($nationalBrands)->sum(function($section) {
                        return count($section['businesses']);
                    }),
                    'view_all_endpoints' => [
                        'ice_cream' => '/api/v1/businesses/national?item_type=ice_cream',
                        'biscuits_snacks' => '/api/v1/businesses/national?item_type=biscuits_snacks',
                        'beverages' => '/api/v1/businesses/national?item_type=beverages', 
                        'food_processing' => '/api/v1/businesses/national?item_type=food_processing'
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get national brands: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve national brands',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get featured attractions for home page display
     */
    private function getFeaturedAttractionsForHome($latitude = null, $longitude = null, $radiusKm = 50)
    {
        try {
            $query = Attraction::with(['coverImage', 'reviews' => function($q) {
                $q->active()->latest()->take(3);
            }])
            ->active()
            ->verified()
            ->featured();

            // If location provided, prioritize nearby attractions but still show some featured ones
            if ($latitude && $longitude) {
                $query = $query->selectRaw(
                    "attractions.*, 
                    ( 6371 * acos( cos( radians(?) ) * 
                      cos( radians( latitude ) ) * 
                      cos( radians( longitude ) - radians(?) ) + 
                      sin( radians(?) ) * 
                      sin( radians( latitude ) ) ) ) AS distance", 
                    [$latitude, $longitude, $latitude]
                )
                ->having('distance', '<', $radiusKm)
                ->orderBy('distance')
                ->orderBy('overall_rating', 'desc');
            } else {
                // Without location, prioritize by rating and discovery score
                $query = $query->orderBy('discovery_score', 'desc')
                              ->orderBy('overall_rating', 'desc');
            }

            $attractions = $query->take(6)->get();

            return $attractions->map(function ($attraction) use ($latitude, $longitude) {
                return [
                    'id' => $attraction->id,
                    'name' => $attraction->name,
                    'slug' => $attraction->slug,
                    'description' => $attraction->description,
                    'type' => $attraction->type,
                    'category' => $attraction->category,
                    'subcategory' => $attraction->subcategory,
                    'city' => $attraction->city,
                    'area' => $attraction->area,
                    'district' => $attraction->district,
                    'is_free' => $attraction->is_free,
                    'entry_fee' => $attraction->entry_fee,
                    'currency' => $attraction->currency,
                    'overall_rating' => (float) $attraction->overall_rating,
                    'total_reviews' => $attraction->total_reviews,
                    'total_views' => $attraction->total_views,
                    'discovery_score' => (float) $attraction->discovery_score,
                    'estimated_duration_minutes' => $attraction->estimated_duration_minutes,
                    'difficulty_level' => $attraction->difficulty_level,
                    'cover_image_url' => $attraction->cover_image_url,
                    'google_maps_url' => $attraction->google_maps_url,
                    'distance_km' => $latitude && $longitude && isset($attraction->distance) 
                        ? round($attraction->distance, 1) 
                        : null,
                    'facilities' => $attraction->facilities,
                    'best_time_to_visit' => $attraction->best_time_to_visit,
                    'is_featured' => true,
                    'recent_reviews_count' => $attraction->reviews->count(),
                ];
            })->toArray();

        } catch (\Exception $e) {
            Log::error('Failed to get featured attractions for home: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get popular attractions nearby for home page display
     */
    private function getPopularAttractionsForHome($latitude, $longitude, $radiusKm = 30)
    {
        try {
            $attractions = Attraction::with(['coverImage', 'reviews' => function($q) {
                $q->active()->latest()->take(2);
            }])
            ->active()
            ->verified()
            ->selectRaw(
                "attractions.*, 
                ( 6371 * acos( cos( radians(?) ) * 
                  cos( radians( latitude ) ) * 
                  cos( radians( longitude ) - radians(?) ) + 
                  sin( radians(?) ) * 
                  sin( radians( latitude ) ) ) ) AS distance", 
                [$latitude, $longitude, $latitude]
            )
            ->having('distance', '<', $radiusKm)
            ->where('total_views', '>', 100) // Popular threshold
            ->orderBy('discovery_score', 'desc')
            ->orderBy('total_views', 'desc')
            ->orderBy('distance')
            ->take(8)
            ->get();

            return $attractions->map(function ($attraction) {
                return [
                    'id' => $attraction->id,
                    'name' => $attraction->name,
                    'slug' => $attraction->slug,
                    'description' => $attraction->description,
                    'type' => $attraction->type,
                    'category' => $attraction->category,
                    'subcategory' => $attraction->subcategory,
                    'city' => $attraction->city,
                    'area' => $attraction->area,
                    'district' => $attraction->district,
                    'is_free' => $attraction->is_free,
                    'entry_fee' => $attraction->entry_fee,
                    'currency' => $attraction->currency,
                    'overall_rating' => (float) $attraction->overall_rating,
                    'total_reviews' => $attraction->total_reviews,
                    'total_views' => $attraction->total_views,
                    'discovery_score' => (float) $attraction->discovery_score,
                    'estimated_duration_minutes' => $attraction->estimated_duration_minutes,
                    'difficulty_level' => $attraction->difficulty_level,
                    'cover_image_url' => $attraction->cover_image_url,
                    'google_maps_url' => $attraction->google_maps_url,
                    'distance_km' => round($attraction->distance, 1),
                    'facilities' => $attraction->facilities,
                    'best_time_to_visit' => $attraction->best_time_to_visit,
                    'is_popular' => true,
                    'recent_reviews_count' => $attraction->reviews->count(),
                ];
            })->toArray();

        } catch (\Exception $e) {
            Log::error('Failed to get popular attractions for home: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get featured attractions endpoint (View All)
     */
    public function featuredAttractions(Request $request)
    {
        try {
            $latitude = $request->input('latitude');
            $longitude = $request->input('longitude');
            $radiusKm = $request->input('radius', 100);
            $perPage = min($request->input('per_page', 20), 50);

            $query = Attraction::with(['coverImage', 'reviews' => function($q) {
                $q->active()->latest()->take(3);
            }])
            ->active()
            ->verified()
            ->featured();

            if ($latitude && $longitude) {
                $query = $query->selectRaw(
                    "attractions.*, 
                    ( 6371 * acos( cos( radians(?) ) * 
                      cos( radians( latitude ) ) * 
                      cos( radians( longitude ) - radians(?) ) + 
                      sin( radians(?) ) * 
                      sin( radians( latitude ) ) ) ) AS distance", 
                    [$latitude, $longitude, $latitude]
                )
                ->having('distance', '<', $radiusKm)
                ->orderBy('distance')
                ->orderBy('overall_rating', 'desc');
            } else {
                $query = $query->orderBy('discovery_score', 'desc')
                              ->orderBy('overall_rating', 'desc');
            }

            $attractions = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Featured attractions retrieved successfully',
                'data' => $attractions
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get featured attractions: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve featured attractions',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get popular attractions endpoint (View All)
     */
    public function popularAttractions(Request $request)
    {
        try {
            $latitude = $request->input('latitude');
            $longitude = $request->input('longitude');
            $radiusKm = $request->input('radius', 50);
            $perPage = min($request->input('per_page', 20), 50);

            if (!$latitude || !$longitude) {
                return response()->json([
                    'success' => false,
                    'message' => 'Location coordinates are required for popular attractions'
                ], 422);
            }

            $attractions = Attraction::with(['coverImage', 'reviews' => function($q) {
                $q->active()->latest()->take(3);
            }])
            ->active()
            ->verified()
            ->selectRaw(
                "attractions.*, 
                ( 6371 * acos( cos( radians(?) ) * 
                  cos( radians( latitude ) ) * 
                  cos( radians( longitude ) - radians(?) ) + 
                  sin( radians(?) ) * 
                  sin( radians( latitude ) ) ) ) AS distance", 
                [$latitude, $longitude, $latitude]
            )
            ->having('distance', '<', $radiusKm)
            ->where('total_views', '>', 50)
            ->orderBy('discovery_score', 'desc')
            ->orderBy('total_views', 'desc')
            ->orderBy('distance')
            ->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Popular attractions retrieved successfully',
                'data' => $attractions
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get popular attractions: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve popular attractions',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get all attractions in current location (View All)
     * This is the comprehensive endpoint for "View All" attractions near user location
     */
    public function allAttractionsNearby(Request $request)
    {
        try {
            $latitude = $request->input('latitude');
            $longitude = $request->input('longitude');
            $radiusKm = $request->input('radius', 50);
            $perPage = min($request->input('per_page', 20), 50);
            $category = $request->input('category');
            $type = $request->input('type');
            $minRating = $request->input('min_rating', 0);
            $maxEntryFee = $request->input('max_entry_fee');
            $isFree = $request->input('is_free');
            $difficultyLevel = $request->input('difficulty_level');
            $sortBy = $request->input('sort_by', 'distance'); // distance, rating, popularity, newest
            
            if (!$latitude || !$longitude) {
                return response()->json([
                    'success' => false,
                    'message' => 'Location coordinates (latitude and longitude) are required'
                ], 422);
            }

            $query = Attraction::with(['coverImage', 'galleries' => function($q) {
                $q->where('is_active', true)->take(3);
            }, 'reviews' => function($q) {
                $q->active()->latest()->take(5);
            }])
            ->active()
            ->selectRaw(
                "attractions.*, 
                ( 6371 * acos( cos( radians(?) ) * 
                  cos( radians( latitude ) ) * 
                  cos( radians( longitude ) - radians(?) ) + 
                  sin( radians(?) ) * 
                  sin( radians( latitude ) ) ) ) AS distance", 
                [$latitude, $longitude, $latitude]
            )
            ->having('distance', '<', $radiusKm);

            // Apply filters
            if ($category) {
                $query->where('category', 'like', '%' . $category . '%');
            }

            if ($type) {
                $query->where('type', $type);
            }

            if ($minRating > 0) {
                $query->where('overall_rating', '>=', $minRating);
            }

            if ($isFree !== null) {
                $query->where('is_free', filter_var($isFree, FILTER_VALIDATE_BOOLEAN));
            }

            if ($maxEntryFee && $isFree !== 'true') {
                $query->where('entry_fee', '<=', $maxEntryFee);
            }

            if ($difficultyLevel) {
                $query->where('difficulty_level', $difficultyLevel);
            }

            // Apply sorting
            switch ($sortBy) {
                case 'rating':
                    $query->orderBy('overall_rating', 'desc')
                          ->orderBy('distance');
                    break;
                case 'popularity':
                    $query->orderBy('discovery_score', 'desc')
                          ->orderBy('total_views', 'desc')
                          ->orderBy('distance');
                    break;
                case 'newest':
                    $query->orderBy('created_at', 'desc')
                          ->orderBy('distance');
                    break;
                case 'featured':
                    $query->orderBy('is_featured', 'desc')
                          ->orderBy('overall_rating', 'desc')
                          ->orderBy('distance');
                    break;
                case 'distance':
                default:
                    $query->orderBy('distance')
                          ->orderBy('overall_rating', 'desc');
                    break;
            }

            $attractions = $query->paginate($perPage);

            // Transform the data to include additional calculated fields
            $attractions->getCollection()->transform(function ($attraction) {
                return [
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
                        'entry_fee' => $attraction->entry_fee,
                        'currency' => $attraction->currency,
                    ],
                    'ratings' => [
                        'overall_rating' => (float) $attraction->overall_rating,
                        'total_reviews' => $attraction->total_reviews,
                        'recent_reviews' => $attraction->reviews->map(function($review) {
                            return [
                                'id' => $review->id,
                                'rating' => (float) $review->rating,
                                'comment' => substr($review->comment, 0, 100) . (strlen($review->comment) > 100 ? '...' : ''),
                                'user_name' => $review->is_anonymous ? 'Anonymous' : $review->user->name,
                                'created_at' => $review->created_at->diffForHumans(),
                            ];
                        })
                    ],
                    'visit_info' => [
                        'estimated_duration_minutes' => $attraction->estimated_duration_minutes,
                        'difficulty_level' => $attraction->difficulty_level,
                        'best_time_to_visit' => $attraction->best_time_to_visit,
                        'facilities' => $attraction->facilities,
                        'opening_hours' => $attraction->opening_hours,
                    ],
                    'media' => [
                        'cover_image_url' => $attraction->cover_image_url,
                        'gallery_images' => $attraction->galleries->map(function($gallery) {
                            return [
                                'id' => $gallery->id,
                                'image_url' => $gallery->full_image_url,
                                'title' => $gallery->title,
                                'is_cover' => $gallery->is_cover,
                            ];
                        }),
                        'gallery_count' => $attraction->gallery_count,
                    ],
                    'stats' => [
                        'total_views' => $attraction->total_views,
                        'total_likes' => $attraction->total_likes,
                        'total_shares' => $attraction->total_shares,
                        'discovery_score' => (float) $attraction->discovery_score,
                    ],
                    'flags' => [
                        'is_featured' => $attraction->is_featured,
                        'is_verified' => $attraction->is_verified,
                        'is_free' => $attraction->is_free,
                    ],
                    'distance_km' => round($attraction->distance, 1),
                    'google_maps_url' => $attraction->google_maps_url,
                    'contact_info' => $attraction->contact_info,
                    'accessibility_info' => $attraction->accessibility_info,
                ];
            });

            // Track this API call for analytics
            $this->trackEndpointAnalytics('attractions_view_all', $latitude, $longitude, [
                'radius_km' => $radiusKm,
                'total_found' => $attractions->total(),
                'filters_applied' => [
                    'category' => $category,
                    'type' => $type,
                    'min_rating' => $minRating,
                    'is_free' => $isFree,
                    'max_entry_fee' => $maxEntryFee,
                    'difficulty_level' => $difficultyLevel,
                    'sort_by' => $sortBy,
                ]
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Nearby attractions retrieved successfully',
                'data' => $attractions,
                'filters' => [
                    'location' => [
                        'latitude' => (float) $latitude,
                        'longitude' => (float) $longitude,
                        'radius_km' => $radiusKm,
                    ],
                    'applied_filters' => array_filter([
                        'category' => $category,
                        'type' => $type,
                        'min_rating' => $minRating,
                        'is_free' => $isFree,
                        'max_entry_fee' => $maxEntryFee,
                        'difficulty_level' => $difficultyLevel,
                    ]),
                    'sort_by' => $sortBy,
                ],
                'available_filters' => [
                    'categories' => ['Beach', 'Historical', 'Nature', 'Wildlife', 'Archaeological', 'Cultural', 'Adventure', 'Religious'],
                    'types' => ['attraction', 'activity', 'spot'],
                    'difficulty_levels' => ['easy', 'moderate', 'challenging', 'expert'],
                    'sort_options' => ['distance', 'rating', 'popularity', 'newest', 'featured'],
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get attractions nearby: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve nearby attractions',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get attraction categories and stats for current location
     */
    public function attractionCategoriesNearby(Request $request)
    {
        try {
            $latitude = $request->input('latitude');
            $longitude = $request->input('longitude');
            $radiusKm = $request->input('radius', 50);

            if (!$latitude || !$longitude) {
                return response()->json([
                    'success' => false,
                    'message' => 'Location coordinates are required'
                ], 422);
            }

            // Get all attractions within radius first, then aggregate in PHP to avoid MySQL GROUP BY issues
            $nearbyAttractions = Attraction::select([
                    'id', 'category', 'type', 'overall_rating', 'is_free', 'entry_fee',
                    DB::raw("( 6371 * acos( cos( radians($latitude) ) * 
                      cos( radians( latitude ) ) * 
                      cos( radians( longitude ) - radians($longitude) ) + 
                      sin( radians($latitude) ) * 
                      sin( radians( latitude ) ) ) ) AS distance")
                ])
                ->active()
                ->having('distance', '<', $radiusKm)
                ->get();

            // Group and aggregate using Laravel collections
            $categories = $nearbyAttractions->groupBy(['category', 'type'])->map(function ($typeGroup, $category) {
                return $typeGroup->map(function ($attractions, $type) use ($category) {
                    $freeCount = $attractions->where('is_free', true)->count();
                    $paidAttractions = $attractions->where('is_free', false);
                    
                    return [
                        'category' => $category,
                        'type' => $type,
                        'count' => $attractions->count(),
                        'avg_rating' => $attractions->avg('overall_rating') ? round($attractions->avg('overall_rating'), 1) : 0,
                        'free_count' => $freeCount,
                        'paid_count' => $attractions->count() - $freeCount,
                        'min_entry_fee' => $paidAttractions->count() > 0 ? $paidAttractions->min('entry_fee') : null,
                        'max_entry_fee' => $paidAttractions->count() > 0 ? $paidAttractions->max('entry_fee') : null,
                    ];
                });
            })->flatten(1)->sortByDesc('count')->values();

            return response()->json([
                'success' => true,
                'message' => 'Attraction categories retrieved successfully',
                'data' => [
                    'categories' => $categories,
                    'summary' => [
                        'total_categories' => $categories->groupBy('category')->count(),
                        'total_attractions' => $categories->sum('count'),
                        'average_rating' => $categories->count() > 0 ? round($categories->avg('avg_rating'), 1) : 0,
                        'free_attractions' => $categories->sum('free_count'),
                    ],
                    'location' => [
                        'latitude' => (float) $latitude,
                        'longitude' => (float) $longitude,
                        'radius_km' => $radiusKm,
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get attraction categories: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve attraction categories',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Normalize section slug to handle different formats
     * Converts both "&" and "and" variations to a consistent format
     */
    private function normalizeSectionSlug(string $text): string
    {
        // First normalize all variations of "&" and "and"
        $normalized = preg_replace('/\s*&\s*/', '_and_', $text);
        $normalized = preg_replace('/\s+and\s+/', '_and_', $normalized);
        
        // Then handle other special characters
        $normalized = str_replace([' ', '/', '-', '.', ','], '_', $normalized);
        
        // Remove multiple underscores
        $normalized = preg_replace('/_+/', '_', $normalized);
        
        // Trim underscores from start and end
        return trim($normalized, '_');
    }
}