<?php

namespace App\Observers;

use App\Models\Offer;
use App\Models\User;
use Filament\Notifications\Notification;

class OfferObserver
{
    /**
     * Handle the Offer "created" event.
     */
    public function created(Offer $offer): void
    {
        // Only send notification to business owner when offer is created
        // Admins don't need to be notified for every offer creation
        if ($offer->business && $offer->business->owner_user_id) {
            $owner = User::find($offer->business->owner_user_id);
            if ($owner) {
                Notification::make()
                    ->title('New Offer Created')
                    ->body("Your offer '{$offer->title}' has been created successfully.")
                    ->icon('heroicon-o-gift')
                    ->color('success')
                    ->sendToDatabase($owner);
            }
        }
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
                Notification::make()
                    ->title('Offer Activated')
                    ->body("Your offer '{$offer->title}' is now active and visible to customers.")
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->sendToDatabase($owner);
            }
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
