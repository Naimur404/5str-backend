<?php

use App\Http\Controllers\Api\Admin\AdminAuthController;
use App\Http\Controllers\Api\Admin\BannerAdminController;
use App\Http\Controllers\Api\Admin\CategoryAdminController;
use App\Http\Controllers\Api\Admin\PermissionAdminController;
use App\Http\Controllers\Api\Admin\RoleAdminController;
use App\Http\Controllers\Api\Admin\UploadAdminController;
use App\Http\Controllers\Api\Admin\UserAdminController;
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
});
