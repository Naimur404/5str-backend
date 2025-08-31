<?php

namespace App\Jobs;

use App\Models\Business;
use App\Models\BusinessSimilarity;
use App\Models\UserInteraction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CalculateBusinessSimilarityJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $businessId;
    
    public $timeout = 300; // 5 minutes timeout
    public $tries = 2;

    public function __construct($businessId)
    {
        $this->businessId = $businessId;
        $this->onQueue('low');
    }

    public function handle(): void
    {
        try {
            Log::info('Calculating business similarity', ['business_id' => $this->businessId]);

            $business = Business::find($this->businessId);
            if (!$business) {
                Log::warning('Business not found for similarity calculation', ['business_id' => $this->businessId]);
                return;
            }

            // Calculate similarity with other businesses
            $similarities = $this->calculateSimilarities($business);
            
            // Update similarity scores
            $this->updateSimilarityScores($similarities);
            
            Log::info('Business similarity calculation completed', [
                'business_id' => $this->businessId,
                'similarities_count' => count($similarities)
            ]);

        } catch (\Exception $e) {
            Log::error('Business similarity calculation failed', [
                'business_id' => $this->businessId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    private function calculateSimilarities(Business $business): array
    {
        $similarities = [];
        
        // Get businesses in same categories
        $relatedBusinesses = Business::whereHas('categories', function ($query) use ($business) {
            $query->whereIn('categories.id', $business->categories->pluck('id'));
        })
        ->where('id', '!=', $business->id)
        ->where('status', 'approved')
        ->limit(100) // Limit for performance
        ->get();

        foreach ($relatedBusinesses as $relatedBusiness) {
            $score = $this->calculateSimilarityScore($business, $relatedBusiness);
            
            if ($score > 0.1) { // Only store meaningful similarities
                $similarities[] = [
                    'business_a_id' => $business->id,
                    'business_b_id' => $relatedBusiness->id,
                    'similarity_score' => $score,
                    'created_at' => now(),
                    'updated_at' => now()
                ];
            }
        }

        return $similarities;
    }

    private function calculateSimilarityScore(Business $businessA, Business $businessB): float
    {
        $score = 0.0;
        
        // Category similarity (40% weight)
        $categoryScore = $this->calculateCategorySimilarity($businessA, $businessB);
        $score += $categoryScore * 0.4;
        
        // Location similarity (30% weight)
        $locationScore = $this->calculateLocationSimilarity($businessA, $businessB);
        $score += $locationScore * 0.3;
        
        // User behavior similarity (30% weight)
        $behaviorScore = $this->calculateBehaviorSimilarity($businessA, $businessB);
        $score += $behaviorScore * 0.3;
        
        return min(1.0, $score); // Cap at 1.0
    }

    private function calculateCategorySimilarity(Business $businessA, Business $businessB): float
    {
        $categoriesA = $businessA->categories->pluck('id')->toArray();
        $categoriesB = $businessB->categories->pluck('id')->toArray();
        
        if (empty($categoriesA) || empty($categoriesB)) {
            return 0.0;
        }
        
        $intersection = array_intersect($categoriesA, $categoriesB);
        $union = array_unique(array_merge($categoriesA, $categoriesB));
        
        return count($intersection) / count($union); // Jaccard similarity
    }

    private function calculateLocationSimilarity(Business $businessA, Business $businessB): float
    {
        if (!$businessA->latitude || !$businessA->longitude || 
            !$businessB->latitude || !$businessB->longitude) {
            return 0.0;
        }
        
        $distance = $this->calculateDistance(
            $businessA->latitude, $businessA->longitude,
            $businessB->latitude, $businessB->longitude
        );
        
        // Businesses within 5km get high similarity, decreasing to 0 at 50km
        if ($distance <= 5) return 1.0;
        if ($distance >= 50) return 0.0;
        
        return 1.0 - (($distance - 5) / 45);
    }

    private function calculateBehaviorSimilarity(Business $businessA, Business $businessB): float
    {
        // Get users who interacted with both businesses
        $usersA = UserInteraction::where('business_id', $businessA->id)
            ->distinct('user_id')
            ->pluck('user_id')
            ->toArray();
            
        $usersB = UserInteraction::where('business_id', $businessB->id)
            ->distinct('user_id')
            ->pluck('user_id')
            ->toArray();
        
        if (empty($usersA) || empty($usersB)) {
            return 0.0;
        }
        
        $commonUsers = array_intersect($usersA, $usersB);
        $totalUsers = array_unique(array_merge($usersA, $usersB));
        
        return count($commonUsers) / count($totalUsers);
    }

    private function calculateDistance($lat1, $lon1, $lat2, $lon2): float
    {
        $earthRadius = 6371; // Earth's radius in kilometers
        
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        
        $a = sin($dLat/2) * sin($dLat/2) + 
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * 
             sin($dLon/2) * sin($dLon/2);
             
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        
        return $earthRadius * $c;
    }

    private function updateSimilarityScores(array $similarities): void
    {
        if (empty($similarities)) {
            return;
        }
        
        // Delete existing similarities for this business
        BusinessSimilarity::where('business_a_id', $this->businessId)
            ->orWhere('business_b_id', $this->businessId)
            ->delete();
        
        // Insert new similarities in chunks for better performance
        $chunks = array_chunk($similarities, 100);
        foreach ($chunks as $chunk) {
            BusinessSimilarity::insert($chunk);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Business similarity job failed', [
            'business_id' => $this->businessId,
            'error' => $exception->getMessage()
        ]);
    }
}
