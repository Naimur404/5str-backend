<?php

use App\Http\Controllers\Api\Admin\AdminAuthController;
use App\Http\Controllers\Api\Admin\AttractionAdminController;
use App\Http\Controllers\Api\Admin\AttractionGalleryAdminController;
use App\Http\Controllers\Api\Admin\AttractionReviewAdminController;
use App\Http\Controllers\Api\Admin\AttractionSubmissionAdminController;
use App\Http\Controllers\Api\Admin\BannerAdminController;
use App\Http\Controllers\Api\Admin\BusinessAdminController;
use App\Http\Controllers\Api\Admin\BusinessImageAdminController;
use App\Http\Controllers\Api\Admin\BusinessOfferingAdminController;
use App\Http\Controllers\Api\Admin\BusinessSubmissionAdminController;
use App\Http\Controllers\Api\Admin\CategoryAdminController;
use App\Http\Controllers\Api\Admin\DashboardAdminController;
use App\Http\Controllers\Api\Admin\EndpointAnalyticsAdminController;
use App\Http\Controllers\Api\Admin\NotificationAdminController;
use App\Http\Controllers\Api\Admin\OfferAdminController;
use App\Http\Controllers\Api\Admin\OfferingSubmissionAdminController;
use App\Http\Controllers\Api\Admin\OfferingVariantAdminController;
use App\Http\Controllers\Api\Admin\PermissionAdminController;
use App\Http\Controllers\Api\Admin\ReviewAdminController;
use App\Http\Controllers\Api\Admin\RoleAdminController;
use App\Http\Controllers\Api\Admin\SearchLogAdminController;
use App\Http\Controllers\Api\Admin\TrendingDataAdminController;
use App\Http\Controllers\Api\Admin\UploadAdminController;
use App\Http\Controllers\Api\Admin\UserAdminController;
use App\Http\Controllers\Api\Admin\UserAttractionInteractionAdminController;
use App\Http\Controllers\Api\Admin\ViewAdminController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin API Routes
|--------------------------------------------------------------------------
|
| Mounted under `/api/admin`. Sanctum + admin-tier role required on all
| protected routes. Login issues an admin Sanctum token; logout revokes
| the current user's tokens.
|
*/

