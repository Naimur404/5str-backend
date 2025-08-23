<?php

namespace App\Observers;

use App\Models\Offer;
use App\Models\User;
use Filament\Notifications\Notification;
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
                Notification::make()
                    ->title('New Offer Created')
                    ->body("Your offer '{$offer->title}' has been created successfully.")
                    ->icon('heroicon-o-gift')
                    ->color('success')
                    ->sendToDatabase($owner);
            }
        }

        // Also notify the admin who created the offer (if they're different from business owner)
        $currentUser = Auth::user();
        if ($currentUser && $currentUser->hasAnyRole(['admin', 'super-admin', 'moderator'])) {
            // Don't send duplicate notification if admin is also the business owner
            if (!$offer->business || $currentUser->id !== $offer->business->owner_user_id) {
                Notification::make()
                    ->title('Offer Created Successfully')
                    ->body("You have successfully created the offer '{$offer->title}' for {$offer->business->business_name}.")
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->sendToDatabase($currentUser);
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
