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
     * Redirect to Google OAuth (Stateless)
     */
    public function redirect()
    {
        try {
            return response()->json([
                'success' => true,
                'url' => Socialite::driver('google')->stateless()->redirect()->getTargetUrl(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to generate Google OAuth URL',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handle Google OAuth callback (Stateless)
     */
    public function callback(Request $request)
    {
        try {
            // Get user from Google using stateless driver
            $googleUser = Socialite::driver('google')->stateless()->user();
            
            // Process user authentication
            $user = $this->handleGoogleUser($googleUser);
            
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