Route::post('login', [AdminAuthController::class, 'login'])->name('admin.login');

Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::post('logout', [AdminAuthController::class, 'logout'])->name('admin.logout');
    Route::get('me', [AdminAuthController::class, 'me'])->name('admin.me');

    Route::post('uploads', [UploadAdminController::class, 'store'])->name('admin.uploads.store');

    // Database notifications (per-event, populated by model observers)
    Route::get('notifications', [NotificationAdminController::class, 'index'])->name('admin.notifications.index');
    Route::post('notifications/mark-all-read', [NotificationAdminController::class, 'markAllRead'])->name('admin.notifications.mark-all-read');
    Route::post('notifications/{id}/read', [NotificationAdminController::class, 'markRead'])->name('admin.notifications.read');
    Route::delete('notifications/{id}', [NotificationAdminController::class, 'destroy'])->name('admin.notifications.destroy');

    Route::get('categories/tree', [CategoryAdminController::class, 'tree'])->name('admin.categories.tree');
    Route::apiResource('categories', CategoryAdminController::class);
    Route::post('categories/bulk-destroy', [CategoryAdminController::class, 'bulkDestroy'])->name('admin.categories.bulk-destroy');

    Route::apiResource('users', UserAdminController::class);
    Route::post('users/bulk-destroy', [UserAdminController::class, 'bulkDestroy'])->name('admin.users.bulk-destroy');
    Route::post('users/{user}/roles', [UserAdminController::class, 'syncRoles'])->name('admin.users.roles');

    Route::apiResource('roles', RoleAdminController::class);
    Route::post('roles/bulk-destroy', [RoleAdminController::class, 'bulkDestroy'])->name('admin.roles.bulk-destroy');
    Route::post('roles/{role}/permissions', [RoleAdminController::class, 'syncPermissions'])->name('admin.roles.permissions');

    Route::apiResource('permissions', PermissionAdminController::class);
    Route::post('permissions/bulk-destroy', [PermissionAdminController::class, 'bulkDestroy'])->name('admin.permissions.bulk-destroy');

    Route::apiResource('banners', BannerAdminController::class);
    Route::post('banners/bulk-destroy', [BannerAdminController::class, 'bulkDestroy'])->name('admin.banners.bulk-destroy');

    // Businesses (with branches creation in one call + approval workflow)
    Route::get('businesses/picker', [BusinessAdminController::class, 'picker'])->name('admin.businesses.picker');
    Route::apiResource('businesses', BusinessAdminController::class);
    Route::post('businesses/bulk-destroy', [BusinessAdminController::class, 'bulkDestroy'])->name('admin.businesses.bulk-destroy');
    Route::post('businesses/{business}/branches', [BusinessAdminController::class, 'storeBranches'])->name('admin.businesses.branches.store');
    Route::post('businesses/{business}/approve', [BusinessAdminController::class, 'approve'])->name('admin.businesses.approve');
    Route::post('businesses/{business}/reject', [BusinessAdminController::class, 'reject'])->name('admin.businesses.reject');

    // Business images (logo / cover / gallery)
    Route::apiResource('business-images', BusinessImageAdminController::class);
    Route::post('business-images/bulk-destroy', [BusinessImageAdminController::class, 'bulkDestroy'])->name('admin.business-images.bulk-destroy');

    // Offerings (cascade to branches on create when business is a main brand)
    Route::apiResource('offerings', BusinessOfferingAdminController::class);
    Route::post('offerings/bulk-destroy', [BusinessOfferingAdminController::class, 'bulkDestroy'])->name('admin.offerings.bulk-destroy');

    // Offering variants
    Route::apiResource('offering-variants', OfferingVariantAdminController::class);
    Route::post('offering-variants/bulk-destroy', [OfferingVariantAdminController::class, 'bulkDestroy'])->name('admin.offering-variants.bulk-destroy');

    // Offers & promotions (cascade to branches on create + duplicate)
    Route::apiResource('offers', OfferAdminController::class);
    Route::post('offers/bulk-destroy', [OfferAdminController::class, 'bulkDestroy'])->name('admin.offers.bulk-destroy');
    Route::post('offers/{offer}/duplicate', [OfferAdminController::class, 'duplicate'])->name('admin.offers.duplicate');

    // Attractions
    Route::apiResource('attractions', AttractionAdminController::class);
    Route::post('attractions/bulk-destroy', [AttractionAdminController::class, 'bulkDestroy'])->name('admin.attractions.bulk-destroy');
    Route::post('attractions/{attraction}/verify', [AttractionAdminController::class, 'verify'])->name('admin.attractions.verify');
    Route::post('attractions/{attraction}/reject', [AttractionAdminController::class, 'reject'])->name('admin.attractions.reject');

    Route::apiResource('attraction-galleries', AttractionGalleryAdminController::class);
    Route::post('attraction-galleries/bulk-destroy', [AttractionGalleryAdminController::class, 'bulkDestroy'])->name('admin.attraction-galleries.bulk-destroy');

    Route::apiResource('attraction-reviews', AttractionReviewAdminController::class);
    Route::post('attraction-reviews/bulk-destroy', [AttractionReviewAdminController::class, 'bulkDestroy'])->name('admin.attraction-reviews.bulk-destroy');
    Route::post('attraction-reviews/{attraction_review}/approve', [AttractionReviewAdminController::class, 'approve'])->name('admin.attraction-reviews.approve');
    Route::post('attraction-reviews/{attraction_review}/reject', [AttractionReviewAdminController::class, 'reject'])->name('admin.attraction-reviews.reject');

    Route::apiResource('user-attraction-interactions', UserAttractionInteractionAdminController::class);
    Route::post('user-attraction-interactions/bulk-destroy', [UserAttractionInteractionAdminController::class, 'bulkDestroy'])->name('admin.user-attraction-interactions.bulk-destroy');

    // Reviews (polymorphic — business + attraction reviews via Review model)
    Route::get('reviews/rating-criteria', [ReviewAdminController::class, 'ratingCriteria'])->name('admin.reviews.rating-criteria');
    Route::apiResource('reviews', ReviewAdminController::class);
    Route::post('reviews/bulk-destroy', [ReviewAdminController::class, 'bulkDestroy'])->name('admin.reviews.bulk-destroy');
    Route::post('reviews/{review}/approve', [ReviewAdminController::class, 'approve'])->name('admin.reviews.approve');
    Route::post('reviews/{review}/reject', [ReviewAdminController::class, 'reject'])->name('admin.reviews.reject');

    // Submissions (queue + approve which materializes the real record)
    Route::prefix('submissions')->group(function () {
        Route::get('businesses', [BusinessSubmissionAdminController::class, 'index'])->name('admin.submissions.businesses.index');
        Route::get('businesses/{business_submission}', [BusinessSubmissionAdminController::class, 'show'])->name('admin.submissions.businesses.show');
        Route::put('businesses/{business_submission}', [BusinessSubmissionAdminController::class, 'update'])->name('admin.submissions.businesses.update');
        Route::post('businesses/{business_submission}/approve', [BusinessSubmissionAdminController::class, 'approve'])->name('admin.submissions.businesses.approve');
        Route::post('businesses/{business_submission}/reject', [BusinessSubmissionAdminController::class, 'reject'])->name('admin.submissions.businesses.reject');
        Route::delete('businesses/{business_submission}', [BusinessSubmissionAdminController::class, 'destroy'])->name('admin.submissions.businesses.destroy');

        Route::get('attractions', [AttractionSubmissionAdminController::class, 'index'])->name('admin.submissions.attractions.index');
        Route::get('attractions/{attraction_submission}', [AttractionSubmissionAdminController::class, 'show'])->name('admin.submissions.attractions.show');
        Route::put('attractions/{attraction_submission}', [AttractionSubmissionAdminController::class, 'update'])->name('admin.submissions.attractions.update');
        Route::post('attractions/{attraction_submission}/approve', [AttractionSubmissionAdminController::class, 'approve'])->name('admin.submissions.attractions.approve');
        Route::post('attractions/{attraction_submission}/reject', [AttractionSubmissionAdminController::class, 'reject'])->name('admin.submissions.attractions.reject');
        Route::delete('attractions/{attraction_submission}', [AttractionSubmissionAdminController::class, 'destroy'])->name('admin.submissions.attractions.destroy');

        Route::get('offerings', [OfferingSubmissionAdminController::class, 'index'])->name('admin.submissions.offerings.index');
        Route::get('offerings/{offering_submission}', [OfferingSubmissionAdminController::class, 'show'])->name('admin.submissions.offerings.show');
        Route::put('offerings/{offering_submission}', [OfferingSubmissionAdminController::class, 'update'])->name('admin.submissions.offerings.update');
        Route::post('offerings/{offering_submission}/approve', [OfferingSubmissionAdminController::class, 'approve'])->name('admin.submissions.offerings.approve');
        Route::post('offerings/{offering_submission}/reject', [OfferingSubmissionAdminController::class, 'reject'])->name('admin.submissions.offerings.reject');
        Route::delete('offerings/{offering_submission}', [OfferingSubmissionAdminController::class, 'destroy'])->name('admin.submissions.offerings.destroy');
    });

    // Dashboard aggregates
    Route::prefix('dashboard')->group(function () {
        Route::get('overview', [DashboardAdminController::class, 'overview'])->name('admin.dashboard.overview');
        Route::get('pending-approvals', [DashboardAdminController::class, 'pendingApprovals'])->name('admin.dashboard.pending-approvals');
        Route::get('business-growth', [DashboardAdminController::class, 'businessGrowth'])->name('admin.dashboard.business-growth');
        Route::get('area-usage', [DashboardAdminController::class, 'areaUsage'])->name('admin.dashboard.area-usage');
    });

    // Trending (admins can curate)
    Route::apiResource('trending-data', TrendingDataAdminController::class);
    Route::post('trending-data/bulk-destroy', [TrendingDataAdminController::class, 'bulkDestroy'])->name('admin.trending-data.bulk-destroy');

    // Analytics streams (read-only)
    Route::get('endpoint-analytics/summary', [EndpointAnalyticsAdminController::class, 'summary'])->name('admin.endpoint-analytics.summary');
    Route::get('endpoint-analytics', [EndpointAnalyticsAdminController::class, 'index'])->name('admin.endpoint-analytics.index');
    Route::get('endpoint-analytics/{endpoint_analytic}', [EndpointAnalyticsAdminController::class, 'show'])->name('admin.endpoint-analytics.show');
    Route::delete('endpoint-analytics/{endpoint_analytic}', [EndpointAnalyticsAdminController::class, 'destroy'])->name('admin.endpoint-analytics.destroy');
    Route::post('endpoint-analytics/bulk-destroy', [EndpointAnalyticsAdminController::class, 'bulkDestroy'])->name('admin.endpoint-analytics.bulk-destroy');

    Route::get('search-logs/summary', [SearchLogAdminController::class, 'summary'])->name('admin.search-logs.summary');
    Route::get('search-logs', [SearchLogAdminController::class, 'index'])->name('admin.search-logs.index');
    Route::get('search-logs/{search_log}', [SearchLogAdminController::class, 'show'])->name('admin.search-logs.show');
    Route::delete('search-logs/{search_log}', [SearchLogAdminController::class, 'destroy'])->name('admin.search-logs.destroy');
    Route::post('search-logs/bulk-destroy', [SearchLogAdminController::class, 'bulkDestroy'])->name('admin.search-logs.bulk-destroy');

    Route::get('views/summary', [ViewAdminController::class, 'summary'])->name('admin.views.summary');
    Route::get('views', [ViewAdminController::class, 'index'])->name('admin.views.index');
    Route::get('views/{view}', [ViewAdminController::class, 'show'])->name('admin.views.show');
    Route::delete('views/{view}', [ViewAdminController::class, 'destroy'])->name('admin.views.destroy');
    Route::post('views/bulk-destroy', [ViewAdminController::class, 'bulkDestroy'])->name('admin.views.bulk-destroy');
});
