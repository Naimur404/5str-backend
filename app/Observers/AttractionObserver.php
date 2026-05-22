<?php

namespace App\Observers;

use App\Models\Attraction;
use App\Models\User;
use App\Notifications\DatabaseNotification;
use App\Services\CacheInvalidationService;

class AttractionObserver
{
    /**
     * Handle the Attraction "created" event.
     */
    public function created(Attraction $attraction): void
    {
        if (config('notifications.admin_notifications.attraction_created', true) && $attraction->name) {
            $admins = User::whereHas('roles', function ($query) {
                $query->whereIn('name', ['admin', 'super-admin', 'moderator']);
            })->get();

            foreach ($admins as $admin) {
                $admin->notify(new DatabaseNotification(
                    title: 'New Attraction Added',
                    body: "'{$attraction->name}' has been added and may need review.",
                    icon: 'heroicon-o-map-pin',
                    color: 'warning',
                ));
            }
        }

        CacheInvalidationService::onAttractionChange();
    }

    /**
     * Handle the Attraction "updated" event.
     */
    public function updated(Attraction $attraction): void
    {
        CacheInvalidationService::onAttractionChange();
    }

    /**
     * Handle the Attraction "deleted" event.
     */
    public function deleted(Attraction $attraction): void
    {
        CacheInvalidationService::onAttractionChange();
    }
}
