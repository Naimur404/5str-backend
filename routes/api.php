<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\HomeController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\BusinessController;
use App\Http\Controllers\Api\OfferingController;
use App\Http\Controllers\Api\OfferController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PushTokenController;
use App\Http\Controllers\Api\UserCollectionController;
use App\Http\Controllers\Api\RecommendationController;
use App\Http\Controllers\Api\AttractionController;
use App\Http\Controllers\Api\AttractionInteractionController;
use App\Http\Controllers\Api\AttractionReviewController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Public routes (no authentication required)
Route::prefix('v1')->group(function () {
    // Authentication routes
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

    // Home screen data (public access)
    Route::get('/home', [HomeController::class, 'index']);
    Route::get('/featured-sections', [HomeController::class, 'featuredSections']);
    Route::post('/banners/{banner}/click', [HomeController::class, 'trackBannerClick']);
    Route::get('/statistics', [HomeController::class, 'statistics']);
    Route::get('/trending', [HomeController::class, 'trending']);
    Route::get('/today-trending', [HomeController::class, 'todayTrending']);
    Route::get('/top-rated', [HomeController::class, 'topRated']);
    Route::get('/open-now', [HomeController::class, 'openNow']);
    
    // Home section "View All" endpoints
    Route::get('/home/top-services', [HomeController::class, 'topServices']);
    Route::get('/home/popular-nearby', [HomeController::class, 'popularNearby']);
    Route::get('/home/dynamic-sections/{section}', [HomeController::class, 'dynamicSections']);
    Route::get('/home/featured-businesses', [HomeController::class, 'featuredBusinesses']);
    Route::get('/home/special-offers', [HomeController::class, 'specialOffers']);
    Route::get('/home/national-brands', [HomeController::class, 'nationalBrands']);
    
    // Home analytics and tracking routes
    Route::post('/home/businesses/{business}/track-view', [HomeController::class, 'trackHomeBusinessView']);
    Route::post('/home/track-trending-performance', [HomeController::class, 'trackTrendingPerformance']);
    
    // Location-based analytics and insights
    Route::get('/analytics/area-insights', [HomeController::class, 'getAreaAnalytics']);
    Route::get('/location/insights', [HomeController::class, 'getLocationInsights']);
    Route::get('/location/recommendations', [HomeController::class, 'getAreaBasedRecommendations']);
    
    // Admin analytics (consider adding admin middleware later)
    Route::get('/analytics/divisions', [HomeController::class, 'getDivisionAnalytics']);

    // Universal search routes (public access)
    Route::prefix('search')->group(function () {
        Route::get('/', [SearchController::class, 'search']);
        Route::get('/suggestions', [SearchController::class, 'suggestions']);
        Route::get('/popular', [SearchController::class, 'popular']);
        
        // Enhanced area-based search routes
        Route::get('/area', [SearchController::class, 'searchByArea']);
        Route::get('/area-suggestions', [SearchController::class, 'getAreaBasedSuggestions']);
        
        // View tracking for trending analysis
        Route::post('/businesses/{business}/view', [SearchController::class, 'trackBusinessView']);
        Route::post('/offerings/{offering}/view', [SearchController::class, 'trackOfferingView']);
    });

    // Public category routes
    Route::prefix('categories')->group(function () {
        Route::get('/', [CategoryController::class, 'index']);
        Route::get('/main', [CategoryController::class, 'mainCategories']);
        Route::get('/featured', [CategoryController::class, 'featuredCategories']);
        Route::get('/popular', [CategoryController::class, 'popularCategories']);
        Route::get('/{category}', [CategoryController::class, 'show']);
        Route::get('/{category}/subcategories', [CategoryController::class, 'subcategories']);
        Route::get('/{category}/businesses', [CategoryController::class, 'businesses']);
    });

    // Public business routes (with optional authentication for favorites)
    Route::prefix('businesses')->group(function () {
        Route::get('/', [BusinessController::class, 'index']);
        Route::get('/search', [BusinessController::class, 'search']);
        Route::get('/nearby', [BusinessController::class, 'nearby']);
        Route::get('/featured', [BusinessController::class, 'featured']);
        Route::get('/national', [BusinessController::class, 'national']); // New national businesses endpoint
        Route::get('/national/filters', [BusinessController::class, 'nationalFilters']); // New national business filters endpoint
        Route::get('/{business}', [BusinessController::class, 'show']);
        Route::post('/{business}/track-click', [BusinessController::class, 'trackClick']);
        Route::post('/{business}/track-view', [BusinessController::class, 'trackBusinessView']);
        // Route::get('/{business}/offerings', [BusinessController::class, 'offerings']);
        Route::get('/{business}/reviews', [BusinessController::class, 'reviews']);
        Route::get('/{business}/offers', [BusinessController::class, 'offers']);
        
        // Enhanced location-based business features
        Route::get('/analytics/by-area', [BusinessController::class, 'getBusinessAnalyticsByArea']);
        Route::get('/by-precise-area', [BusinessController::class, 'getBusinessesByPreciseArea']);
        Route::post('/analytics/area-comparison', [BusinessController::class, 'getAreaComparisonAnalytics']);
    });

    // Public offering routes (with optional authentication for favorites)
    Route::prefix('businesses/{business}/offerings')->group(function () {
        Route::get('/', [OfferingController::class, 'index']);
        Route::get('/{offering}', [OfferingController::class, 'show']);
        Route::get('/{offering}/reviews', [OfferingController::class, 'reviews']);
        
        // View tracking for trending analysis
        Route::post('/{offering}/track-view', [OfferingController::class, 'trackOfferingView']);
    });

    // Public offering analytics routes
    Route::prefix('offerings/analytics')->group(function () {
        Route::post('/area-analytics', [OfferingController::class, 'getOfferingAnalyticsByArea']);
        Route::post('/area-offerings', [OfferingController::class, 'getOfferingsByPreciseArea']);
        Route::post('/popular-in-area', [OfferingController::class, 'getPopularOfferingsInArea']);
    });

    // Public offer routes (with optional authentication for usage tracking)
    Route::prefix('offers')->group(function () {
        Route::get('/', [OfferController::class, 'index']);
        Route::get('/{offer}', [OfferController::class, 'show']);
    });

    // Public attractions routes (with optional authentication for interactions)
    Route::prefix('attractions')->group(function () {
        Route::get('/', [AttractionController::class, 'index']);
        Route::get('/search', [AttractionController::class, 'search']);
        Route::get('/nearby', [AttractionController::class, 'nearby']);
        Route::get('/featured', [AttractionController::class, 'featured']);
        Route::get('/popular', [AttractionController::class, 'popular']);
        Route::get('/{attraction}', [AttractionController::class, 'show']);
        Route::get('/{attraction}/gallery', [AttractionController::class, 'gallery']);
        Route::get('/{attraction}/reviews', [AttractionController::class, 'reviews']);
        
        // Location-based analytics (public access)
        Route::get('/analytics/by-area', [AttractionController::class, 'getAttractionsByArea']);
        Route::post('/analytics/area-insights', [AttractionController::class, 'getAreaInsights']);
    });

    // Public attraction review routes (read-only without authentication)
    Route::prefix('attraction-reviews')->group(function () {
        Route::get('/', [AttractionReviewController::class, 'index']);
        Route::get('/{attraction}/statistics', [AttractionReviewController::class, 'statistics']);
    });
});

