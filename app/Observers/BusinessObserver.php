<?php

namespace App\Observers;

use App\Models\Business;
use App\Models\User;
use App\Models\Category;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Filament\Notifications\Notification;

class BusinessObserver
{
    /**
     * Handle the Business "created" event.
     */
    public function created(Business $business): void
    {
        // Only send welcome notification to business owner, not admins
        if ($business->owner_user_id) {
            $owner = User::find($business->owner_user_id);
            if ($owner) {
                Notification::make()
                    ->title('Business Created Successfully')
                    ->body("Your business '{$business->business_name}' has been created. Please complete your profile for better visibility.")
                    ->icon('heroicon-o-building-office')
                    ->color('info')
                    ->sendToDatabase($owner);
            }
        }

        // Notify admins only for important business registrations (configurable)
        if (config('notifications.admin_notifications.business_created', false) && 
            $business->business_name && $business->business_email && $business->full_address) {
            
            $admins = User::whereHas('roles', function($query) {
                $query->whereIn('name', ['admin', 'super-admin']);
            })->get();

            foreach ($admins as $admin) {
                Notification::make()
                    ->title('New Business Registration')
                    ->body("'{$business->business_name}' has registered and needs review.")
                    ->icon('heroicon-o-building-office-2')
                    ->color('warning')
                    ->sendToDatabase($admin);
            }
        }

        // Update category business count when a new business is created
        $this->updateCategoryBusinessCount($business->category_id);
    }

    /**
     * Handle the Business "updated" event.
     */
    public function updated(Business $business): void
    {
        $owner = $business->owner_user_id ? User::find($business->owner_user_id) : null;
        
        // IMPORTANT: Notify admins only for verification status changes
        if ($business->isDirty('is_verified') && $business->is_verified) {
            // Notify business owner
            if ($owner) {
                Notification::make()
                    ->title('Business Verified!')
                    ->body("Congratulations! Your business '{$business->business_name}' has been verified and approved.")
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->sendToDatabase($owner);
            }
        }

        // Notify business owner for activation (not admins)
        if ($business->isDirty('is_active') && $business->is_active && $owner) {
            Notification::make()
                ->title('Business Activated')
                ->body("Your business '{$business->business_name}' is now active and visible to customers.")
                ->icon('heroicon-o-eye')
                ->color('success')
                ->sendToDatabase($owner);
        }

        // Notify business owner for profile updates (not admins)
        $significantFields = [
            'business_name', 'description', 'business_email', 
            'business_phone', 'full_address', 'opening_hours'
        ];
        
        if ($business->isDirty($significantFields) && $owner) {
            Notification::make()
                ->title('Business Profile Updated')
                ->body("Your business profile has been updated successfully.")
                ->icon('heroicon-o-pencil-square')
                ->color('info')
                ->sendToDatabase($owner);
        }

        // Update category business count when business category or active status changes
        if ($business->isDirty('category_id')) {
            $oldCategoryId = $business->getOriginal('category_id');
            if ($oldCategoryId) {
                $this->updateCategoryBusinessCount($oldCategoryId);
            }
            $this->updateCategoryBusinessCount($business->category_id);
        }

        if ($business->isDirty('is_active')) {
            $this->updateCategoryBusinessCount($business->category_id);
        }
    }

    /**
     * Handle the Business "deleted" event.
     */
    public function deleted(Business $business): void
    {
        // Update category business count when a business is deleted
        $this->updateCategoryBusinessCount($business->category_id);
    }

    /**
     * Handle the Business "restored" event.
     */
    public function restored(Business $business): void
    {
        // Update category business count when a business is restored
        $this->updateCategoryBusinessCount($business->category_id);
    }

    /**
     * Handle the Business "force deleted" event.
     */
    public function forceDeleted(Business $business): void
    {
        // Update category business count when a business is force deleted
        $this->updateCategoryBusinessCount($business->category_id);
    }

    /**
     * Update the total_businesses count for a category
     */
    private function updateCategoryBusinessCount($categoryId)
    {
        if (!$categoryId) {
            return;
        }

        try {
            // Use raw query for better performance
            DB::statement("
                UPDATE categories 
                SET total_businesses = (
                    SELECT COUNT(*) 
                    FROM businesses 
                    WHERE businesses.category_id = ? 
                    AND businesses.is_active = 1
                ) 
                WHERE id = ?
            ", [$categoryId, $categoryId]);

            // Optional: Log successful updates for monitoring
            Log::info("Category business count updated", [
                'category_id' => $categoryId,
                'timestamp' => now()
            ]);

        } catch (\Exception $e) {
            // Log the error but don't fail the main operation
            Log::error("Failed to update category business count", [
                'category_id' => $categoryId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Update business counts for multiple categories (bulk operation)
     */
    public static function updateMultipleCategoryCounts(array $categoryIds)
    {
        if (empty($categoryIds)) {
            return;
        }

        try {
            $placeholders = str_repeat('?,', count($categoryIds) - 1) . '?';
            
            DB::statement("
                UPDATE categories 
                SET total_businesses = (
                    SELECT COUNT(*) 
                    FROM businesses 
                    WHERE businesses.category_id = categories.id 
                    AND businesses.is_active = 1
                ) 
                WHERE id IN ($placeholders)
            ", $categoryIds);

            Log::info("Multiple category business counts updated", [
                'category_ids' => $categoryIds,
                'count' => count($categoryIds),
                'timestamp' => now()
            ]);

        } catch (\Exception $e) {
            Log::error("Failed to update multiple category business counts", [
                'category_ids' => $categoryIds,
                'error' => $e->getMessage()
            ]);
        }
    }
}
