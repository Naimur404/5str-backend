<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Business;
use App\Observers\BusinessObserver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CategoryController extends Controller
{
    /**
     * Get all categories with optional filtering
     */
    public function index(Request $request)
    {
        try {
            $query = Category::active();

            // Filter by level if specified
            if ($request->has('level')) {
                $query->level($request->level);
            }

            // Filter by parent if specified
            if ($request->has('parent_id')) {
                $query->where('parent_id', $request->parent_id);
            }

            // Include children if requested
            $with = [];
            if ($request->boolean('include_children')) {
                $with[] = 'children';
            }

            // Always include actual business count (dynamic calculation)
            $query->withCount(['businesses' => function($q) {
                $q->where('is_active', true);
            }]);

            $categories = $query->with($with)
                ->orderBy('businesses_count', 'desc') // Use dynamic count instead of static total_businesses
                ->orderBy('sort_order')              // then sort_order
                ->orderBy('name')                    // then name
                ->take(10)
                ->get();

            // Transform the data to include both counts for comparison
            $categories->transform(function($category) {
                $category->actual_businesses_count = $category->businesses_count;
                $category->stored_total_businesses = $category->total_businesses;
                
                // Use the actual count as the main total_businesses field
                $category->total_businesses = $category->businesses_count;
                
                return $category;
            });

            return response()->json([
                'success' => true,
                'data' => $categories
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch categories',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get main categories (level 1)
     */
    public function mainCategories(Request $request)
    {
        try {
            $categories = Category::active()
                ->mainCategories()
                ->with('children')
                ->orderBy('sort_order')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $categories
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch main categories',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get featured categories
     */
    public function featuredCategories(Request $request)
    {
        try {
            $categories = Category::active()
                ->featured()
                ->orderBy('sort_order')
                ->take(12)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $categories
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch featured categories',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get popular categories
     */
    public function popularCategories(Request $request)
    {
        try {
            $categories = Category::active()
                ->popular()
                ->withCount(['businesses' => function($q) {
                    $q->where('is_active', true);
                }])
                ->orderBy('businesses_count', 'desc') // Use dynamic count
                ->take(10)
                ->get();

            // Transform to use actual count as total_businesses
            $categories->transform(function($category) {
                $category->total_businesses = $category->businesses_count;
                return $category;
            });

            return response()->json([
                'success' => true,
                'data' => $categories
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch popular categories',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a specific category with details
     */
    public function show(Request $request, $categoryId)
    {
        try {
            $category = Category::active()
                ->with(['parent', 'children'])
                ->findOrFail($categoryId);

            return response()->json([
                'success' => true,
                'data' => $category
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Category not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Get subcategories of a category
     */
    public function subcategories(Request $request, $categoryId)
    {
        try {
            $category = Category::active()->findOrFail($categoryId);

            $subcategories = Category::active()
                ->where('parent_id', $category->id)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'category' => $category,
                    'subcategories' => $subcategories
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch subcategories',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get businesses in a category
     */
    public function businesses(Request $request, $categoryId)
    {
        try {
            $category = Category::active()->findOrFail($categoryId);

            $latitude = $request->input('latitude');
            $longitude = $request->input('longitude');
            $radiusKm = $request->input('radius', 20);
            $page = $request->input('page', 1);
            $limit = $request->input('limit', 20);

            $query = Business::active()
                ->inCategory($categoryId)
                ->with(['category', 'logoImage']);

            // Add location-based filtering if coordinates provided
            if ($latitude && $longitude) {
                $query->nearby($latitude, $longitude, $radiusKm);
            }

            // Add rating filter
            if ($request->has('min_rating')) {
                $query->withRating($request->min_rating);
            }

            // Add price range filter
            if ($request->has('price_min')) {
                $query->priceRange($request->price_min, $request->price_max);
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

            return response()->json([
                'success' => true,
                'data' => [
                    'category' => $category,
                    'businesses' => $businesses->items(),
                    'pagination' => [
                        'current_page' => $businesses->currentPage(),
                        'last_page' => $businesses->lastPage(),
                        'per_page' => $businesses->perPage(),
                        'total' => $businesses->total(),
                        'has_more' => $businesses->hasMorePages()
                    ],
                    'filters_applied' => [
                        'location' => $latitude && $longitude ? ['lat' => $latitude, 'lng' => $longitude, 'radius' => $radiusKm] : null,
                        'min_rating' => $request->min_rating,
                        'price_range' => $request->has('price_min') ? ['min' => $request->price_min, 'max' => $request->price_max] : null,
                        'sort' => $sortBy
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
     * Sync total_businesses count for all categories (utility method for maintenance)
     * This should be called periodically to keep the stored counts accurate
     */
    public function syncBusinessCounts()
    {
        try {
            // Get all category IDs
            $categoryIds = Category::pluck('id')->toArray();
            
            // Use the observer's bulk update method
            BusinessObserver::updateMultipleCategoryCounts($categoryIds);

            $updatedCount = count($categoryIds);

            return response()->json([
                'success' => true,
                'message' => "Successfully synced business counts for {$updatedCount} categories",
                'data' => [
                    'categories_updated' => $updatedCount,
                    'updated_category_ids' => $categoryIds,
                    'timestamp' => now(),
                    'note' => 'Counts are now automatically maintained via BusinessObserver'
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to sync business counts',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
