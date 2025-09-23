<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Business;
use App\Models\BusinessSimilarity;

class CleanWrongSimilaritiesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'business:clean-wrong-similarities {--dry-run : Show what would be deleted without actually deleting}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up wrong business similarities (e.g., restaurant similar to clothing store)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting wrong similarities cleanup...');

        $wrongSimilarities = collect();
        
        // Find similarities between businesses of completely different categories
        $similarities = BusinessSimilarity::with(['businessA.category', 'businessB.category'])->get();
        
        $this->info("Found {$similarities->count()} total similarities to check");

        foreach ($similarities as $similarity) {
            $businessA = $similarity->businessA;
            $businessB = $similarity->businessB;
            
            if (!$businessA || !$businessB) {
                $wrongSimilarities->push($similarity);
                continue;
            }

            // Check if businesses are from incompatible categories
            if ($this->areIncompatibleCategories($businessA, $businessB)) {
                $wrongSimilarities->push($similarity);
            }
            
            // Check if similarity score is unreasonably high for different categories
            if ($businessA->category_id !== $businessB->category_id && $similarity->similarity_score > 0.7) {
                $wrongSimilarities->push($similarity);
            }
        }

        $this->info("Found {$wrongSimilarities->count()} wrong similarities");

        if ($wrongSimilarities->count() === 0) {
            $this->info('No wrong similarities found!');
            return;
        }

        // Show wrong similarities
        foreach ($wrongSimilarities->take(10) as $sim) {
            $categoryA = $sim->businessA->category->name ?? 'Unknown';
            $categoryB = $sim->businessB->category->name ?? 'Unknown';
            $this->line("Wrong: {$sim->businessA->name} ({$categoryA}) <-> {$sim->businessB->name} ({$categoryB}) - Score: {$sim->similarity_score}");
        }

        if ($wrongSimilarities->count() > 10) {
            $this->line("... and " . ($wrongSimilarities->count() - 10) . " more");
        }

        if ($this->option('dry-run')) {
            $this->warn('DRY RUN: Would delete ' . $wrongSimilarities->count() . ' wrong similarities');
            return;
        }

        if ($this->confirm('Delete these wrong similarities?')) {
            $wrongSimilarities->each(function($similarity) {
                $similarity->delete();
            });
            
            $this->info("Deleted {$wrongSimilarities->count()} wrong similarities");
            $this->info('Wrong similarities cleanup completed!');
        } else {
            $this->info('Cleanup cancelled');
        }
    }

    /**
     * Check if two businesses are from incompatible categories
     */
    private function areIncompatibleCategories(Business $businessA, Business $businessB): bool
    {
        // Define incompatible category pairs (add more as needed)
        $incompatiblePairs = [
            'restaurant' => ['clothing', 'electronics', 'pharmacy', 'automotive'],
            'clothing' => ['restaurant', 'pharmacy', 'automotive', 'food'],
            'electronics' => ['restaurant', 'clothing', 'food', 'beauty'],
            'pharmacy' => ['restaurant', 'clothing', 'electronics'],
            'automotive' => ['restaurant', 'clothing', 'beauty', 'food'],
            'food' => ['clothing', 'electronics', 'automotive'],
            'beauty' => ['electronics', 'automotive']
        ];

        $categoryA = strtolower($businessA->category->name ?? '');
        $categoryB = strtolower($businessB->category->name ?? '');

        // Check if categories are in incompatible pairs
        if (isset($incompatiblePairs[$categoryA]) && in_array($categoryB, $incompatiblePairs[$categoryA])) {
            return true;
        }

        if (isset($incompatiblePairs[$categoryB]) && in_array($categoryA, $incompatiblePairs[$categoryB])) {
            return true;
        }

        return false;
    }
}