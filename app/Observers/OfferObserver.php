<?php

namespace App\Observers;

use App\Models\Offer;
use App\Models\User;
use App\Notifications\DatabaseNotification;
use App\Services\CacheInvalidationService;
use Illuminate\Support\Facades\Auth;

class OfferObserver
{
    private const ADMIN_ROLES = ['admin', 'super-admin', 'moderator'];

    public function created(Offer $offer): void
    {
        $ownerId = $offer->business?->owner_user_id;

        if ($ownerId) {
            $owner = User::find($ownerId);
            $owner?->notify(new DatabaseNotification(
                title: 'New Offer Created',
                body: "Your offer '{$offer->title}' has been created successfully.",
                icon: 'heroicon-o-gift',
                color: 'success',
            ));
        }

        $currentUser = Auth::user();
        if ($currentUser
            && $currentUser->id !== $ownerId
            && $currentUser->hasAnyRole(self::ADMIN_ROLES)) {
            $businessName = $offer->business?->business_name ?? 'business';
            $currentUser->notify(new DatabaseNotification(
                title: 'Offer Created Successfully',
                body: "You have successfully created the offer '{$offer->title}' for {$businessName}.",
                icon: 'heroicon-o-check-circle',
                color: 'success',
            ));
        }

        CacheInvalidationService::onOfferChange();
    }

    public function updated(Offer $offer): void
    {
        $ownerId = $offer->business?->owner_user_id;

        if ($offer->isDirty('is_active') && $offer->is_active && $ownerId) {
            $owner = User::find($ownerId);
            $owner?->notify(new DatabaseNotification(
                title: 'Offer Activated',
                body: "Your offer '{$offer->title}' is now active and visible to customers.",
                icon: 'heroicon-o-check-circle',
                color: 'success',
            ));
        }

        CacheInvalidationService::onOfferChange();
    }

    public function deleted(Offer $offer): void
    {
        CacheInvalidationService::onOfferChange();
    }

    public function restored(Offer $offer): void
    {
    }

    public function forceDeleted(Offer $offer): void
    {
    }
}
