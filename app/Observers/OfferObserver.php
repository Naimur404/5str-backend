<?php

namespace App\Observers;

use App\Models\Offer;
use App\Models\User;
use App\Notifications\DatabaseNotification;
use App\Services\CacheInvalidationService;
use Illuminate\Support\Facades\Auth;

class OfferObserver
{
    /**
     * Handle the Offer "created" event.
     */
    public function created(Offer $offer): void
    {
        // Send notification to business owner when offer is created
        if ($offer->business && $offer->business->owner_user_id) {
            $owner = User::find($offer->business->owner_user_id);
            if ($owner) {
                $owner->notify(new DatabaseNotification(
                    title: 'New Offer Created',
                    body: "Your offer '{$offer->title}' has been created successfully.",
                    icon: 'heroicon-o-gift',
                    color: 'success',
                ));
            }
        }

        // Also notify the admin who created the offer (if they're different from business owner)
        $currentUser = Auth::user();
        if ($currentUser && $currentUser->hasAnyRole(['admin', 'super-admin', 'moderator'])) {
            // Don't send duplicate notification if admin is also the business owner
            if (!$offer->business || $currentUser->id !== $offer->business->owner_user_id) {
                $currentUser->notify(new DatabaseNotification(
                    title: 'Offer Created Successfully',
                    body: "You have successfully created the offer '{$offer->title}' for {$offer->business->business_name}.",
                    icon: 'heroicon-o-check-circle',
                    color: 'success',
                ));
            }
        }

        // Invalidate caches affected by new offer
        CacheInvalidationService::onOfferChange();
    }

    /**
     * Handle the Offer "updated" event.
     */
    public function updated(Offer $offer): void
    {
        // Send notification if offer becomes active
        if ($offer->isDirty('is_active') && $offer->is_active && $offer->business && $offer->business->owner_user_id) {
            $owner = User::find($offer->business->owner_user_id);
            if ($owner) {
                $owner->notify(new DatabaseNotification(
                    title: 'Offer Activated',
                    body: "Your offer '{$offer->title}' is now active and visible to customers.",
                    icon: 'heroicon-o-check-circle',
                    color: 'success',
                ));
            }
        }

        // Invalidate caches affected by offer update
        CacheInvalidationService::onOfferChange();
    }

    /**
     * Handle the Offer "deleted" event.
     */
    public function deleted(Offer $offer): void
    {
        // Invalidate caches affected by offer deletion
        CacheInvalidationService::onOfferChange();
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
