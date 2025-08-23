<?php

namespace App\Observers;

use App\Models\Review;
use App\Models\User;
use Filament\Notifications\Notification;

class ReviewObserver
{
    /**
     * Handle the Review "created" event.
     */
    public function created(Review $review): void
    {
        // Only notify admins if enabled in config and review needs approval
        if (config('notifications.admin_notifications.review_submitted', true)) {
            $admins = User::whereHas('roles', function($query) {
                $query->whereIn('name', ['admin', 'super-admin', 'moderator']);
            })->get();

            $businessName = $review->reviewable ? ($review->reviewable->business_name ?? 'a business') : 'a business';

            foreach ($admins as $admin) {
                Notification::make()
                    ->title('Review Pending Approval')
                    ->body("A new review for {$businessName} needs your approval.")
                    ->icon('heroicon-o-star')
                    ->color('warning')
                    ->sendToDatabase($admin);
            }
        }
    }

    /**
     * Handle the Review "updated" event.
     */
    public function updated(Review $review): void
    {
        // Check if review status changed to approved
        if ($review->isDirty('status') && $review->status === 'approved') {
            $reviewer = $review->user;
            if ($reviewer) {
                Notification::make()
                    ->title('Review Approved!')
                    ->body("Your review has been approved and is now visible to other users. Thank you for your feedback!")
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->sendToDatabase($reviewer);
            }
        }

        // Check if review status changed to rejected
        if ($review->isDirty('status') && $review->status === 'rejected') {
            $reviewer = $review->user;
            if ($reviewer) {
                Notification::make()
                    ->title('Review Needs Attention')
                    ->body("Your review requires some modifications before it can be published. Please check the review guidelines.")
                    ->icon('heroicon-o-exclamation-triangle')
                    ->color('warning')
                    ->sendToDatabase($reviewer);
            }
        }
    }

    /**
     * Handle the Review "deleted" event.
     */
    public function deleted(Review $review): void
    {
        // Notify user if their review is deleted
        $reviewer = $review->user;
        if ($reviewer) {
            Notification::make()
                ->title('Review Removed')
                ->body("Your review has been removed. If you have questions, please contact support.")
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->sendToDatabase($reviewer);
        }
    }

    /**
     * Handle the Review "restored" event.
     */
    public function restored(Review $review): void
    {
        //
    }

    /**
     * Handle the Review "force deleted" event.
     */
    public function forceDeleted(Review $review): void
    {
        //
    }
}
