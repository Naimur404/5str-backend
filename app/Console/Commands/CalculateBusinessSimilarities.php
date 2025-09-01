<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Business;
use App\Models\BusinessSimilarity;
use App\Models\UserInteraction;

class CalculateBusinessSimilarities extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'similarities:calculate 
                            {--force : Force recalculation of existing similarities}
                            {--business= : Calculate similarities for specific business ID}
                            {--chunk=10 : Number of businesses to process at once}';

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
        $this->info('Starting business similarity calculations...');

        $specificBusinessId = $this->option('business');
        $force = $this->option('force');
        $chunkSize = (int) $this->option('chunk');

        if ($specificBusinessId) {
            $business = Business::find($specificBusinessId);
            if (!$business) {
                $this->error("Business with ID {$specificBusinessId} not found.");
                return 1;
            }
            $this->calculateSimilaritiesForBusiness($business, $force);
        } else {
            $this->calculateAllSimilarities($force, $chunkSize);
        }

        $this->info('Business similarity calculations completed!');
        return 0;
    }

    private function calculateAllSimilarities(bool $force, int $chunkSize): void
    {
        $businessCount = Business::where('is_active', true)->count();
        $this->info("Processing {$businessCount} active businesses...");

        $progressBar = $this->output->createProgressBar($businessCount);
        $progressBar->start();

        Business::where('is_active', true)
            ->chunk($chunkSize, function ($businesses) use ($force, $progressBar) {
                foreach ($businesses as $business) {
                    $this->calculateSimilaritiesForBusiness($business, $force);
                    $progressBar->advance();
                }
            });

        $progressBar->finish();
        $this->newLine();
    }

    private function calculateSimilaritiesForBusiness(Business $business, bool $force): void
    {
        // Get all other active businesses
        $otherBusinesses = Business::where('id', '!=', $business->id)
            ->where('is_active', true)
            ->with(['category', 'subcategory'])
            ->get();

        foreach ($otherBusinesses as $otherBusiness) {
            // Skip if similarity already exists and not forcing
            if (!$force) {
                $exists = BusinessSimilarity::where(function ($query) use ($business, $otherBusiness) {
                    $query->where('business_a_id', min($business->id, $otherBusiness->id))
                          ->where('business_b_id', max($business->id, $otherBusiness->id));
                })->exists();

                if ($exists) {
                    continue;
                }
            }

            // Calculate similarity factors
            $factors = $this->calculateSimilarityFactors($business, $otherBusiness);
            
            // Store the similarity if score is above threshold
            $score = $this->calculateSimilarityScore($factors);
            if ($score >= 0.1) { // Minimum similarity threshold
                BusinessSimilarity::calculateAndStore($business->id, $otherBusiness->id, $factors);
            }
        }
    }

    private function calculateSimilarityFactors(Business $businessA, Business $businessB): array
    {
        return [
            'category_match' => $this->calculateCategoryMatch($businessA, $businessB),
            'location_proximity' => $this->calculateLocationProximity($businessA, $businessB),
            'review_sentiment' => $this->calculateReviewSentimentSimilarity($businessA, $businessB),
            'feature_overlap' => $this->calculateFeatureOverlap($businessA, $businessB),
            'user_overlap' => $this->calculateUserOverlap($businessA, $businessB)
        ];
    }

    private function calculateCategoryMatch(Business $businessA, Business $businessB): float
    {
        // Direct category match
        if ($businessA->category_id === $businessB->category_id) {
            // If subcategories also match, perfect score
            if ($businessA->subcategory_id === $businessB->subcategory_id) {
                return 1.0;
            }
            // Same category, different subcategory
            return 0.8;
        }

        // Different categories
        return 0.0;
    }

    private function calculateLocationProximity(Business $businessA, Business $businessB): float
    {
        if (!$businessA->latitude || !$businessA->longitude || 
            !$businessB->latitude || !$businessB->longitude) {
            return 0.0;
        }

        $distance = $this->calculateDistance(
            $businessA->latitude, $businessA->longitude,
            $businessB->latitude, $businessB->longitude
        );

        // Businesses within 1km get highest score
        if ($distance <= 1) return 1.0;
        // Within 5km get good score
        if ($distance <= 5) return 0.8;
        // Within 10km get moderate score
        if ($distance <= 10) return 0.5;
        // Within 25km get low score
        if ($distance <= 25) return 0.2;
        
        return 0.0;
    }

    private function calculateReviewSentimentSimilarity(Business $businessA, Business $businessB): float
    {
        // Simple rating-based similarity
        $ratingDiff = abs($businessA->overall_rating - $businessB->overall_rating);
        return max(0, 1 - ($ratingDiff / 5));
    }

    private function calculateFeatureOverlap(Business $businessA, Business $businessB): float
    {
        // Compare price ranges
        $priceRangeScore = 0.0;
        if ($businessA->price_range && $businessB->price_range) {
            $priceDiff = abs($businessA->price_range - $businessB->price_range);
            $priceRangeScore = max(0, 1 - ($priceDiff / 3)); // Assuming 1-4 price range
        }

        // Compare business features (placeholder for future enhancement)
        $featureScore = 0.5; // Default baseline

        return ($priceRangeScore + $featureScore) / 2;
    }

    private function calculateUserOverlap(Business $businessA, Business $businessB): float
    {
        $usersA = UserInteraction::where('business_id', $businessA->id)
            ->whereIn('interaction_type', ['favorite', 'review', 'phone_call', 'visit'])
            ->pluck('user_id')
            ->unique()
            ->toArray();

        $usersB = UserInteraction::where('business_id', $businessB->id)
            ->whereIn('interaction_type', ['favorite', 'review', 'phone_call', 'visit'])
            ->pluck('user_id')
            ->unique()
            ->toArray();

        if (empty($usersA) || empty($usersB)) {
            return 0.0;
        }

        $intersection = array_intersect($usersA, $usersB);
        $union = array_unique(array_merge($usersA, $usersB));

        return count($intersection) / count($union);
    }

    private function calculateSimilarityScore(array $factors): float
    {
        $weights = [
            'category_match' => 0.4,
            'location_proximity' => 0.25,
            'review_sentiment' => 0.15,
            'feature_overlap' => 0.1,
            'user_overlap' => 0.1
        ];

        $score = 0;
        foreach ($weights as $factor => $weight) {
            $score += ($factors[$factor] ?? 0) * $weight;
        }

        return round($score, 4);
    }

    private function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371; // Earth's radius in kilometers

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));

        return $earthRadius * $c;
    }
}
