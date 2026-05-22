<?php

namespace App\Observers;

use App\Models\Review;
use App\Models\User;
use App\Notifications\DatabaseNotification;
use App\Services\CacheInvalidationService;
use Illuminate\Support\Facades\Notification;

class ReviewObserver
{
    private const ADMIN_ROLES = ['admin', 'super-admin', 'moderator'];

    public function created(Review $review): void
    {
        if (config('notifications.admin_notifications.review_submitted', true)) {
            $admins = User::query()
                ->whereHas('roles', static fn ($q) => $q->whereIn('name', self::ADMIN_ROLES))
                ->get();

            if ($admins->isNotEmpty()) {
                $businessName = $review->reviewable->business_name ?? 'a business';

                Notification::send($admins, new DatabaseNotification(
                    title: 'Review Pending Approval',
                    body: "A new review for {$businessName} needs your approval.",
                    icon: 'heroicon-o-star',
                    color: 'warning',
                ));
            }
        }

        CacheInvalidationService::onReviewChange();
    }

    public function updated(Review $review): void
    {
        if ($review->isDirty('status')) {
            $reviewer = $review->user;

            if ($reviewer && $review->status === 'approved') {
                $reviewer->notify(new DatabaseNotification(
                    title: 'Review Approved!',
                    body: 'Your review has been approved and is now visible to other users. Thank you for your feedback!',
                    icon: 'heroicon-o-check-circle',
                    color: 'success',
                ));
            } elseif ($reviewer && $review->status === 'rejected') {
                $reviewer->notify(new DatabaseNotification(
                    title: 'Review Needs Attention',
                    body: 'Your review requires some modifications before it can be published. Please check the review guidelines.',
                    icon: 'heroicon-o-exclamation-triangle',
                    color: 'warning',
                ));
            }
        }

        CacheInvalidationService::onReviewChange();
    }

    public function deleted(Review $review): void
    {
        $reviewer = $review->user;
        if ($reviewer) {
            $reviewer->notify(new DatabaseNotification(
                title: 'Review Removed',
                body: 'Your review has been removed. If you have questions, please contact support.',
                icon: 'heroicon-o-trash',
                color: 'danger',
            ));
        }

        CacheInvalidationService::onReviewChange();
    }

    public function restored(Review $review): void
    {
    }

    public function forceDeleted(Review $review): void
    {
    }
}
