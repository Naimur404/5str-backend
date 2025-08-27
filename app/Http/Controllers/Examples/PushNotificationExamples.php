<?php

/**
 * Push Notification Integration Examples
 * 
 * This file demonstrates how to integrate push notifications with different parts of your application.
 * These are example implementations that show the proper usage of the PushNotificationService.
 */

namespace App\Http\Controllers\Examples;

use App\Http\Controllers\Controller;
use App\Services\PushNotificationService;
use App\Models\Review;
use App\Models\Offer;
use App\Models\User;
use App\Models\Business;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PushNotificationExamples extends Controller
{
    protected PushNotificationService $pushService;

    public function __construct(PushNotificationService $pushService)
    {
        $this->pushService = $pushService;
    }

    /**
     * Example 1: Review Approval (as implemented in ReviewController)
     * This is the main example from the usage guide
     */
    public function approveReviewExample($reviewId)
    {
        $review = Review::with(['user', 'reviewable'])->findOrFail($reviewId);
        
        // Update review status
        $review->update(['status' => 'approved', 'approved_at' => now()]);
        
        // Get the reviewed item title
        $itemTitle = '';
        if ($review->reviewable_type === 'App\\Models\\Business') {
            $itemTitle = $review->reviewable->business_name ?? 'Business';
        } elseif ($review->reviewable_type === 'App\\Models\\BusinessOffering') {
            $itemTitle = $review->reviewable->name ?? 'Service';
        }

        // Create database notification (for notification list)
        $review->user->notifications()->create([
            'title' => 'Review Approved! 🎉',
            'body' => "Your review for \"{$itemTitle}\" has been approved and is now visible to other users. Thank you for your valuable feedback!",
            'icon' => 'heroicon-o-check-circle',
            'color' => 'success',
            'type' => 'review_approved',
            'data' => ['review_id' => $review->id],
        ]);

        // Send push notification (for real-time alert)
        $notification = $this->pushService->createReviewApprovalNotification($itemTitle);
        $this->pushService->sendToUser($review->user, $notification);

        return response()->json(['success' => true, 'message' => 'Review approved and user notified']);
    }

    /**
     * Example 2: New Offer Notification
     * Send push notification when a new offer is created
     */
    public function notifyNewOffer($offerId)
    {
        $offer = Offer::with('business')->findOrFail($offerId);
        
        // Get users who favorited this business
        $interestedUsers = User::whereHas('favorites', function($query) use ($offer) {
            $query->where('favoritable_type', 'App\\Models\\Business')
                  ->where('favoritable_id', $offer->business_id);
        })->get();

        foreach ($interestedUsers as $user) {
            // Create database notification
            $user->notifications()->create([
                'title' => 'New Offer Available! 🎁',
                'body' => "A new offer is available at {$offer->business->business_name}. Check it out now!",
                'icon' => 'heroicon-o-gift',
                'color' => 'info',
                'type' => 'new_offer',
                'data' => [
                    'offer_id' => $offer->id,
                    'business_id' => $offer->business_id,
                ],
            ]);

            // Send push notification
            try {
                $notification = $this->pushService->createNewOfferNotification(
                    $offer->business->business_name,
                    $offer->title,
                    $offer->business_id,
                    $offer->id
                );
                $this->pushService->sendToUser($user, $notification);
            } catch (\Exception $e) {
                Log::error("Failed to send offer notification", [
                    'user_id' => $user->id,
                    'offer_id' => $offer->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return response()->json([
            'success' => true, 
            'message' => "Notified {$interestedUsers->count()} users about the new offer"
        ]);
    }

    /**
     * Example 3: Trending Business Notification
     * Notify users in a specific area about trending businesses
     */
    public function notifyTrendingBusiness($businessId, $area = 'Dhanmondi')
    {
        $business = Business::findOrFail($businessId);
        
        // Get users in the area (simplified location matching)
        $usersInArea = User::where('city', 'like', "%{$area}%")
                          ->orWhere('address', 'like', "%{$area}%")
                          ->get();

        foreach ($usersInArea as $user) {
            // Create database notification
            $user->notifications()->create([
                'title' => 'Trending Now in Your Area! 📈',
                'body' => "{$business->business_name} is trending in {$area}. Don't miss out!",
                'icon' => 'heroicon-o-trending-up',
                'color' => 'warning',
                'type' => 'trending_business',
                'data' => [
                    'business_id' => $business->id,
                    'area' => $area,
                ],
            ]);

            // Send push notification
            try {
                $notification = $this->pushService->createTrendingBusinessNotification(
                    $business->business_name,
                    $business->id,
                    $area
                );
                $this->pushService->sendToUser($user, $notification);
            } catch (\Exception $e) {
                Log::error("Failed to send trending notification", [
                    'user_id' => $user->id,
                    'business_id' => $business->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Notified {$usersInArea->count()} users about trending business"
        ]);
    }

    /**
     * Example 4: System Announcement
     * Send announcement to all active users
     */
    public function sendSystemAnnouncement($title, $message)
    {
        // Get all users with active push tokens
        $users = User::whereHas('activePushTokens')->get();

        $successCount = 0;
        $failCount = 0;

        foreach ($users as $user) {
            try {
                // Create database notification
                $user->notifications()->create([
                    'title' => $title,
                    'body' => $message,
                    'icon' => 'heroicon-o-megaphone',
                    'color' => 'info',
                    'type' => 'system_announcement',
                    'data' => ['announcement_type' => 'general'],
                ]);

                // Send push notification
                $notification = $this->pushService->createSystemAnnouncementNotification($title, $message);
                $this->pushService->sendToUser($user, $notification);
                $successCount++;
                
            } catch (\Exception $e) {
                $failCount++;
                Log::error("Failed to send system announcement", [
                    'user_id' => $user->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => "System announcement sent successfully",
            'data' => [
                'total_users' => $users->count(),
                'success_count' => $successCount,
                'fail_count' => $failCount
            ]
        ]);
    }

    /**
     * Example 5: Promotional Campaign
     * Send promotional message to targeted users
     */
    public function sendPromotionalCampaign($userIds, $campaignTitle, $campaignMessage)
    {
        $users = User::whereIn('id', $userIds)->get();

        foreach ($users as $user) {
            try {
                // Create database notification
                $user->notifications()->create([
                    'title' => $campaignTitle,
                    'body' => $campaignMessage,
                    'icon' => 'heroicon-o-sparkles',
                    'color' => 'success',
                    'type' => 'promotional_campaign',
                    'data' => ['campaign_type' => 'targeted'],
                ]);

                // Send push notification
                $notification = $this->pushService->createPromotionalNotification(
                    $campaignTitle,
                    $campaignMessage,
                    ['campaign_type' => 'targeted']
                );
                $this->pushService->sendToUser($user, $notification);
                
            } catch (\Exception $e) {
                Log::error("Failed to send promotional campaign", [
                    'user_id' => $user->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Promotional campaign sent to {$users->count()} users"
        ]);
    }

    /**
     * Example 6: Bulk Notification to Multiple Users
     * Efficient way to send the same notification to multiple users
     */
    public function sendBulkNotification(array $userIds, $title, $body, $type = 'general')
    {
        try {
            // Prepare notification data
            $notification = [
                'title' => $title,
                'body' => $body,
                'data' => ['type' => $type]
            ];

            // Send to multiple users at once (more efficient)
            $result = $this->pushService->sendToUsers($userIds, $notification);

            // Also create database notifications for each user
            $users = User::whereIn('id', $userIds)->get();
            foreach ($users as $user) {
                $user->notifications()->create([
                    'title' => $title,
                    'body' => $body,
                    'icon' => 'heroicon-o-bell',
                    'color' => 'info',
                    'type' => $type,
                    'data' => ['bulk_notification' => true],
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Bulk notification sent successfully',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send bulk notification',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Example 7: Conditional Notification
     * Send notification based on user preferences or conditions
     */
    public function sendConditionalNotification($businessId, $eventType)
    {
        $business = Business::findOrFail($businessId);
        
        // Get users who have this business in favorites AND have notification preferences enabled
        $targetUsers = User::whereHas('favorites', function($query) use ($business) {
            $query->where('favoritable_type', 'App\\Models\\Business')
                  ->where('favoritable_id', $business->id);
        })
        ->whereHas('activePushTokens') // Only users with active push tokens
        ->get();

        $notificationsSent = 0;

        foreach ($targetUsers as $user) {
            // Check user notification preferences (if you have them)
            // if (!$user->wants_business_notifications) continue;

            try {
                $message = '';
                $icon = 'heroicon-o-bell';
                
                switch ($eventType) {
                    case 'new_menu_item':
                        $message = "{$business->business_name} added a new menu item! Check it out.";
                        $icon = 'heroicon-o-plus-circle';
                        break;
                    case 'price_drop':
                        $message = "Great news! {$business->business_name} has reduced prices on select items.";
                        $icon = 'heroicon-o-arrow-trending-down';
                        break;
                    case 'reopening':
                        $message = "{$business->business_name} is now open! Visit them today.";
                        $icon = 'heroicon-o-building-storefront';
                        break;
                    default:
                        $message = "Something new is happening at {$business->business_name}!";
                }

                // Create database notification
                $user->notifications()->create([
                    'title' => 'Update from Your Favorite! ⭐',
                    'body' => $message,
                    'icon' => $icon,
                    'color' => 'info',
                    'type' => 'business_update',
                    'data' => [
                        'business_id' => $business->id,
                        'event_type' => $eventType,
                    ],
                ]);

                // Send push notification
                $notification = [
                    'title' => 'Update from Your Favorite! ⭐',
                    'body' => $message,
                    'data' => [
                        'business_id' => $business->id,
                        'event_type' => $eventType,
                    ]
                ];
                
                $this->pushService->sendToUser($user, $notification);
                $notificationsSent++;
                
            } catch (\Exception $e) {
                Log::error("Failed to send conditional notification", [
                    'user_id' => $user->id,
                    'business_id' => $business->id,
                    'event_type' => $eventType,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Sent {$notificationsSent} conditional notifications for {$eventType}",
            'data' => [
                'business_name' => $business->business_name,
                'event_type' => $eventType,
                'notifications_sent' => $notificationsSent,
                'target_users' => $targetUsers->count()
            ]
        ]);
    }
}

/**
 * Integration Tips:
 * 
 * 1. Always wrap push notification calls in try-catch blocks
 * 2. Create database notifications first, then send push notifications
 * 3. Log failures for debugging and monitoring
 * 4. Use queued jobs for bulk notifications to avoid timeouts
 * 5. Respect user notification preferences
 * 6. Consider rate limiting for frequent notifications
 * 7. Test with different device states (app backgrounded, notifications disabled, etc.)
 * 8. Monitor delivery rates and handle failed tokens
 */
