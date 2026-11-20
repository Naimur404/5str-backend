<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\GoogleAuthController;
use App\Http\Controllers\HorizonAuthController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return response()->json([
        'success' => true,
        'message' => '5SRT API',
    ]);
});

// Add a named login route to prevent authentication errors
Route::get('/login', function () {
    return response()->json([
        'success' => false,
        'message' => 'Authentication required. Please log in to access this resource.',
        'error' => 'Unauthenticated'
    ], 401);
})->name('login');

Route::prefix('auth/google')->group(function () {
        Route::get('/redirect', [GoogleAuthController::class, 'redirect']);
        Route::get('/callback', [GoogleAuthController::class, 'callback']);
        Route::post('/token', [GoogleAuthController::class, 'handleGoogleToken']);
    });


/*
|--------------------------------------------------------------------------
| Horizon login (session-based)
|--------------------------------------------------------------------------
| The Horizon dashboard at /horizon is gated by HorizonBasicAuth, which
| redirects unauthenticated visitors to /horizon-login. Authentication
| is against the real `users` table via the `web` guard, with an
| inline admin-role check so non-admins never receive a session
| cookie. Logout invalidates the session and bounces back here.
*/

Route::get('/horizon-login', [HorizonAuthController::class, 'showLogin'])
    ->name('horizon.login');

Route::post('/horizon-login', [HorizonAuthController::class, 'login'])
    ->middleware('throttle:auth')
    ->name('horizon.login.attempt');

Route::post('/horizon-logout', [HorizonAuthController::class, 'logout'])
    ->name('horizon.logout');
