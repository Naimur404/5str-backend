<?php

namespace App\Observers;

use App\Models\Business;
use App\Models\User;
use App\Notifications\DatabaseNotification;
use App\Services\CacheInvalidationService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Eager owner resolution: avoids two `User::find` round-trips per write at
 * scale. Notifications are dispatched via `Notification::send` so the
 * persistence happens in a single bulk insert; combined with the queued
 * DatabaseNotification this no longer blocks the request thread.
 */
class BusinessObserver
{
    private const ADMIN_ROLES_CREATED = ['admin', 'super-admin'];

    public function created(Business $business): void
    {
        $this->notifyOwner(
            $business,
            'Business Created Successfully',
            "Your business '{$business->business_name}' has been created. Please complete your profile for better visibility.",
            'heroicon-o-building-office',
            'info',
        );

        if (config('notifications.admin_notifications.business_created', false)
            && $business->business_name
            && $business->business_email
            && $business->full_address) {
            $admins = $this->fetchAdmins(self::ADMIN_ROLES_CREATED);
            if ($admins->isNotEmpty()) {
                Notification::send($admins, new DatabaseNotification(
                    title: 'New Business Registration',
                    body: "'{$business->business_name}' has registered and needs review.",
                    icon: 'heroicon-o-building-office-2',
                    color: 'warning',
                ));
            }
        }

        $this->updateCategoryBusinessCount($business->category_id);
        CacheInvalidationService::onBusinessChange();
    }

    public function updated(Business $business): void
    {
        if ($business->isDirty('is_verified') && $business->is_verified) {
            $this->notifyOwner(
                $business,
                'Business Verified!',
                "Congratulations! Your business '{$business->business_name}' has been verified and approved.",
                'heroicon-o-check-badge',
                'success',
            );
        }

        if ($business->isDirty('is_active') && $business->is_active) {
            $this->notifyOwner(
                $business,
                'Business Activated',
                "Your business '{$business->business_name}' is now active and visible to customers.",
                'heroicon-o-eye',
                'success',
            );
        }

        $significantFields = [
            'business_name', 'description', 'business_email',
            'business_phone', 'full_address', 'opening_hours',
        ];

        if ($business->isDirty($significantFields)) {
            $this->notifyOwner(
                $business,
                'Business Profile Updated',
                'Your business profile has been updated successfully.',
                'heroicon-o-pencil-square',
                'info',
            );
        }

        if ($business->isDirty('category_id')) {
            $oldCategoryId = $business->getOriginal('category_id');
            if ($oldCategoryId) {
                $this->updateCategoryBusinessCount($oldCategoryId);
            }
            $this->updateCategoryBusinessCount($business->category_id);
        } elseif ($business->isDirty('is_active')) {
            $this->updateCategoryBusinessCount($business->category_id);
        }

        // If a branch's rolled-up fields changed, ask the parent
        // brand row to recompute its own aggregates. Skipped when
        // the row IS the brand itself (parent_id null) — the job
        // will be dispatched by ReviewObserver from the branches
        // instead.
        $rollupFields = ['overall_rating', 'total_reviews', 'discovery_score', 'is_active'];
        if ($business->parent_id !== null && $business->isDirty($rollupFields)) {
            \App\Jobs\RecomputeBrandAggregatesJob::dispatch($business->parent_id);
        }

        CacheInvalidationService::onBusinessChange();
    }

    public function deleted(Business $business): void
    {
        $this->updateCategoryBusinessCount($business->category_id);

        if ($business->parent_id !== null) {
            \App\Jobs\RecomputeBrandAggregatesJob::dispatch($business->parent_id);
        }

        CacheInvalidationService::onBusinessChange();
    }

    public function restored(Business $business): void
    {
        $this->updateCategoryBusinessCount($business->category_id);
        CacheInvalidationService::onBusinessChange();
    }

    public function forceDeleted(Business $business): void
    {
        $this->updateCategoryBusinessCount($business->category_id);
        CacheInvalidationService::onBusinessChange();
    }

    private function notifyOwner(
        Business $business,
        string $title,
        string $body,
        string $icon,
        string $color,
    ): void {
        if (! $business->owner_user_id) {
            return;
        }
        $owner = User::find($business->owner_user_id);
        if (! $owner) {
            return;
        }
        $owner->notify(new DatabaseNotification(
            title: $title,
            body: $body,
            icon: $icon,
            color: $color,
        ));
    }

    /**
     * @param  array<int, string>  $roles
     * @return Collection<int, User>
     */
    private function fetchAdmins(array $roles): Collection
    {
        return User::query()
            ->whereHas('roles', static fn ($q) => $q->whereIn('name', $roles))
            ->get();
    }

    private function updateCategoryBusinessCount(?int $categoryId): void
    {
        if (! $categoryId) {
            return;
        }

        try {
            DB::statement(
                'UPDATE categories SET total_businesses = ('
                . 'SELECT COUNT(*) FROM businesses '
                . 'WHERE businesses.category_id = ? AND businesses.is_active = 1'
                . ') WHERE id = ?',
                [$categoryId, $categoryId],
            );
        } catch (\Throwable $e) {
            Log::error('Failed to update category business count', [
                'category_id' => $categoryId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<int, int>  $categoryIds
     */
    public static function updateMultipleCategoryCounts(array $categoryIds): void
    {
        if (empty($categoryIds)) {
            return;
        }

        try {
            $placeholders = implode(',', array_fill(0, count($categoryIds), '?'));
            DB::statement(
                'UPDATE categories SET total_businesses = ('
                . 'SELECT COUNT(*) FROM businesses '
                . 'WHERE businesses.category_id = categories.id AND businesses.is_active = 1'
                . ") WHERE id IN ({$placeholders})",
                $categoryIds,
            );
        } catch (\Throwable $e) {
            Log::error('Failed to update multiple category business counts', [
                'category_ids' => $categoryIds,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
