<?php

namespace App\Observers;

use App\Models\Offer;
use App\Services\NotificationService;

class OfferObserver
{
    /**
     * Handle the Offer "created" event.
     */
    public function created(Offer $offer): void
    {
        // Send notification when a new offer is created
        if ($offer->business && $offer->business->owner_user_id) {
            NotificationService::offerCreated($offer->business, $offer);
        }
    }

    /**
     * Handle the Offer "updated" event.
     */
    public function updated(Offer $offer): void
    {
        // Send notification if offer becomes active
        if ($offer->isDirty('is_active') && $offer->is_active && $offer->business && $offer->business->owner_user_id) {
            NotificationService::offerCreated($offer->business, $offer);
        }
    }

    /**
     * Handle the Offer "deleted" event.
     */
    public function deleted(Offer $offer): void
    {
        //
    }

    /**
     * Handle the Offer "restored" event.
     */
    public function restored(Offer $offer): void
    {
        //
    }

    /**
     * Handle the Offer "force deleted" event.
     */
    public function forceDeleted(Offer $offer): void
    {
        //
    }
}
