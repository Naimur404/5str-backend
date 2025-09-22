<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Observers\BusinessObserver;
use Illuminate\Console\Command;

class SyncCategoryBusinessCounts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'categories:sync-business-counts
                            {--force : Force sync even if observer is working}
                            {--category-id=* : Specific category IDs to sync}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync total_businesses count for all categories with actual business counts';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting category business count synchronization...');

        try {
            $specificCategoryIds = $this->option('category-id');
            
            if (!empty($specificCategoryIds)) {
                // Sync specific categories
                $categoryIds = $specificCategoryIds;
                $this->info('Syncing specific categories: ' . implode(', ', $categoryIds));
            } else {
                // Sync all categories
                $categoryIds = Category::pluck('id')->toArray();
                $this->info('Syncing all ' . count($categoryIds) . ' categories...');
            }

            // Use the observer's bulk update method
            BusinessObserver::updateMultipleCategoryCounts($categoryIds);

            // Display results
            $this->info('✅ Successfully updated business counts for ' . count($categoryIds) . ' categories');

            // Show some statistics
            if ($this->option('verbose')) {
                $categories = Category::whereIn('id', $categoryIds)
                    ->withCount(['businesses' => function($q) {
                        $q->where('is_active', true);
                    }])
                    ->get(['id', 'name', 'total_businesses']);

                $this->table(
                    ['Category ID', 'Category Name', 'Stored Count', 'Actual Count', 'Status'],
                    $categories->map(function($category) {
                        $status = $category->total_businesses == $category->businesses_count ? '✅ Match' : '⚠️ Updated';
                        return [
                            $category->id,
                            substr($category->name, 0, 30),
                            $category->total_businesses,
                            $category->businesses_count,
                            $status
                        ];
                    })->toArray()
                );
            }

            $this->newLine();
            $this->info('🎉 Category business count sync completed successfully!');
            $this->comment('Note: Business counts are now automatically maintained via BusinessObserver');

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Failed to sync category business counts');
            $this->error('Error: ' . $e->getMessage());
            
            if ($this->option('verbose')) {
                $this->error('Stack trace: ' . $e->getTraceAsString());
            }

            return Command::FAILURE;
        }
    }
}