<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Business;
use App\Models\FeaturedSection;
use App\Models\Banner;
use App\Models\Offer;
use App\Models\Review;
use App\Models\TrendingData;
use App\Services\AnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class HomeController extends Controller
{
    protected $analyticsService;

    public function __construct(AnalyticsService $analyticsService)
    {
        $this->analyticsService = $analyticsService;
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

            // Determine user area from coordinates or use default
            $userArea = $this->determineUserArea($latitude, $longitude);

            // Track this API call for analytics
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

            // Get all potential businesses for analysis
            $allBusinesses = $this->getAllPotentialBusinesses($latitude, $longitude, $radiusKm);

            // 1. PRIORITY 1: Get trending businesses (highest priority)
            $trendingBusinesses = $this->getTrendingBusinessesForHome($userArea, $allBusinesses, $usedBusinessIds);
            $sectionData['trending_businesses'] = $trendingBusinesses;

            // 2. PRIORITY 2: Get featured businesses (excluding already used)
            $featuredBusinesses = $this->getFeaturedBusinessesForHome($latitude, $longitude, $radiusKm, $allBusinesses, $usedBusinessIds);
            $sectionData['featured_businesses'] = $featuredBusinesses;

            // 3. PRIORITY 3: Get popular nearby (excluding already used)
            $popularNearby = [];
            if ($latitude && $longitude) {
                $popularNearby = $this->getPopularNearbyForHome($latitude, $longitude, $radiusKm, $allBusinesses, $usedBusinessIds);
            }
            $sectionData['popular_nearby'] = $popularNearby;

            // 4. PRIORITY 4: Get dynamic sections by category (excluding already used)
            $dynamicSections = [];
            if ($latitude && $longitude) {
                $dynamicSections = $this->getDynamicSectionsForHome($latitude, $longitude, $radiusKm, $allBusinesses, $usedBusinessIds);
            }
            $sectionData['dynamic_sections'] = $dynamicSections;

            // 5. Get top services (categories) - dynamic based on location and user behavior
            $topServices = $this->getTopServicesForHome($latitude, $longitude, $radiusKm, $userArea);

            // 6. Get special offers
            $specialOffers = $this->getSpecialOffersForHome($latitude, $longitude, $radiusKm, $usedBusinessIds);

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
                    'analytics' => [
                        'total_businesses_shown' => count($usedBusinessIds),
                        'unique_business_placement' => true,
                        'location_based' => $latitude && $longitude ? true : false,
                        'trending_data_driven' => true
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
            $userArea = $this->determineUserArea($latitude, $longitude);

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
            $userArea = $this->determineUserArea($latitude, $longitude);
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
    private function determineUserArea($latitude, $longitude)
    {
        if (!$latitude || !$longitude) {
            return 'Dhanmondi'; // Default area
        }

        // Define Bangladesh area boundaries (approximate)
        $bangladeshAreas = [
            // Dhaka areas
            'Dhanmondi' => [
                'lat_min' => 23.740, 'lat_max' => 23.755,
                'lng_min' => 90.365, 'lng_max' => 90.380
            ],
            'Gulshan' => [
                'lat_min' => 23.780, 'lat_max' => 23.800,
                'lng_min' => 90.405, 'lng_max' => 90.425
            ],
            'Banani' => [
                'lat_min' => 23.785, 'lat_max' => 23.795,
                'lng_min' => 90.395, 'lng_max' => 90.410
            ],
            'Uttara' => [
                'lat_min' => 23.855, 'lat_max' => 23.885,
                'lng_min' => 90.395, 'lng_max' => 90.420
            ],
            'Mirpur' => [
                'lat_min' => 23.795, 'lat_max' => 23.825,
                'lng_min' => 90.345, 'lng_max' => 90.375
            ],
            'Old Dhaka' => [
                'lat_min' => 23.700, 'lat_max' => 23.725,
                'lng_min' => 90.395, 'lng_max' => 90.420
            ],
            'Wari' => [
                'lat_min' => 23.715, 'lat_max' => 23.725,
                'lng_min' => 90.410, 'lng_max' => 90.425
            ],
            'Motijheel' => [
                'lat_min' => 23.725, 'lat_max' => 23.735,
                'lng_min' => 90.410, 'lng_max' => 90.425
            ],
            'Tejgaon' => [
                'lat_min' => 23.755, 'lat_max' => 23.770,
                'lng_min' => 90.395, 'lng_max' => 90.415
            ],
            'Farmgate' => [
                'lat_min' => 23.755, 'lat_max' => 23.765,
                'lng_min' => 90.385, 'lng_max' => 90.395
            ],
            'Mohammadpur' => [
                'lat_min' => 23.760, 'lat_max' => 23.775,
                'lng_min' => 90.355, 'lng_max' => 90.370
            ],
            'Bashundhara' => [
                'lat_min' => 23.810, 'lat_max' => 23.830,
                'lng_min' => 90.425, 'lng_max' => 90.445
            ],
            
            // Major cities outside Dhaka
            'Chittagong' => [
                'lat_min' => 22.320, 'lat_max' => 22.380,
                'lng_min' => 91.800, 'lng_max' => 91.860
            ],
            'Sylhet' => [
                'lat_min' => 24.880, 'lat_max' => 24.920,
                'lng_min' => 91.860, 'lng_max' => 91.900
            ],
            'Rajshahi' => [
                'lat_min' => 24.360, 'lat_max' => 24.380,
                'lng_min' => 88.590, 'lng_max' => 88.620
            ],
            'Khulna' => [
                'lat_min' => 22.800, 'lat_max' => 22.820,
                'lng_min' => 89.540, 'lng_max' => 89.570
            ],
            'Barisal' => [
                'lat_min' => 22.700, 'lat_max' => 22.720,
                'lng_min' => 90.350, 'lng_max' => 90.380
            ],
            'Rangpur' => [
                'lat_min' => 25.740, 'lat_max' => 25.760,
                'lng_min' => 89.240, 'lng_max' => 89.270
            ],
            'Comilla' => [
                'lat_min' => 23.450, 'lat_max' => 23.470,
                'lng_min' => 91.170, 'lng_max' => 91.190
            ],
            'Mymensingh' => [
                'lat_min' => 24.740, 'lat_max' => 24.760,
                'lng_min' => 90.400, 'lng_max' => 90.420
            ]
        ];

        // Check which area the coordinates fall into
        foreach ($bangladeshAreas as $areaName => $bounds) {
            if ($latitude >= $bounds['lat_min'] && $latitude <= $bounds['lat_max'] &&
                $longitude >= $bounds['lng_min'] && $longitude <= $bounds['lng_max']) {
                return $areaName;
            }
        }

        // If no specific area found, determine by general region
        if ($latitude >= 23.0 && $latitude <= 24.5 && $longitude >= 90.0 && $longitude <= 90.5) {
            return 'Dhaka Metropolitan'; // General Dhaka area
        } elseif ($latitude >= 22.0 && $latitude <= 23.0 && $longitude >= 91.5 && $longitude <= 92.0) {
            return 'Chittagong Division';
        } elseif ($latitude >= 24.5 && $latitude <= 25.5 && $longitude >= 91.5 && $longitude <= 92.5) {
            return 'Sylhet Division';
        } elseif ($latitude >= 24.0 && $latitude <= 25.0 && $longitude >= 88.0 && $longitude <= 89.5) {
            return 'Rajshahi Division';
        } elseif ($latitude >= 22.5 && $latitude <= 23.5 && $longitude >= 89.0 && $longitude <= 90.0) {
            return 'Khulna Division';
        } elseif ($latitude >= 22.0 && $latitude <= 23.0 && $longitude >= 90.0 && $longitude <= 91.0) {
            return 'Barisal Division';
        } elseif ($latitude >= 25.0 && $latitude <= 26.5 && $longitude >= 88.5 && $longitude <= 90.0) {
            return 'Rangpur Division';
        }

        // Default fallback
        return 'Bangladesh';
    }

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
     * Get dynamic sections (top-restaurants, top-shopping, etc.)
     */
    public function dynamicSections(Request $request, $section)
    {
        try {
            $latitude = $request->input('latitude');
            $longitude = $request->input('longitude');
            $radiusKm = $request->input('radius', 20);
            $limit = $request->input('limit', 50);

            // Define section configurations
            $sectionConfigs = [
                'restaurants' => [
                    'title' => 'Top Restaurants',
                    'keywords' => ['restaurant', 'food', 'cafe', 'pizza', 'burger', 'dining', 'fast food', 'chinese', 'indian']
                ],
                'shopping' => [
                    'title' => 'Top Shopping',
                    'keywords' => ['shopping', 'shop', 'store', 'clothing', 'fashion', 'retail', 'boutique', 'mall', 'market']
                ]
            ];

            // Check if section is supported
            if (!isset($sectionConfigs[$section])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Section not supported. Available sections: ' . implode(', ', array_keys($sectionConfigs))
                ], 422);
            }

            $config = $sectionConfigs[$section];
            
            $query = Business::active()
                ->whereHas('category', function ($q) use ($config) {
                    $q->where(function($subQuery) use ($config) {
                        foreach ($config['keywords'] as $keyword) {
                            $subQuery->orWhere('name', 'LIKE', "%{$keyword}%");
                        }
                    });
                })
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
                    'section' => $section,
                    'title' => $config['title'],
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
                'message' => 'Failed to fetch section data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get today's trending offerings and businesses combined
     */
    public function todayTrending(Request $request)
    {
        try {
            $latitude = $request->input('latitude');
            $longitude = $request->input('longitude');
            $area = $request->input('area');
            $businessLimit = $request->input('business_limit', 10);
            $offeringLimit = $request->input('offering_limit', 10);
            
            // Determine user area if not provided
            if (!$area) {
                $area = $this->determineUserArea($latitude, $longitude);
            }

            $today = now()->format('Y-m-d');

            // Get trending businesses for today
            $trendingBusinesses = TrendingData::where('item_type', 'business')
                ->where('time_period', 'daily')
                ->where('date_period', $today)
                ->where('location_area', $area)
                ->orderBy('trend_score', 'desc')
                ->with(['business' => function($query) use ($latitude, $longitude) {
                    $query->select(['id', 'business_name', 'slug', 'landmark', 'overall_rating', 'price_range', 'category_id', 'subcategory_id', 'latitude', 'longitude'])
                          ->with([
                              'category:id,name,slug,icon_image,color_code',
                              'subcategory:id,name,slug',
                              'logoImage:id,business_id,image_url',
                              'coverImage:id,business_id,image_url',
                              'galleryImages:id,business_id,image_url'
                          ]);
                }])
                ->take($businessLimit)
                ->get()
                ->map(function($trend) use ($latitude, $longitude) {
                    if (!$trend->business) return null;
                    
                    $business = $trend->business;
                    $businessData = [
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
                            'gallery' => $business->galleryImages->pluck('image_url')->toArray()
                        ],
                        'trend_score' => $trend->trend_score,
                        'trend_rank' => $trend->id
                    ];

                    // Calculate distance if coordinates provided
                    if ($latitude && $longitude && $business->latitude && $business->longitude) {
                        $businessData['distance'] = $this->calculateDistance(
                            $latitude, $longitude, 
                            $business->latitude, $business->longitude
                        );
                    }

                    return $businessData;
                })
                ->filter()
                ->values();

            // Get trending offerings for today
            $trendingOfferings = TrendingData::where('item_type', 'offering')
                ->where('time_period', 'daily')
                ->where('date_period', $today)
                ->where('location_area', $area)
                ->orderBy('trend_score', 'desc')
                ->take($offeringLimit)
                ->get()
                ->map(function($trend) use ($latitude, $longitude) {
                    // Get the offering details with images
                    $offering = \App\Models\BusinessOffering::select(['id', 'name', 'offering_type', 'price', 'description', 'image_url', 'business_id'])
                        ->with(['business' => function($query) {
                            $query->select(['id', 'business_name', 'slug', 'area', 'latitude', 'longitude'])
                                  ->with([
                                      'category:id,name,slug', 
                                      'logoImage:id,business_id,image_url',
                                      'coverImage:id,business_id,image_url'
                                  ]);
                        }])
                        ->find($trend->item_id);
                    
                    if (!$offering || !$offering->business) return null;

                    $offeringData = [
                        'id' => $offering->id,
                        'name' => $offering->name,
                        'offering_type' => $offering->offering_type,
                        'price' => $offering->price,
                        'description' => $offering->description,
                        'image_url' => $offering->image_url,
                        'trend_score' => $trend->trend_score,
                        'trend_rank' => $trend->id,
                        'business' => [
                            'id' => $offering->business->id,
                            'business_name' => $offering->business->business_name,
                            'slug' => $offering->business->slug,
                            'area' => $offering->business->area,
                            'category_name' => $offering->business->category->name ?? null,
                            'images' => [
                                'logo' => $offering->business->logoImage->image_url ?? null,
                                'cover' => $offering->business->coverImage->image_url ?? null,
                            ]
                        ]
                    ];

                    // Calculate distance if coordinates provided
                    if ($latitude && $longitude && $offering->business->latitude && $offering->business->longitude) {
                        $offeringData['business']['distance'] = $this->calculateDistance(
                            $latitude, $longitude, 
                            $offering->business->latitude, $offering->business->longitude
                        );
                    }

                    return $offeringData;
                })
                ->filter()
                ->values();

            // Get summary stats
            $totalTrendingItems = TrendingData::where('time_period', 'daily')
                ->where('date_period', $today)
                ->where('location_area', $area)
                ->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'trending_businesses' => $trendingBusinesses,
                    'trending_offerings' => $trendingOfferings,
                    'summary' => [
                        'date' => $today,
                        'area' => $area,
                        'total_trending_items' => $totalTrendingItems,
                        'businesses_count' => $trendingBusinesses->count(),
                        'offerings_count' => $trendingOfferings->count(),
                        'location_provided' => $latitude && $longitude ? true : false
                    ],
                    'location' => [
                        'latitude' => $latitude,
                        'longitude' => $longitude,
                        'determined_area' => $area
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch today\'s trending data',
                'error' => $e->getMessage()
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
            
            if ($business && !in_array($business->id, $usedBusinessIds)) {
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
        $availableBusinesses = $allBusinesses->whereNotIn('id', $usedBusinessIds)
            ->where('is_featured', true);

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
        $availableBusinesses = $allBusinesses->whereNotIn('id', $usedBusinessIds)
            ->where('overall_rating', '>=', 3.5);

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
        $availableBusinesses = $allBusinesses->whereNotIn('id', $usedBusinessIds);
        $categorizedBusinesses = $availableBusinesses->groupBy('category.name');
        
        $dynamicSections = [];
        
        foreach ($categorizedBusinesses as $categoryName => $businesses) {
            if ($businesses->count() >= 2 && $categoryName) { // Only show categories with at least 2 businesses
                $sectionBusinesses = [];
                
                foreach ($businesses->sortByDesc('overall_rating')->take(4) as $business) {
                    $sectionBusinesses[] = [
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
                        'section_priority' => 'dynamic_' . strtolower(str_replace(' ', '_', $categoryName))
                    ];
                    
                    $usedBusinessIds[] = $business->id;
                }
                
                if (!empty($sectionBusinesses)) {
                    $dynamicSections[] = [
                        'section_name' => "Top {$categoryName}",
                        'section_slug' => strtolower(str_replace(' ', '_', $categoryName)),
                        'category_name' => $categoryName,
                        'count' => count($sectionBusinesses),
                        'businesses' => $sectionBusinesses
                    ];
                }
            }
        }

        // Sort sections by total rating and availability
        usort($dynamicSections, function($a, $b) {
            $aAvgRating = collect($a['businesses'])->avg('overall_rating');
            $bAvgRating = collect($b['businesses'])->avg('overall_rating');
            return $bAvgRating <=> $aAvgRating;
        });

        return $dynamicSections;
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
     * Track section performance for optimization
     */
    private function trackSectionPerformance($sectionData, $area)
    {
        try {
            $performance = [
                'area' => $area,
                'date' => now()->format('Y-m-d'),
                'trending_count' => count($sectionData['trending_businesses']),
                'featured_count' => count($sectionData['featured_businesses']),
                'popular_count' => count($sectionData['popular_nearby']),
                'dynamic_sections_count' => count($sectionData['dynamic_sections']),
                'timestamp' => now()
            ];
            
            Log::info('Section Performance', $performance);
        } catch (\Exception $e) {
            // Silent fail for analytics
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

            // Transform the data to include images
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
                    ]
                ];

                // Add distance if coordinates provided
                if ($latitude && $longitude && isset($business->distance)) {
                    $businessData['distance'] = $business->distance;
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
     * Get businesses that are currently open with optional category filtering
     */
    public function openNow(Request $request)
    {
        try {
            $latitude = $request->input('latitude');
            $longitude = $request->input('longitude');
            $radiusKm = $request->input('radius', 50);
            $limit = $request->input('limit', 50);
            $categoryId = $request->input('category_id');
            $minRating = $request->input('min_rating', 3.0);

            // Get current day and time
            $currentDay = strtolower(now()->format('l')); // monday, tuesday, etc.
            $currentTime = now()->format('H:i:s');

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

            // Filter for businesses that are currently open
            // This assumes opening_hours is stored as JSON with day-wise hours
            $query->where(function($q) use ($currentDay, $currentTime) {
                $q->whereNotNull('opening_hours')
                  ->where(function($subQ) use ($currentDay, $currentTime) {
                      // Check if opening_hours contains today's schedule
                      $subQ->whereRaw("JSON_EXTRACT(opening_hours, '$.{$currentDay}') IS NOT NULL")
                           ->whereRaw("JSON_EXTRACT(opening_hours, '$.{$currentDay}.is_open') = true")
                           ->whereRaw("TIME(JSON_UNQUOTE(JSON_EXTRACT(opening_hours, '$.{$currentDay}.open_time'))) <= ?", [$currentTime])
                           ->whereRaw("TIME(JSON_UNQUOTE(JSON_EXTRACT(opening_hours, '$.{$currentDay}.close_time'))) >= ?", [$currentTime]);
                  })
                  // Or if it's a 24/7 business
                  ->orWhereRaw("JSON_EXTRACT(opening_hours, '$.{$currentDay}.is_24_hours') = true")
                  // Or fallback for businesses without detailed opening hours but marked as open
                  ->orWhere('is_active', true);
            });

            $businesses = $query->orderBy('overall_rating', 'desc')
                ->orderBy('total_reviews', 'desc')
                ->paginate($limit);

            // Transform the data to include images and opening status
            $transformedBusinesses = $businesses->getCollection()->map(function($business) use ($latitude, $longitude, $currentDay, $currentTime) {
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
                    'opening_status' => $this->getOpeningStatus($business, $currentDay, $currentTime)
                ];

                // Add distance if coordinates provided
                if ($latitude && $longitude && isset($business->distance)) {
                    $businessData['distance'] = $business->distance;
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
                        'current_time' => now()->format('Y-m-d H:i:s'),
                        'current_day' => $currentDay,
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
                'next_change' => null
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
                'is_open' => false,
                'status' => 'Closed today',
                'next_change' => null
            ];
        }

        $todayHours = $openingHours[$currentDay];

        // Check if closed today
        if (!($todayHours['is_open'] ?? true)) {
            return [
                'is_open' => false,
                'status' => 'Closed today',
                'next_change' => null
            ];
        }

        // Check if 24 hours
        if ($todayHours['is_24_hours'] ?? false) {
            return [
                'is_open' => true,
                'status' => 'Open 24 hours',
                'next_change' => null
            ];
        }

        $openTime = $todayHours['open_time'] ?? '09:00:00';
        $closeTime = $todayHours['close_time'] ?? '22:00:00';

        if ($currentTime >= $openTime && $currentTime <= $closeTime) {
            return [
                'is_open' => true,
                'status' => "Open until {$closeTime}",
                'next_change' => $closeTime
            ];
        } else {
            return [
                'is_open' => false,
                'status' => "Opens at {$openTime}",
                'next_change' => $openTime
            ];
        }
    }
}
