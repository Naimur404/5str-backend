<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Admin Notification Settings
    |--------------------------------------------------------------------------
    |
    | This file controls which events will send notifications to admins.
    | Set to false to prevent admin notifications for specific events.
    |
    */

    'admin_notifications' => [
        
        // Business Events
        'business_created' => true,          // When new business registers
        'business_verification' => false,    // When business is verified (handled manually)
        'business_updated' => false,         // When business profile is updated
        
        // Offer Events  
        'offer_created' => false,            // When new offer is created
        'offer_updated' => false,            // When offer is updated
        
        // Review Events
        'review_submitted' => true,          // When new review needs approval - IMPORTANT
        'review_approved' => false,          // When review is approved (user gets notified)
        'review_rejected' => false,          // When review is rejected (user gets notified)
        
        // System Events
        'user_registered' => false,         // When new user registers
        'suspicious_activity' => true,      // When suspicious activity detected
        'system_errors' => true,            // When system errors occur
    ],

    /*
    |--------------------------------------------------------------------------
    | User Notification Settings  
    |--------------------------------------------------------------------------
    |
    | Control which events send notifications to users
    |
    */

    'user_notifications' => [
        
        // Business Owner Notifications
        'business_created' => true,
        'business_verified' => true,
        'business_activated' => true,
        'business_updated' => true,
        'offer_created' => true,
        'offer_activated' => true,
        
        // Customer Notifications
        'review_approved' => true,
        'review_rejected' => true,
        'review_deleted' => true,
        'favorite_business_updated' => true,
        'new_offer_nearby' => true,
    ],
];
