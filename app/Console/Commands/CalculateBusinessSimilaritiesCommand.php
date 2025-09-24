<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Business;
use App\Models\BusinessSimilarity;
use App\Jobs\CalculateBusinessSimilarityJob;

class CalculateBusinessSimilaritiesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'business:calculate-similarities {--force : Force recalculation of all similarities}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calculate business similarities for recommendation system';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting business similarity calculation...');

        $businesses = Business::with(['category', 'offerings'])->get();
        $totalBusinesses = $businesses->count();

        if ($totalBusinesses < 2) {
            $this->warn('Need at least 2 businesses to calculate similarities.');
            return;
        }

        $this->info("Found {$totalBusinesses} businesses");

        // Clear existing similarities if force flag is used
        if ($this->option('force')) {
            $this->info('Clearing existing similarities...');
            BusinessSimilarity::truncate();
        }

        $processed = 0;
        $created = 0;

        $bar = $this->output->createProgressBar($totalBusinesses * ($totalBusinesses - 1) / 2);
        $bar->start();

        foreach ($businesses as $business1) {
            foreach ($businesses as $business2) {
                if ($business1->id >= $business2->id) {
                    continue; // Avoid duplicates and self-comparison
                }

                // Check if similarity already exists
                $existingSimilarity = BusinessSimilarity::where([
                    'business_a_id' => min($business1->id, $business2->id),
                    'business_b_id' => max($business1->id, $business2->id),
                ])->first();

                if (!$existingSimilarity || $this->option('force')) {
                    if ($existingSimilarity) {
                        $existingSimilarity->delete();
                    }

                    $similarity = $this->calculateSimilarity($business1, $business2);
                    
                    // STRICT THRESHOLD: Only store meaningful similarities
                    if ($similarity >= 0.3) {
                        BusinessSimilarity::create([
                            'business_a_id' => min($business1->id, $business2->id),
                            'business_b_id' => max($business1->id, $business2->id),
                            'similarity_type' => 'enhanced_relevance',
                            'similarity_score' => $similarity,
                        ]);

                        $created++;
                        
                        // Debug output for verification
                        if ($this->output->isVerbose()) {
                            $cat1 = $business1->category->name ?? 'Unknown';
                            $cat2 = $business2->category->name ?? 'Unknown';
                            $this->line("✓ Stored: {$business1->name} ({$cat1}) <-> {$business2->name} ({$cat2}) = {$similarity}");
                        }
                    } else if ($this->output->isVeryVerbose() && $similarity > 0) {
                        $cat1 = $business1->category->name ?? 'Unknown';
                        $cat2 = $business2->category->name ?? 'Unknown';
                        $this->line("✗ Skipped low similarity: {$business1->name} ({$cat1}) <-> {$business2->name} ({$cat2}) = {$similarity}");
                    }
                }

                $processed++;
                $bar->advance();
            }
        }

        $bar->finish();
        $this->newLine();

        $this->info("Processed {$processed} business pairs");
        $this->info("Created {$created} similarity records");
        $this->info('Business similarity calculation completed!');
    }

    /**
     * Calculate similarity between two businesses with strict relevance filtering
     */
    private function calculateSimilarity(Business $business1, Business $business2): float
    {
        // STRICT FILTER: Skip businesses that are completely incompatible
        if ($this->areIncompatibleBusinesses($business1, $business2)) {
            return 0.0;
        }

        $factors = [
            'category_match' => $this->calculateCategoryMatch($business1, $business2),
            'location_proximity' => $this->calculateLocationProximity($business1, $business2),
            'offering_overlap' => $this->calculateOfferingOverlap($business1, $business2),
            'rating_similarity' => $this->calculateRatingSimilarity($business1, $business2)
        ];

        return $this->calculateSimilarityScore($factors);
    }

    /**
     * Check if two businesses are completely incompatible
     */
    private function areIncompatibleBusinesses(Business $business1, Business $business2): bool
    {
        $category1 = strtolower($business1->category->name ?? '');
        $category2 = strtolower($business2->category->name ?? '');

        // Define incompatible category combinations
        $incompatiblePairs = [
            'restaurant' => ['clothing', 'electronics', 'pharmacy', 'automotive', 'real estate'],
            'clothing' => ['restaurant', 'pharmacy', 'automotive', 'food', 'medical'],
            'electronics' => ['restaurant', 'clothing', 'food', 'beauty', 'pharmacy'],
            'pharmacy' => ['restaurant', 'clothing', 'electronics', 'automotive'],
            'automotive' => ['restaurant', 'clothing', 'beauty', 'food', 'pharmacy'],
            'real estate' => ['restaurant', 'clothing', 'electronics', 'beauty', 'food'],
            'medical' => ['clothing', 'electronics', 'automotive', 'beauty'],
            'education' => ['restaurant', 'clothing', 'automotive', 'beauty']
        ];

        if (isset($incompatiblePairs[$category1])) {
            return in_array($category2, $incompatiblePairs[$category1]);
        }

        if (isset($incompatiblePairs[$category2])) {
            return in_array($category1, $incompatiblePairs[$category2]);
        }

        return false;
    }

    /**
     * Calculate category match with compatibility rules
     */
    private function calculateCategoryMatch(Business $business1, Business $business2): float
    {
        // Direct category match
        if ($business1->category_id === $business2->category_id) {
            return 1.0;
        }

        // Check for compatible categories
        if ($this->areCompatibleCategories($business1, $business2)) {
            return 0.6;
        }

        return 0.0;
    }

    /**
     * Check if two businesses have compatible categories
     */
    private function areCompatibleCategories(Business $business1, Business $business2): bool
    {
        $compatiblePairs = [
            'restaurant' => ['food', 'cafe', 'fast food', 'dining'],
            'food' => ['restaurant', 'cafe', 'fast food', 'dining'],
            'beauty' => ['salon', 'spa', 'wellness'],
            'salon' => ['beauty', 'spa', 'wellness'],
            'clothing' => ['fashion', 'apparel', 'boutique'],
            'fashion' => ['clothing', 'apparel', 'boutique'],
            'electronics' => ['technology', 'mobile', 'computer'],
            'technology' => ['electronics', 'mobile', 'computer'],
            'health' => ['pharmacy', 'medical', 'healthcare'],
            'pharmacy' => ['health', 'medical', 'healthcare'],
        ];

        $category1 = strtolower($business1->category->name ?? '');
        $category2 = strtolower($business2->category->name ?? '');

        return isset($compatiblePairs[$category1]) && 
               in_array($category2, $compatiblePairs[$category1]);
    }

    /**
     * Calculate location proximity
     */
    private function calculateLocationProximity(Business $business1, Business $business2): float
    {
        // Same area gets high score
        if ($business1->area === $business2->area) {
            return 0.8;
        }

        // Same district gets moderate score
        if ($business1->district === $business2->district) {
            return 0.4;
        }

        return 0.0;
    }

    /**
     * Calculate offering overlap
     */
    private function calculateOfferingOverlap(Business $business1, Business $business2): float
    {
        $offerings1 = $business1->offerings->pluck('id')->toArray();
        $offerings2 = $business2->offerings->pluck('id')->toArray();
        
        if (empty($offerings1) || empty($offerings2)) {
            return 0.0;
        }

        $intersection = count(array_intersect($offerings1, $offerings2));
        $union = count(array_unique(array_merge($offerings1, $offerings2)));
        
        return $union > 0 ? ($intersection / $union) : 0.0;
    }

    /**
     * Calculate rating similarity
     */
    private function calculateRatingSimilarity(Business $business1, Business $business2): float
    {
        $rating1 = $business1->overall_rating ?? 0;
        $rating2 = $business2->overall_rating ?? 0;
        
        if ($rating1 == 0 || $rating2 == 0) {
            return 0.0;
        }

        $ratingDiff = abs($rating1 - $rating2);
        return max(0, 1 - ($ratingDiff / 5));
    }

    /**
     * Calculate final similarity score with proper weighting
     */
    private function calculateSimilarityScore(array $factors): float
    {
        // STRICT RULE: If no category match, similarity is 0
        if (($factors['category_match'] ?? 0) == 0) {
            return 0.0;
        }

        $weights = [
            'category_match' => 0.6,       // Category is most important
            'location_proximity' => 0.2,   // Location is secondary
            'offering_overlap' => 0.15,    // Offerings matter
            'rating_similarity' => 0.05    // Rating is least important
        ];

        $score = 0;
        foreach ($weights as $factor => $weight) {
            $score += ($factors[$factor] ?? 0) * $weight;
        }

        return round($score, 4);
    }
}
