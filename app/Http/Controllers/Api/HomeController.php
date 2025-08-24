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
use Illuminate\Http\Request;

class HomeController extends Controller
{
    /**
     * Get home screen data (Public access)
     * Includes: banners, featured sections, top categories, nearby businesses, offers
     * Area is automatically determined from lat/lng coordinates
     */
    public function index(Request $request)
    {
        try {
            $latitude = $request->input('latitude');
            $longitude = $request->input('longitude');
            $radiusKm = $request->input('radius', 10);

            // Determine user area from coordinates or use default
            $userArea = $this->determineUserArea($latitude, $longitude);

            // Get active banners
            $banners = Banner::where('is_active', true)
                ->where('start_date', '<=', now())
                ->where('end_date', '>=', now())
                ->orderBy('sort_order')
                ->get();

            // Get top services (main categories) - dynamic based on location
            $topServices = [];
            if ($latitude && $longitude) {
                // Get categories that actually have businesses in the area
                $topServices = Category::active()
                    ->whereHas('businesses', function ($query) use ($latitude, $longitude, $radiusKm) {
                        $query->active()->nearby($latitude, $longitude, $radiusKm);
                    })
                    ->withCount(['businesses' => function ($query) use ($latitude, $longitude, $radiusKm) {
                        $query->active()->nearby($latitude, $longitude, $radiusKm);
                    }])
                    ->orderBy('businesses_count', 'desc')
                    ->take(8)
                    ->get()
                    ->map(function($category) {
                        return [
                            'id' => $category->id,
                            'name' => $category->name,
                            'slug' => $category->slug,
                            'icon_image' => $category->icon_image,
                            'color_code' => $category->color_code,
                            'business_count' => $category->businesses_count
                        ];
                    });
            } else {
                // Fallback to featured categories if no location
                $topServices = Category::active()
                    ->featured()
                    ->level(1)
                    ->orderBy('sort_order')
                    ->take(8)
                    ->get();
            }

                        // Get popular services nearby (if location provided)
            $popularNearby = [];
            if ($latitude && $longitude) {
                $popularNearby = Business::active()
                    ->nearbyWithDistance($latitude, $longitude, $radiusKm)
                    ->withRating(3.5)
                    ->with([
                        'category:id,name,slug,icon_image,color_code',
                        'subcategory:id,name,slug',
                        'logoImage:id,business_id,image_url'
                    ])
                    ->take(10)
                    ->get()
                    ->map(function($business) {
                        return [
                            'id' => $business->id,
                            'business_name' => $business->business_name,
                            'slug' => $business->slug,
                            'landmark' => $business->landmark,
                            'overall_rating' => $business->overall_rating,
                            'price_range' => $business->price_range,
                            'distance' => $business->distance ?? null,
                            'category_name' => $business->category->name ?? null,
                            'subcategory_name' => $business->subcategory->name ?? null,
                            'logo_image' => $business->logoImage->image_url ?? null,
                        ];
                    });
            }

            // Get dynamic top categories nearby (based on what's actually available)
            $dynamicSections = [];
            if ($latitude && $longitude) {
                $nearbyBusinesses = Business::active()
                    ->nearbyWithDistance($latitude, $longitude, $radiusKm)
                    ->withRating(3.0)
                    ->with(['category:id,name,slug', 'subcategory:id,name,slug', 'logoImage:id,business_id,image_url'])
                    ->get();

                // Group by category and create dynamic sections
                $categorizedBusinesses = $nearbyBusinesses->groupBy('category.name');
                
                foreach ($categorizedBusinesses as $categoryName => $businesses) {
                    if ($businesses->count() >= 1) { // Only show categories with at least 1 business
                        $dynamicSections[] = [
                            'section_name' => "Top {$categoryName}",
                            'section_slug' => strtolower(str_replace(' ', '_', $categoryName)),
                            'count' => $businesses->count(),
                            'businesses' => $businesses->sortByDesc('overall_rating')->take(6)->map(function($business) {
                                return [
                                    'id' => $business->id,
                                    'business_name' => $business->business_name,
                                    'slug' => $business->slug,
                                    'landmark' => $business->landmark,
                                    'overall_rating' => $business->overall_rating,
                                    'price_range' => $business->price_range,
                                    'category_name' => $business->category->name ?? null,
                                    'subcategory_name' => $business->subcategory->name ?? null,
                                    'logo_image' => $business->logoImage->image_url ?? null,
                                ];
                            })->values()
                        ];
                    }
                }

                // Sort sections by business count (most popular first)
                usort($dynamicSections, function($a, $b) {
                    return $b['count'] <=> $a['count'];
                });
            }

            // Get special offers - prioritize location-based offers
            $specialOffers = Offer::whereHas('business', function ($query) use ($latitude, $longitude, $radiusKm) {
                $query->active();
                if ($latitude && $longitude) {
                    $query->nearby($latitude, $longitude, $radiusKm);
                }
            })
                ->where('is_active', true)
                ->where('valid_from', '<=', now())
                ->where('valid_to', '>=', now())
                ->with(['business' => function($query) use ($latitude, $longitude) {
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

            // Get featured businesses - prioritize nearby if location provided
            $featuredBusinesses = Business::active()
                ->when($latitude && $longitude, function ($query) use ($latitude, $longitude, $radiusKm) {
                    // If location provided, get nearby featured businesses first
                    $query->nearby($latitude, $longitude, $radiusKm)->featured();
                }, function ($query) {
                    // Otherwise get general featured businesses
                    $query->featured();
                })
                ->with([
                    'category:id,name,slug,icon_image,color_code',
                    'subcategory:id,name,slug',
                    'logoImage:id,business_id,image_url'
                ])
                ->orderBy('overall_rating', 'desc')
                ->take(6)
                ->get()
                ->map(function($business) {
                    return [
                        'id' => $business->id,
                        'business_name' => $business->business_name,
                        'slug' => $business->slug,
                        'landmark' => $business->landmark,
                        'overall_rating' => $business->overall_rating,
                        'price_range' => $business->price_range,
                        'category_name' => $business->category->name ?? null,
                        'subcategory_name' => $business->subcategory->name ?? null,
                        'logo_image' => $business->logoImage->image_url ?? null,
                        'distance' => $business->distance ?? null,
                    ];
                });

            // Get dynamic top categories nearby (based on what's actually available)
            $topCategoriesNearby = [];
            if ($latitude && $longitude) {
                $topCategoriesNearby = Business::active()
                    ->nearbyWithDistance($latitude, $longitude, $radiusKm)
                    ->with(['category'])
                    ->get()
                    ->groupBy('category.name')
                    ->map(function($businesses, $categoryName) {
                        return [
                            'category_name' => $categoryName,
                            'count' => $businesses->count(),
                            'avg_rating' => round($businesses->avg('overall_rating'), 1),
                            'businesses' => $businesses->sortByDesc('overall_rating')->take(3)->values()
                        ];
                    })
                    ->sortByDesc('count')
                    ->take(5)
                    ->values();
            }

            // Get trending businesses and categories - based on determined area
            $trendingBusinesses = TrendingData::where('item_type', 'business')
                ->where('time_period', 'daily')
                ->where('date_period', now()->format('Y-m-d'))
                ->where('location_area', $userArea)
                ->orderBy('trend_score', 'desc')
                ->with(['business' => function($query) use ($latitude, $longitude, $radiusKm) {
                    $query->select(['id', 'business_name', 'slug', 'landmark', 'overall_rating', 'price_range', 'category_id', 'latitude', 'longitude'])
                          ->with([
                              'category:id,name,slug,icon_image,color_code',
                              'logoImage:id,business_id,image_url'
                          ]);
                    // If coordinates provided, prioritize nearby businesses
                    if ($latitude && $longitude) {
                        $query->nearby($latitude, $longitude, $radiusKm);
                    }
                }])
                ->take(6)
                ->get()
                ->map(function($trend) {
                    return [
                        'trend_score' => $trend->trend_score,
                        'business' => $trend->business ? [
                            'id' => $trend->business->id,
                            'business_name' => $trend->business->business_name,
                            'slug' => $trend->business->slug,
                            'landmark' => $trend->business->landmark,
                            'overall_rating' => $trend->business->overall_rating,
                            'price_range' => $trend->business->price_range,
                            'category_name' => $trend->business->category->name ?? null,
                            'logo_image' => $trend->business->logoImage->image_url ?? null,
                        ] : null
                    ];
                })
                ->filter(function($item) {
                    return $item['business'] !== null;
                })
                ->values();

            $trendingCategories = TrendingData::where('item_type', 'category')
                ->where('time_period', 'daily')
                ->where('date_period', now()->format('Y-m-d'))
                ->where('location_area', $userArea)
                ->orderBy('trend_score', 'desc')
                ->with(['category' => function($query) {
                    $query->select(['id', 'name', 'slug', 'icon_image', 'color_code']);
                }])
                ->take(5)
                ->get()
                ->map(function($trend) {
                    return [
                        'trend_score' => $trend->trend_score,
                        'category' => $trend->category ? [
                            'id' => $trend->category->id,
                            'name' => $trend->category->name,
                            'slug' => $trend->category->slug,
                            'icon_image' => $trend->category->icon_image,
                            'color_code' => $trend->category->color_code,
                        ] : null
                    ];
                })
                ->filter(function($item) {
                    return $item['category'] !== null;
                })
                ->values();

            $trendingSearchTerms = TrendingData::where('item_type', 'search_term')
                ->where('time_period', 'daily')
                ->where('date_period', now()->format('Y-m-d'))
                ->where('location_area', $userArea)
                ->orderBy('trend_score', 'desc')
                ->take(5)
                ->get()
                ->map(function($trend) {
                    return [
                        'search_term' => $trend->item_name,
                        'trend_score' => $trend->trend_score,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'banners' => $banners,
                    'top_services' => $topServices,
                    'popular_nearby' => $popularNearby,
                    'dynamic_sections' => $dynamicSections,
                    'special_offers' => $specialOffers,
                    'featured_businesses' => $featuredBusinesses,
                    'trending' => [
                        'businesses' => $trendingBusinesses,
                        'categories' => $trendingCategories,
                        'search_terms' => $trendingSearchTerms,
                        'area' => $userArea,
                        'date' => now()->format('Y-m-d')
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
            $type = $request->input('type'); // business, category, search_term (optional filter)
            
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
}
