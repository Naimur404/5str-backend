<?php

namespace App\Services;

use App\Models\PushToken;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PushNotificationService
{
    protected string $expoApiUrl = 'https://exp.host/--/api/v2/push/send';
    
    /**
     * Send push notification to a user
     */
    public function sendToUser(User $user, array $notification): bool
    {
        $tokens = $user->pushTokens()->active()->pluck('token')->toArray();
        
        if (empty($tokens)) {
            Log::info("No active push tokens found for user {$user->id}");
            return false;
        }

        return $this->sendToTokens($tokens, $notification);
    }

    /**
     * Send push notification to multiple users
     */
    public function sendToUsers(array $userIds, array $notification): bool
    {
        $tokens = PushToken::whereIn('user_id', $userIds)
            ->active()
            ->pluck('token')
            ->toArray();

        if (empty($tokens)) {
            Log::info("No active push tokens found for users: " . implode(', ', $userIds));
            return false;
        }

        return $this->sendToTokens($tokens, $notification);
    }

    /**
     * Send push notification to all users (broadcast)
     */
    public function sendToAllUsers(array $notification): bool
    {
        $tokens = PushToken::active()->pluck('token')->toArray();

        if (empty($tokens)) {
            Log::info("No active push tokens found for broadcast notification");
            return false;
        }

        return $this->sendToTokens($tokens, $notification);
    }

    /**
     * Send push notification to specific tokens
     */
    public function sendToTokens(array $tokens, array $notification): bool
    {
        try {
            $messages = [];
            
            foreach ($tokens as $token) {
                $messages[] = array_merge([
                    'to' => $token,
                    'sound' => 'default',
                    'priority' => 'high',
                    'badge' => 1,
                ], $notification);
            }

            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Accept-encoding' => 'gzip, deflate',
                'Content-Type' => 'application/json',
            ])->timeout(30)->post($this->expoApiUrl, $messages);

            $result = $response->json();
            
            Log::info('Push notification sent', [
                'tokens_count' => count($tokens),
                'notification_title' => $notification['title'] ?? 'No title',
                'response_status' => $response->status(),
                'response' => $result
            ]);

            // Handle response and update token status if needed
            $this->handleExpoResponse($result, $tokens);

            return $response->successful();

        } catch (\Exception $e) {
            Log::error('Failed to send push notification', [
                'error' => $e->getMessage(),
                'tokens_count' => count($tokens),
                'notification' => $notification
            ]);
            return false;
        }
    }

    /**
     * Handle Expo push notification response
     */
    protected function handleExpoResponse(array $response, array $tokens): void
    {
        if (!isset($response['data'])) {
            return;
        }

        foreach ($response['data'] as $index => $result) {
            if (isset($result['status']) && $result['status'] === 'error') {
                $token = $tokens[$index] ?? null;
                
                if ($token && isset($result['details'])) {
                    $error = $result['details'];
                    
                    // Deactivate invalid tokens
                    if (isset($error['error']) && in_array($error['error'], ['DeviceNotRegistered', 'InvalidCredentials', 'MessageTooBig'])) {
                        PushToken::where('token', $token)->update(['is_active' => false]);
                        Log::info("Deactivated invalid push token: {$token}, Error: {$error['error']}");
                    }
                }
            }
        }
    }

    /**
     * Create notification for review approval
     */
    public function createReviewApprovalNotification(string $reviewTitle, int $businessId = null): array
    {
        return [
            'title' => '✅ Review Approved!',
            'body' => "Your review '{$reviewTitle}' has been approved and is now visible to other users. Thank you for your feedback!",
            'data' => [
                'type' => 'review_approved',
                'action' => 'open_reviews',
                'business_id' => $businessId,
                'timestamp' => now()->toISOString(),
            ],
            'badge' => 1,
            'channelId' => 'reviews',
        ];
    }

    /**
     * Create notification for new offer
     */
    public function createNewOfferNotification(string $businessName, string $offerTitle, int $businessId = null, int $offerId = null): array
    {
        return [
            'title' => '🎉 New Offer Available!',
            'body' => "{$businessName} has a new offer: {$offerTitle}",
            'data' => [
                'type' => 'new_offer',
                'action' => 'open_offers',
                'business_id' => $businessId,
                'offer_id' => $offerId,
                'timestamp' => now()->toISOString(),
            ],
            'badge' => 1,
            'channelId' => 'promotions',
        ];
    }

    /**
     * Create notification for business trending status
     */
    public function createTrendingBusinessNotification(string $businessName, int $businessId, string $area): array
    {
        return [
            'title' => '📈 Your Business is Trending!',
            'body' => "{$businessName} is trending in {$area}! More customers are discovering your business.",
            'data' => [
                'type' => 'business_trending',
                'action' => 'open_business',
                'business_id' => $businessId,
                'area' => $area,
                'timestamp' => now()->toISOString(),
            ],
            'badge' => 1,
            'channelId' => 'business',
        ];
    }

    /**
     * Create notification for new business review
     */
    public function createNewReviewNotification(string $businessName, int $businessId, float $rating): array
    {
        $stars = str_repeat('⭐', (int) $rating);
        
        return [
            'title' => '📝 New Review Received!',
            'body' => "Someone left a {$stars} review for {$businessName}. Check it out!",
            'data' => [
                'type' => 'new_review',
                'action' => 'open_reviews',
                'business_id' => $businessId,
                'rating' => $rating,
                'timestamp' => now()->toISOString(),
            ],
            'badge' => 1,
            'channelId' => 'reviews',
        ];
    }

    /**
     * Create notification for favorite business update
     */
    public function createFavoriteBusinessUpdateNotification(string $businessName, int $businessId, string $updateType): array
    {
        $messages = [
            'new_offer' => "🎁 {$businessName} added a new offer!",
            'hours_updated' => "🕒 {$businessName} updated their hours.",
            'menu_updated' => "📄 {$businessName} updated their menu/services.",
            'featured' => "⭐ {$businessName} is now featured!"
        ];

        $body = $messages[$updateType] ?? "{$businessName} has an important update!";

        return [
            'title' => '❤️ Favorite Business Update',
            'body' => $body,
            'data' => [
                'type' => 'favorite_business_update',
                'action' => 'open_business',
                'business_id' => $businessId,
                'update_type' => $updateType,
                'timestamp' => now()->toISOString(),
            ],
            'badge' => 1,
            'channelId' => 'favorites',
        ];
    }

    /**
     * Create notification for system announcements
     */
    public function createSystemAnnouncementNotification(string $title, string $message, string $type = 'general'): array
    {
        return [
            'title' => "📢 {$title}",
            'body' => $message,
            'data' => [
                'type' => 'system_announcement',
                'action' => 'open_app',
                'announcement_type' => $type,
                'timestamp' => now()->toISOString(),
            ],
            'badge' => 1,
            'channelId' => 'system',
        ];
    }

    /**
     * Create notification for promotional campaigns
     */
    public function createPromotionalNotification(string $title, string $message, array $additionalData = []): array
    {
        return [
            'title' => "🎯 {$title}",
            'body' => $message,
            'data' => array_merge([
                'type' => 'promotional',
                'action' => 'open_promotions',
                'timestamp' => now()->toISOString(),
            ], $additionalData),
            'badge' => 1,
            'channelId' => 'promotions',
        ];
    }

    /**
     * Validate if a token is a valid Expo push token format
     */
    public function isValidExpoPushToken(string $token): bool
    {
        // Expo push tokens typically start with "ExponentPushToken[" or "ExpoPushToken["
        return preg_match('/^Expo(nent)?PushToken\[.+\]$/', $token) === 1;
    }

    /**
     * Get push notification statistics
     */
    public function getStatistics(): array
    {
        return [
            'total_tokens' => PushToken::count(),
            'active_tokens' => PushToken::active()->count(),
            'ios_tokens' => PushToken::active()->where('platform', 'ios')->count(),
            'android_tokens' => PushToken::active()->where('platform', 'android')->count(),
            'users_with_notifications' => PushToken::active()->distinct('user_id')->count(),
            'recent_registrations' => PushToken::where('created_at', '>=', now()->subDays(7))->count(),
        ];
    }

    /**
     * Clean up inactive tokens older than specified days
     */
    public function cleanupInactiveTokens(int $daysOld = 30): int
    {
        $deletedCount = PushToken::where('is_active', false)
            ->where('updated_at', '<', now()->subDays($daysOld))
            ->delete();

        Log::info("Cleaned up {$deletedCount} inactive push tokens older than {$daysOld} days");
        
        return $deletedCount;
    }
}