// Protected routes (authentication required)
Route::middleware('auth:sanctum')->prefix('v1')->group(function () {
    // User management
    Route::prefix('auth')->group(function () {
        Route::get('/user', [AuthController::class, 'user']);
        Route::put('/profile', [AuthController::class, 'updateProfile']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });

    // User-specific features (require login)
    Route::prefix('user')->group(function () {
        // Favorites management
        Route::get('/favorites', [UserController::class, 'favorites']);
        Route::post('/favorites', [UserController::class, 'addFavorite']);
        Route::delete('/favorites/{favorite}', [UserController::class, 'removeFavorite']);
        
        // User reviews
        Route::get('/reviews', [UserController::class, 'reviews']);
        
        // User points and rewards
        Route::get('/points', [UserController::class, 'points']);
    });

    // Notification management (require login)
    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::get('/stats', [NotificationController::class, 'stats']);
        Route::get('/{notification}', [NotificationController::class, 'show']);
        Route::patch('/{notification}/read', [NotificationController::class, 'markAsRead']);
        Route::patch('/mark-all-read', [NotificationController::class, 'markAllAsRead']);
        Route::delete('/{notification}', [NotificationController::class, 'clear']);
        Route::delete('/', [NotificationController::class, 'clearAll']);
        
        // Test notification (development only)
        Route::post('/test', [NotificationController::class, 'createTest']);
    });

    // Push token management (require login)
    Route::prefix('push-tokens')->group(function () {
        Route::get('/', [PushTokenController::class, 'index']);
        Route::post('/', [PushTokenController::class, 'store']);
        Route::put('/{token}', [PushTokenController::class, 'update']);
        Route::delete('/{token}', [PushTokenController::class, 'destroy']);
        Route::put('/{token}/status', [PushTokenController::class, 'updateStatus']);
        
        // Test push notification (development only)
        Route::post('/test', [PushTokenController::class, 'testNotification']);
    });

    // Review management (require login)
    Route::prefix('reviews')->group(function () {
        Route::post('/', [ReviewController::class, 'store']);
        Route::get('/{review}', [ReviewController::class, 'show']);
        Route::put('/{review}', [ReviewController::class, 'update']);
        Route::delete('/{review}', [ReviewController::class, 'destroy']);
        
        // Review voting system
        Route::post('/{review}/vote', [ReviewController::class, 'voteHelpful']);
        Route::delete('/{review}/vote', [ReviewController::class, 'removeVote']);
        Route::get('/{review}/vote-status', [ReviewController::class, 'getVoteStatus']);
        
        // Admin/Moderator functions
        Route::post('/{review}/approve', [ReviewController::class, 'approveReview'])
            ->middleware('role:admin|super-admin|moderator');
    });

    // Offer usage (require login)
    Route::prefix('offers')->group(function () {
        Route::post('/{offer}/use', [OfferController::class, 'useOffer']);
    });

    // User Collections (require login)
    Route::prefix('collections')->group(function () {
        // User's collections CRUD
        Route::get('/', [UserCollectionController::class, 'index']);
        Route::post('/', [UserCollectionController::class, 'store']);
        Route::get('/{collection}', [UserCollectionController::class, 'show']);
        Route::put('/{collection}', [UserCollectionController::class, 'update']);
        Route::delete('/{collection}', [UserCollectionController::class, 'destroy']);
        
        // Collection business management
        Route::post('/{collection}/businesses', [UserCollectionController::class, 'addBusiness']);
        Route::delete('/{collection}/businesses/{business}', [UserCollectionController::class, 'removeBusiness']);
        
        // Collection following system
        Route::post('/{collection}/follow', [UserCollectionController::class, 'follow']);
        Route::delete('/{collection}/follow', [UserCollectionController::class, 'unfollow']);
    });

    // AI-Powered Recommendations (require login)
    Route::prefix('recommendations')->group(function () {
        Route::get('/', [RecommendationController::class, 'getRecommendations']);
        Route::get('/advanced-ai', [RecommendationController::class, 'getAdvancedAIRecommendations']);
        Route::get('/personalized', [RecommendationController::class, 'getPersonalizedBusinesses']);
        Route::get('/similar/{business}', [RecommendationController::class, 'getSimilarBusinesses']);
        Route::get('/trending', [RecommendationController::class, 'getTrendingBusinesses']);
        Route::get('/history', [RecommendationController::class, 'getInteractionHistory']);
        
        // Interaction tracking
        Route::post('/track', [RecommendationController::class, 'trackInteraction']);
    });

    // User Interaction Tracking and Personalization
    Route::prefix('interactions')->group(function () {
        Route::post('/track', [\App\Http\Controllers\Api\UserInteractionController::class, 'track']);
        Route::post('/batch', [\App\Http\Controllers\Api\UserInteractionController::class, 'batch']);
        Route::get('/history', [\App\Http\Controllers\Api\UserInteractionController::class, 'history']);
        Route::get('/personalization-summary', [\App\Http\Controllers\Api\UserInteractionController::class, 'personalizationSummary']);
    });

    // Attraction user interactions (require login)
    Route::prefix('attraction-interactions')->group(function () {
        // User interaction management (like/dislike/bookmark/visit/share)
        Route::post('/', [AttractionInteractionController::class, 'store']);
        Route::get('/user/{user}', [AttractionInteractionController::class, 'userInteractions']);
        Route::get('/attraction/{attraction}', [AttractionInteractionController::class, 'attractionInteractions']);
        Route::post('/toggle', [AttractionInteractionController::class, 'toggle']);
        Route::delete('/remove', [AttractionInteractionController::class, 'remove']);
        
        // User's liked/bookmarked attractions
        Route::get('/liked', [AttractionInteractionController::class, 'likedAttractions']);
        Route::get('/bookmarked', [AttractionInteractionController::class, 'bookmarkedAttractions']);
        Route::get('/visited', [AttractionInteractionController::class, 'visitedAttractions']);
    });

    // Attraction review management (require login)
    Route::prefix('attraction-reviews')->group(function () {
        Route::post('/', [AttractionReviewController::class, 'store']);
        Route::get('/{review}', [AttractionReviewController::class, 'show']);
        Route::put('/{review}', [AttractionReviewController::class, 'update']);
        Route::delete('/{review}', [AttractionReviewController::class, 'destroy']);
        
        // Review helpful voting system
        Route::post('/{review}/helpful', [AttractionReviewController::class, 'markHelpful']);
        Route::delete('/{review}/helpful', [AttractionReviewController::class, 'removeHelpful']);
    });

    // Note: A/B testing routes removed to simplify the system
});

// Public Collection Discovery Routes (no authentication required)
Route::prefix('v1/discover/collections')->group(function () {
    Route::get('/popular', [UserCollectionController::class, 'popular']);
    Route::get('/search', [UserCollectionController::class, 'search']);
});
