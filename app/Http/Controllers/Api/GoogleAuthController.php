<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class GoogleAuthController extends Controller
{
    /**
     * Redirect to Google OAuth (for web applications)
     * Note: For React Native, use the /token endpoint instead
     */
    public function redirect()
    {
        try {
            // Build Google OAuth URL manually for API use
            $clientId = config('services.google.client_id');
            $redirectUri = config('services.google.redirect');
            
            $url = "https://accounts.google.com/oauth/authorize?" . http_build_query([
                'client_id' => $clientId,
                'redirect_uri' => $redirectUri,
                'scope' => 'openid email profile',
                'response_type' => 'code',
                'access_type' => 'offline',
            ]);
            
            return response()->json([
                'url' => $url,
                'note' => 'For React Native apps, use POST /auth/google/token instead'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to generate Google OAuth URL',
                'message' => $e->getMessage(),
                'note' => 'For React Native apps, use the /token endpoint instead'
            ], 500);
        }
    }

    /**
     * Handle Google OAuth callback (for web applications)
     * Note: For React Native, use the /token endpoint instead
     */
    public function callback(Request $request)
    {
        try {
            return response()->json([
                'error' => 'This endpoint is for web applications',
                'message' => 'For React Native apps, use POST /auth/google/token with your Google ID token',
                'recommended_endpoint' => '/api/v1/auth/google/token'
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Authentication failed',
                'message' => $e->getMessage()
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

            $googleId = $payload['sub'];
            $email = $payload['email'];
            $name = $payload['name'];
            $avatar = $payload['picture'] ?? null;

            // Check if user exists with this Google ID
            $user = User::where('google_id', $googleId)->first();
            
            if ($user) {
                // User exists, update their info
                $user->update([
                    'name' => $name,
                    'email' => $email,
                    'avatar' => $avatar,
                ]);
            } else {
                // Check if user exists with the same email
                $existingUser = User::where('email', $email)->first();
                
                if ($existingUser) {
                    // Link Google account to existing user
                    $existingUser->update([
                        'google_id' => $googleId,
                        'avatar' => $avatar,
                    ]);
                    $user = $existingUser;
                } else {
                    // Create new user
                    $user = User::create([
                        'name' => $name,
                        'email' => $email,
                        'google_id' => $googleId,
                        'avatar' => $avatar,
                        'email_verified_at' => now(),
                        'password' => null, // No password for Google OAuth users
                        'is_active' => true,
                    ]);
                }
            }
            
            // Create Sanctum token
            $token = $user->createToken('google-auth')->plainTextToken;
            
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
}
