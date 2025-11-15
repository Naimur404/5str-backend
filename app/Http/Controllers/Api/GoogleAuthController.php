<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GoogleAuthController extends Controller
{
    /**
     * Redirect to Google OAuth (Stateless)
     */
    public function redirect()
    {
        try {
            return response()->json([
                'url' => Socialite::driver('google')->redirect()->getTargetUrl(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to generate Google auth URL',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handle Google OAuth callback (Stateless)
     */
     public function callback(Request $request)
    {
        try {
            // Get user info from Google
            $googleUser = Socialite::driver('google')->user();
            
            // Find or create user
            $user = User::where('email', $googleUser->email)
                ->orWhere('google_id', $googleUser->id)
                ->first();

            if ($user) {
                // Update existing user
                $user->update([
                    'google_id' => $googleUser->id,
                    'avatar' => $googleUser->avatar,
                    'email_verified_at' => $user->email_verified_at ?? now(),
                ]);
            } else {
                // Create new user
                $user = User::create([
                    'name' => $googleUser->name,
                    'email' => $googleUser->email,
                    'google_id' => $googleUser->id,
                    'avatar' => $googleUser->avatar,
                    'email_verified_at' => now(),
                    'password' => bcrypt(Str::random(32)), // Random password for OAuth users
                ]);
            }

            // Create token
            $token = $user->createToken('mobile-app')->plainTextToken;

            // **KEY CHANGE: Detect if request is from mobile app**
            $userAgent = $request->header('User-Agent');
            $isMobileApp = str_contains($userAgent, 'ReactNative') || 
                          str_contains($userAgent, 'Expo') ||
                          $request->header('X-Mobile-App') === 'true';

            // **OPTION 1: Redirect to mobile app deep link (RECOMMENDED)**
            if ($isMobileApp || $request->has('mobile')) {
                // Encode user data
                $userData = json_encode([
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'profile_image' => $user->profile_image,
                    'current_latitude' => $user->current_latitude,
                    'current_longitude' => $user->current_longitude,
                    'city' => $user->city,
                    'total_points' => $user->total_points,
                    'total_reviews_written' => $user->total_reviews_written,
                    'trust_level' => $user->trust_level,
                    'email_verified_at' => $user->email_verified_at,
                    'is_active' => $user->is_active,
                    'google_id' => $user->google_id,
                    'avatar' => $user->avatar,
                ]);

                // Redirect to app with data
                $deepLink = '5str://auth/google?' . http_build_query([
                    'success' => 'true',
                    'token' => $token,
                    'user' => base64_encode($userData),
                ]);

                return redirect($deepLink);
            }

            // **OPTION 2: Return JSON for web/testing**
            return response()->json([
                'success' => true,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'profile_image' => $user->profile_image,
                    'current_latitude' => $user->current_latitude,
                    'current_longitude' => $user->current_longitude,
                    'city' => $user->city,
                    'total_points' => $user->total_points,
                    'total_reviews_written' => $user->total_reviews_written,
                    'trust_level' => $user->trust_level,
                    'email_verified_at' => $user->email_verified_at,
                    'is_active' => $user->is_active,
                    'google_id' => $user->google_id,
                    'avatar' => $user->avatar,
                ],
                'token' => $token,
                'token_type' => 'Bearer'
            ]);

        } catch (\Exception $e) {
            Log::error('Google OAuth Error: ' . $e->getMessage());
            
            // Redirect to app with error if mobile
            $userAgent = $request->header('User-Agent');
            $isMobileApp = str_contains($userAgent, 'ReactNative') || 
                          str_contains($userAgent, 'Expo') ||
                          $request->has('mobile');
                          
            if ($isMobileApp) {
                return redirect('5str://auth/google?error=' . urlencode($e->getMessage()));
            }

            return response()->json([
                'success' => false,
                'message' => 'Authentication failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handle Google OAuth with ID Token (for React frontend)
     */
    public function handleGoogleToken(Request $request)
    {
        try {
            $request->validate([
                'token' => 'required|string',
            ]);

            // Verify the Google ID token
            $client = new \Google_Client(['client_id' => config('services.google.client_id')]);
            $payload = $client->verifyIdToken($request->token);
            
            if (!$payload) {
                return response()->json([
                    'error' => 'Invalid Google token'
                ], 401);
            }

            // Create a mock Google user object matching Socialite's structure
            $googleUser = (object) [
                'id' => $payload['sub'],
                'email' => $payload['email'],
                'name' => $payload['name'],
                'avatar' => $payload['picture'] ?? null,
            ];

            // Process user authentication
            $user = $this->handleGoogleUser($googleUser);
            
            // Create Sanctum token
            $token = $user->createToken('google-auth')->plainTextToken;
            return response()->json([
                            'success' => true,
                            'user' => [
                                'id' => $user->id,
                                'name' => $user->name,
                                'email' => $user->email,
                                'phone' => $user->phone,
                                'profile_image' => $user->avatar,
                                'current_latitude' => $user->current_latitude,
                                'current_longitude' => $user->current_longitude,
                                'city' => $user->city,
                                'total_points' => $user->total_points,
                                'total_reviews_written' => $user->total_reviews_written,
                                'trust_level' => $user->trust_level,
                                'email_verified_at' => $user->email_verified_at,
                                'is_active' => $user->is_active,
                                'google_id' => $user->google_id,
                                'avatar' => $user->avatar,
                                'role' => $user->roles->first()?->name ?? 'user'
                            ],
                            'token' => $token,
                            'token_type' => 'Bearer',
                        ]);
            
            return response()->json([
                'success' => true,
                'user' => $user,
                'token' => $token,
                'token_type' => 'Bearer',
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Authentication failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Helper method to handle Google user data
     */
    private function handleGoogleUser($googleUser)
    {
        // Check if user exists with this Google ID
        $user = User::where('google_id', $googleUser->id)->first();
        
        if ($user) {
            // User exists, update their info
            $user->update([
                'name' => $googleUser->name,
                'email' => $googleUser->email,
                'avatar' => $googleUser->avatar ?? $user->avatar,
            ]);
        } else {
            // Check if user exists with the same email
            $existingUser = User::where('email', $googleUser->email)->first();
            
            if ($existingUser) {
                // Link Google account to existing user
                $existingUser->update([
                    'google_id' => $googleUser->id,
                    'avatar' => $googleUser->avatar ?? $existingUser->avatar,
                ]);
                $user = $existingUser;
            } else {
                // Create new user
                $user = User::create([
                    'name' => $googleUser->name,
                    'email' => $googleUser->email,
                    'google_id' => $googleUser->id,
                    'avatar' => $googleUser->avatar,
                    'email_verified_at' => now(),
                    'password' => null, // No password for Google OAuth users
                    'is_active' => true,
                ]);
            }
        }

        return $user;
    }
}
