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
     */
    public function index(Request $request)
    {
        try {
            $latitude = $request->input('latitude');
            $longitude = $request->input('longitude');
            $radiusKm = $request->input('radius', 10);

            // Get active banners
            $banners = Banner::where('is_active', true)
                ->where('start_date', '<=', now())
                ->where('end_date', '>=', now())
                ->orderBy('sort_order')
                ->get();

            // Get top services (main categories)
            $topServices = Category::active()
                ->featured()
                ->level(1)
                ->orderBy('sort_order')
                ->take(5)
                ->get();

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

            // Get special offers
            $specialOffers = Offer::whereHas('business', function ($query) use ($latitude, $longitude, $radiusKm) {
                $query->active();
                if ($latitude && $longitude) {
                    $query->nearby($latitude, $longitude, $radiusKm);
                }
            })
                ->where('is_active', true)
                ->where('valid_from', '<=', now())
                ->where('valid_to', '>=', now())
                ->select(['id', 'business_id', 'title', 'description', 'offer_type', 'discount_percentage', 'valid_to'])
                ->with(['business:id,business_name,slug', 'business.logoImage:id,business_id,image_url'])
                ->take(12)
                ->get();

            // Get featured businesses
            $featuredBusinesses = Business::active()
                ->featured()
                ->with([
                    'category:id,name,slug,icon_image,color_code',
                    'subcategory:id,name,slug',
                    'logoImage:id,business_id,image_url'
                ])
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
                    ];
                });

                        // Get special offers (with businesses)
            $specialOffers = Offer::active()
                ->with([
                    'business' => function($query) {
                        $query->select(['id', 'business_name', 'slug', 'landmark', 'overall_rating', 'price_range', 'category_id', 'subcategory_id'])
                              ->with([
                                  'category:id,name,slug,icon_image,color_code',
                                  'subcategory:id,name,slug',
                                  'logoImage:id,business_id,image_url'
                              ]);
                    }
                ])
                ->take(5)
                ->get()
                ->map(function($offer) {
                    return [
                        'id' => $offer->id,
                        'title' => $offer->title,
                        'description' => $offer->description,
                        'discount_percentage' => $offer->discount_percentage,
                        'valid_until' => $offer->valid_until,
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
                });

            // Get featured businesses
            $featuredBusinesses = Business::active()
                ->featured()
                ->with([
                    'category:id,name,slug,icon_image,color_code',
                    'subcategory:id,name,slug',
                    'logoImage:id,business_id,image_url'
                ])
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

            // Get trending businesses and categories
            $userArea = $request->input('area', 'Dhanmondi'); // Default or user-specified area
            
            $trendingBusinesses = TrendingData::where('item_type', 'business')
                ->where('time_period', 'daily')
                ->where('date_period', now()->format('Y-m-d'))
                ->where('location_area', $userArea)
                ->orderBy('trend_score', 'desc')
                ->with(['business' => function($query) {
                    $query->select(['id', 'business_name', 'slug', 'landmark', 'overall_rating', 'price_range', 'category_id'])
                          ->with([
                              'category:id,name,slug,icon_image,color_code',
                              'logoImage:id,business_id,image_url'
                          ]);
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
                        'radius_km' => $radiusKm
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
}
