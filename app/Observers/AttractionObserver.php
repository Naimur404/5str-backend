<?php

namespace App\Observers;

use App\Models\Attraction;
use App\Models\User;
use App\Notifications\DatabaseNotification;
use App\Services\CacheInvalidationService;
use Illuminate\Support\Facades\Notification;

class AttractionObserver
{
    private const ADMIN_ROLES = ['admin', 'super-admin', 'moderator'];

    public function created(Attraction $attraction): void
    {
        if (config('notifications.admin_notifications.attraction_created', true) && $attraction->name) {
            $admins = User::query()
                ->whereHas('roles', static fn ($q) => $q->whereIn('name', self::ADMIN_ROLES))
                ->get();

            if ($admins->isNotEmpty()) {
                Notification::send($admins, new DatabaseNotification(
                    title: 'New Attraction Added',
                    body: "'{$attraction->name}' has been added and may need review.",
                    icon: 'heroicon-o-map-pin',
                    color: 'warning',
                ));
            }
        }

        CacheInvalidationService::onAttractionChange();
    }

    public function updated(Attraction $attraction): void
    {
        CacheInvalidationService::onAttractionChange();
    }

    public function deleted(Attraction $attraction): void
    {
        CacheInvalidationService::onAttractionChange();
    }
}
