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
     * Redirect to Google OAuth
     */
    public function redirect()
    {
        try {
            return response()->json([
                'url' => Socialite::driver('google')->redirect()->getTargetUrl()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to generate Google OAuth URL',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handle Google OAuth callback
     */
    public function callback(Request $request)
    {
        try {
            // Get user from Google
            $googleUser = Socialite::driver('google')->user();
            
            // Check if user exists with this Google ID
            $user = User::where('google_id', $googleUser->id)->first();
            
            if ($user) {
                // User exists, update their info and log them in
                $user->update([
                    'name' => $googleUser->name,
                    'email' => $googleUser->email,
                    'avatar' => $googleUser->avatar,
                ]);
            } else {
                // Check if user exists with the same email
                $existingUser = User::where('email', $googleUser->email)->first();
                
                if ($existingUser) {
                    // Link Google account to existing user
                    $existingUser->update([
                        'google_id' => $googleUser->id,
                        'avatar' => $googleUser->avatar,
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
                        'password' => bcrypt(Str::random(24)), // Random password since they're using Google OAuth
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
