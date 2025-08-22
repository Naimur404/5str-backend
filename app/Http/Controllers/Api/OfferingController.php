<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BusinessOffering;
use App\Models\Review;
use Illuminate\Http\Request;

class OfferingController extends Controller
{
    /**
     * Get business offerings (products/services/menu)
     */
    public function index(Request $request, $businessId)
    {
        try {
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

            // Sort options
            $sortBy = $request->input('sort', 'sort_order');
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
                    $query->popular()->orderBy('total_reviews', 'desc');
                    break;
                case 'name':
                    $query->orderBy('name', 'asc');
                    break;
                default:
                    $query->orderBy('sort_order')->orderBy('name');
            }

            $offerings = $query->with(['category:id,name,slug'])
                ->get()
                ->map(function($offering) {
                    return [
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
                        'category' => $offering->category ? [
                            'id' => $offering->category->id,
                            'name' => $offering->category->name,
                            'slug' => $offering->category->slug,
                        ] : null,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'business_id' => $businessId,
                    'offerings' => $offerings
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
     * Get offering details
     */
    public function show(Request $request, $businessId, $offeringId)
    {
        try {
            $offering = BusinessOffering::where('business_id', $businessId)
                ->where('id', $offeringId)
                ->available()
                ->with(['category:id,name,slug', 'business:id,business_name,slug'])
                ->firstOrFail();

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
                ],
                'category' => $offering->category ? [
                    'id' => $offering->category->id,
                    'name' => $offering->category->name,
                    'slug' => $offering->category->slug,
                ] : null,
            ];

            return response()->json([
                'success' => true,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Offering not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Get offering reviews
     */
    public function reviews(Request $request, $businessId, $offeringId)
    {
        try {
            $offering = BusinessOffering::where('business_id', $businessId)
                ->where('id', $offeringId)
                ->available()
                ->firstOrFail();
            
            $page = $request->input('page', 1);
            $limit = $request->input('limit', 20);

            $query = Review::where('reviewable_type', 'App\\Models\\BusinessOffering')
                ->where('reviewable_id', $offeringId)
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

            // Map reviews to lightweight format
            $reviewsData = $reviews->getCollection()->map(function($review) {
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
                    'offering' => [
                        'id' => $offering->id,
                        'name' => $offering->name,
                        'offering_type' => $offering->offering_type,
                        'average_rating' => $offering->average_rating,
                        'total_reviews' => $offering->total_reviews
                    ],
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
                'message' => 'Failed to fetch offering reviews',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
