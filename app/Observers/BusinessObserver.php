<?php

namespace App\Observers;

use App\Models\Business;
use App\Services\NotificationService;

class BusinessObserver
{
    /**
     * Handle the Business "created" event.
     */
    public function created(Business $business): void
    {
        // When a new business is created, send a welcome notification
        if ($business->owner_user_id) {
            NotificationService::completeProfileReminder($business->owner);
        }
    }

    /**
     * Handle the Business "updated" event.
     */
    public function updated(Business $business): void
    {
        // Check if verification status changed
        if ($business->isDirty('is_verified')) {
            if ($business->is_verified) {
                // Business was approved
                NotificationService::businessApproved($business);
            }
        }

        // Check if business was made active after being inactive
        if ($business->isDirty('is_active') && $business->is_active) {
            NotificationService::businessUpdated($business);
        }

        // General update notification (only if significant fields changed)
        $significantFields = [
            'business_name', 'description', 'business_email', 
            'business_phone', 'full_address', 'opening_hours'
        ];
        
        if ($business->isDirty($significantFields) && $business->owner_user_id) {
            NotificationService::businessUpdated($business);
        }
    }

    /**
     * Handle the Business "deleted" event.
     */
    public function deleted(Business $business): void
    {
        //
    }

    /**
     * Handle the Business "restored" event.
     */
    public function restored(Business $business): void
    {
        //
    }

    /**
     * Handle the Business "force deleted" event.
     */
    public function forceDeleted(Business $business): void
    {
        //
    }
}
