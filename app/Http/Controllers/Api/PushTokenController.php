<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PushToken;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class PushTokenController extends Controller
{
    /**
     * Store or update a push token for the authenticated user
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'token' => 'required|string|max:500',
                'platform' => 'required|in:ios,android',
                'device_id' => 'nullable|string|max:255',
            ]);

            $user = Auth::user();
            
            // Check if token already exists for this user
            $existingToken = PushToken::where('token', $request->token)
                ->where('user_id', $user->id)
                ->first();

            if ($existingToken) {
                // Update existing token
                $existingToken->update([
                    'platform' => $request->platform,
                    'device_id' => $request->device_id,
                    'is_active' => true,
                    'last_used_at' => now(),
                ]);
                
                $pushToken = $existingToken;
                $message = 'Push token updated successfully! You\'ll now receive notifications on this device.';
            } else {
                // Create new token
                $pushToken = PushToken::create([
                    'user_id' => $user->id,
                    'token' => $request->token,
                    'platform' => $request->platform,
                    'device_id' => $request->device_id,
                    'is_active' => true,
                    'last_used_at' => now(),
                ]);
                
                $message = 'Push token registered successfully! You\'re all set to receive notifications.';
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'token_id' => $pushToken->id,
                    'platform' => $pushToken->platform,
                    'device_id' => $pushToken->device_id,
                    'is_active' => $pushToken->is_active,
                    'registered_at' => $pushToken->created_at,
                    'last_used_at' => $pushToken->last_used_at,
                ]
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid push token information. Please check your device settings.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to save push token: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'request' => $request->only(['platform', 'device_id']),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Oops! We couldn\'t register your device for notifications. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Update a push token (same logic as store)
     */
    public function update(Request $request): JsonResponse
    {
        return $this->store($request);
    }

    /**
     * Get user's push tokens
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $tokens = $user->pushTokens()
                ->orderBy('last_used_at', 'desc')
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function($token) {
                    return [
                        'id' => $token->id,
                        'platform' => $token->platform,
                        'device_id' => $token->device_id,
                        'is_active' => $token->is_active,
                        'registered_at' => $token->created_at,
                        'last_used_at' => $token->last_used_at,
                        'last_used_human' => $token->last_used_at ? $token->last_used_at->diffForHumans() : null,
                    ];
                });

            $stats = [
                'total_devices' => $tokens->count(),
                'active_devices' => $tokens->where('is_active', true)->count(),
                'ios_devices' => $tokens->where('platform', 'ios')->count(),
                'android_devices' => $tokens->where('platform', 'android')->count(),
            ];

            return response()->json([
                'success' => true,
                'message' => 'Your notification devices retrieved successfully.',
                'data' => [
                    'push_tokens' => $tokens,
                    'stats' => $stats
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch push tokens: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to retrieve your notification settings. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Delete a push token
     */
    public function destroy(Request $request, $tokenId = null): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if ($tokenId) {
                // Delete specific token by ID
                $token = $user->pushTokens()->where('id', $tokenId)->first();
                
                if (!$token) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Notification device not found.'
                    ], 404);
                }
                
                $platform = $token->platform;
                $token->delete();
                
                $message = "🔕 Your {$platform} device has been removed from notifications. You won't receive alerts on this device anymore.";
                $deletedCount = 1;
            } else {
                // Delete all tokens for user
                $deletedCount = $user->pushTokens()->count();
                $user->pushTokens()->delete();
                
                $message = $deletedCount > 0 
                    ? "🔕 All notification devices removed successfully! You won't receive push notifications until you re-enable them."
                    : "No notification devices were found to remove.";
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'deleted_count' => $deletedCount,
                    'removed_at' => now(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to delete push token: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'token_id' => $tokenId,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to remove notification device. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Test push notification for the authenticated user
     */
    public function testNotification(Request $request): JsonResponse
    {
        try {
            // Only allow in development/testing
            if (!config('app.debug')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Test notifications are only available in debug mode'
                ], 403);
            }

            $request->validate([
                'title' => 'nullable|string|max:255',
                'body' => 'nullable|string|max:500',
            ]);

            $user = Auth::user();
            $activeTokens = $user->pushTokens()->active()->count();
            
            if ($activeTokens === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active notification devices found. Please register a device first.'
                ], 400);
            }

            // Import the push notification service
            $pushService = app(\App\Services\PushNotificationService::class);
            
            $notification = [
                'title' => $request->input('title', '🧪 Test Notification'),
                'body' => $request->input('body', 'This is a test notification from your 5str app! If you can see this, notifications are working perfectly.'),
                'data' => [
                    'type' => 'test',
                    'timestamp' => now()->toISOString(),
                ],
                'badge' => 1,
                'channelId' => 'test',
            ];

            $sent = $pushService->sendToUser($user, $notification);

            return response()->json([
                'success' => $sent,
                'message' => $sent 
                    ? "🎉 Test notification sent successfully! Check your device for the notification."
                    : "❌ Failed to send test notification. Please check your device settings and try again.",
                'data' => [
                    'notification_sent' => $sent,
                    'active_devices' => $activeTokens,
                    'sent_at' => now(),
                ]
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid test notification parameters.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to send test notification: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'request' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to send test notification. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Update token status (activate/deactivate)
     */
    public function updateStatus(Request $request, $tokenId): JsonResponse
    {
        try {
            $request->validate([
                'is_active' => 'required|boolean',
            ]);

            $user = Auth::user();
            $token = $user->pushTokens()->where('id', $tokenId)->first();
            
            if (!$token) {
                return response()->json([
                    'success' => false,
                    'message' => 'Notification device not found.'
                ], 404);
            }

            $isActive = $request->input('is_active');
            $token->update(['is_active' => $isActive]);

            $message = $isActive 
                ? "✅ Notifications enabled for your {$token->platform} device! You'll receive alerts again."
                : "⏸️ Notifications paused for your {$token->platform} device. You can re-enable them anytime.";

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'token_id' => $token->id,
                    'platform' => $token->platform,
                    'is_active' => $token->is_active,
                    'updated_at' => $token->updated_at,
                ]
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid status update parameters.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to update push token status: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'token_id' => $tokenId,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to update notification settings. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}
