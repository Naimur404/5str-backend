<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\GoogleAuthController;

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
    return redirect('/admin');
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
