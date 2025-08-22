<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use App\Models\Business;

class NotificationService
{
    /**
     * Send notification when business is approved
     */
    public static function businessApproved(Business $business)
    {
        if ($business->owner) {
            return Notification::create([
                'user_id' => $business->owner_user_id,
                'type' => 'business_approved',
                'title' => 'Business Approved! 🎉',
                'message' => "Congratulations! Your business '{$business->business_name}' has been approved and is now live on our platform. You can now start managing your offerings and promotions.",
                'priority' => 'high',
                'data' => [
                    'business_id' => $business->id,
                    'business_name' => $business->business_name,
                    'action_url' => '/admin/businesses/' . $business->id,
                ]
            ]);
        }
    }

    /**
     * Send notification when business is rejected
     */
    public static function businessRejected(Business $business, $reason = null)
    {
        if ($business->owner) {
            return Notification::create([
                'user_id' => $business->owner_user_id,
                'type' => 'business_rejected',
                'title' => 'Business Application Needs Review',
                'message' => "Your business application for '{$business->business_name}' requires some updates. " . ($reason ? "Reason: {$reason}" : "Please check the details and resubmit."),
                'priority' => 'high',
                'data' => [
                    'business_id' => $business->id,
                    'business_name' => $business->business_name,
                    'reason' => $reason,
                    'action_url' => '/admin/businesses/' . $business->id . '/edit',
                ]
            ]);
        }
    }

    /**
     * Send notification when business receives a new review
     */
    public static function newReviewReceived(Business $business, $review)
    {
        if ($business->owner) {
            return Notification::create([
                'user_id' => $business->owner_user_id,
                'type' => 'review_received',
                'title' => 'New Review Received! ⭐',
                'message' => "Your business '{$business->business_name}' has received a new review with {$review->overall_rating} stars. Check it out and respond if needed.",
                'priority' => 'medium',
                'data' => [
                    'business_id' => $business->id,
                    'business_name' => $business->business_name,
                    'review_id' => $review->id,
                    'rating' => $review->overall_rating,
                    'action_url' => '/admin/reviews?business=' . $business->id,
                ]
            ]);
        }
    }

    /**
     * Send notification when business owner creates a new offer
     */
    public static function offerCreated(Business $business, $offer)
    {
        if ($business->owner) {
            return Notification::create([
                'user_id' => $business->owner_user_id,
                'type' => 'offer_created',
                'title' => 'Offer Created Successfully! 🎁',
                'message' => "Your offer '{$offer->title}' for {$business->business_name} has been created and is now live. Start promoting it to attract more customers!",
                'priority' => 'medium',
                'data' => [
                    'business_id' => $business->id,
                    'business_name' => $business->business_name,
                    'offer_id' => $offer->id,
                    'offer_title' => $offer->title,
                    'action_url' => '/admin/offers/' . $offer->id,
                ]
            ]);
        }
    }

    /**
     * Send reminder to complete business profile
     */
    public static function completeProfileReminder(User $user)
    {
        return Notification::create([
            'user_id' => $user->id,
            'type' => 'reminder',
            'title' => 'Complete Your Business Profile',
            'message' => "Don't forget to complete your business profile to get more visibility and attract customers. Add photos, update opening hours, and create attractive offerings!",
            'priority' => 'low',
            'data' => [
                'action_url' => '/admin/businesses',
                'reminder_type' => 'complete_profile',
            ]
        ]);
    }

    /**
     * Send notification when business owner updates their business
     */
    public static function businessUpdated(Business $business)
    {
        if ($business->owner) {
            return Notification::create([
                'user_id' => $business->owner_user_id,
                'type' => 'profile_updated',
                'title' => 'Business Profile Updated ✅',
                'message' => "Your business profile for '{$business->business_name}' has been successfully updated. The changes are now live on the platform.",
                'priority' => 'low',
                'data' => [
                    'business_id' => $business->id,
                    'business_name' => $business->business_name,
                    'action_url' => '/admin/businesses/' . $business->id,
                ]
            ]);
        }
    }

    /**
     * Send system announcement to all business owners
     */
    public static function systemAnnouncement($title, $message, $priority = 'medium')
    {
        $businessOwners = User::whereHas('ownedBusinesses')->get();
        
        foreach ($businessOwners as $owner) {
            Notification::create([
                'user_id' => $owner->id,
                'type' => 'system_announcement',
                'title' => $title,
                'message' => $message,
                'priority' => $priority,
                'data' => [
                    'announcement_type' => 'system',
                    'target_audience' => 'business_owners',
                ]
            ]);
        }
    }

    /**
     * Send promotional notification
     */
    public static function promotionalMessage(User $user, $title, $message, $actionUrl = null)
    {
        return Notification::create([
            'user_id' => $user->id,
            'type' => 'promotion',
            'title' => $title,
            'message' => $message,
            'priority' => 'medium',
            'data' => [
                'action_url' => $actionUrl,
                'promo_type' => 'marketing',
            ]
        ]);
    }

    /**
     * Get unread notifications count for a user
     */
    public static function getUnreadCount($userId)
    {
        return Notification::where('user_id', $userId)
            ->where('is_read', false)
            ->count();
    }

    /**
     * Mark notification as read
     */
    public static function markAsRead($notificationId)
    {
        return Notification::where('id', $notificationId)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
    }

    /**
     * Mark all notifications as read for a user
     */
    public static function markAllAsRead($userId)
    {
        return Notification::where('user_id', $userId)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
    }
}
