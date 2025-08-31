<?php

namespace App\Services;

use App\Models\Business;
use App\Models\User;
use App\Models\UserInteraction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CollaborativeFilter
{
    private const MIN_INTERACTIONS_FOR_SIMILARITY = 3;
    private const SIMILARITY_THRESHOLD = 0.1;

    public function getRecommendations(User $user, int $count = 20): Collection
    {
        // Find similar users based on interaction patterns
        $similarUsers = $this->findSimilarUsers($user);

        if ($similarUsers->isEmpty()) {
            return $this->getFallbackRecommendations($user, $count);
        }

        // Get businesses that similar users liked but current user hasn't interacted with
        $recommendations = $this->getBusinessesFromSimilarUsers($user, $similarUsers);

        return $recommendations
            ->sortByDesc('score')
            ->take($count)
            ->values();
    }

    private function findSimilarUsers(User $user): Collection
    {
        // Get user's interaction history
        $userInteractions = UserInteraction::where('user_id', $user->id)
            ->where('created_at', '>=', now()->subDays(90)) // Recent interactions only
            ->get()
            ->groupBy('business_id')
            ->map(function ($interactions) {
                return $interactions->sum('weight'); // Sum up interaction weights per business
            });

        if ($userInteractions->count() < self::MIN_INTERACTIONS_FOR_SIMILARITY) {
            return collect();
        }

        // Find users who interacted with the same businesses
        $commonBusinessIds = $userInteractions->keys();
        
        $otherUsers = UserInteraction::whereIn('business_id', $commonBusinessIds)
            ->where('user_id', '!=', $user->id)
            ->where('created_at', '>=', now()->subDays(90))
            ->select('user_id')
            ->distinct()
            ->pluck('user_id');

        // Calculate similarity scores with other users
        $similarities = collect();

        foreach ($otherUsers as $otherUserId) {
            $otherUserInteractions = UserInteraction::where('user_id', $otherUserId)
                ->whereIn('business_id', $commonBusinessIds)
                ->where('created_at', '>=', now()->subDays(90))
                ->get()
                ->groupBy('business_id')
                ->map(function ($interactions) {
                    return $interactions->sum('weight');
                });

            $similarity = $this->calculateCosineSimilarity($userInteractions, $otherUserInteractions);

            if ($similarity >= self::SIMILARITY_THRESHOLD) {
                $similarities->put($otherUserId, $similarity);
            }
        }

        return $similarities->sortDesc()->take(10); // Top 10 similar users
    }

    private function calculateCosineSimilarity(Collection $vectorA, Collection $vectorB): float
    {
        $commonKeys = $vectorA->keys()->intersect($vectorB->keys());
        
        if ($commonKeys->isEmpty()) {
            return 0;
        }

        $dotProduct = 0;
        $magnitudeA = 0;
        $magnitudeB = 0;

        foreach ($commonKeys as $key) {
            $valueA = $vectorA->get($key, 0);
            $valueB = $vectorB->get($key, 0);
            
            $dotProduct += $valueA * $valueB;
            $magnitudeA += $valueA * $valueA;
            $magnitudeB += $valueB * $valueB;
        }

        $magnitudeA = sqrt($magnitudeA);
        $magnitudeB = sqrt($magnitudeB);

        if ($magnitudeA == 0 || $magnitudeB == 0) {
            return 0;
        }

        return $dotProduct / ($magnitudeA * $magnitudeB);
    }

    private function getBusinessesFromSimilarUsers(User $user, Collection $similarUsers): Collection
    {
        // Get businesses that current user has already interacted with
        $userBusinessIds = UserInteraction::where('user_id', $user->id)
            ->pluck('business_id')
            ->unique()
            ->toArray();

        // Get highly weighted interactions from similar users
        $businessScores = collect();

        foreach ($similarUsers as $similarUserId => $similarity) {
            $similarUserInteractions = UserInteraction::where('user_id', $similarUserId)
                ->whereNotIn('business_id', $userBusinessIds) // Exclude businesses user already knows
                ->where('weight', '>=', 2.0) // Only positive interactions
                ->where('created_at', '>=', now()->subDays(90))
                ->get();

            foreach ($similarUserInteractions as $interaction) {
                $businessId = $interaction->business_id;
                $score = $interaction->weight * $similarity;

                if ($businessScores->has($businessId)) {
                    $businessScores[$businessId] += $score;
                } else {
                    $businessScores[$businessId] = $score;
                }
            }
        }

        // Load business details and create recommendation objects
        $businessIds = $businessScores->keys();
        $businesses = Business::whereIn('id', $businessIds)
            ->where('is_active', true)
            ->with(['categories', 'images'])
            ->get()
            ->keyBy('id');

        return $businessScores->map(function ($score, $businessId) use ($businesses) {
            $business = $businesses->get($businessId);
            
            if (!$business) {
                return null;
            }

            return [
                'business_id' => $businessId,
                'business' => $business,
                'score' => round($score, 4),
                'algorithm' => 'collaborative_filtering'
            ];
        })->filter(); // Remove null values
    }

    private function getFallbackRecommendations(User $user, int $count): Collection
    {
        // For users without enough similarity data, use popularity-based recommendations
        $userBusinessIds = UserInteraction::where('user_id', $user->id)
            ->pluck('business_id')
            ->unique()
            ->toArray();

        // Get trending businesses that the user hasn't interacted with
        $trendingBusinesses = Business::whereNotIn('id', $userBusinessIds)
            ->where('is_active', true)
            ->where('created_at', '>=', now()->subDays(30)) // Recently added businesses
            ->orWhere(function ($query) use ($userBusinessIds) {
                $query->whereNotIn('id', $userBusinessIds)
                    ->where('is_active', true)
                    ->orderByDesc('total_reviews')
                    ->orderByDesc('overall_rating');
            })
            ->with(['categories', 'images'])
            ->take($count)
            ->get();

        return $trendingBusinesses->map(function ($business, $index) use ($count) {
            return [
                'business_id' => $business->id,
                'business' => $business,
                'score' => ($count - $index) / $count,
                'algorithm' => 'collaborative_fallback'
            ];
        });
    }

    public function calculateUserSimilarity(User $userA, User $userB): float
    {
        $interactionsA = UserInteraction::where('user_id', $userA->id)
            ->where('created_at', '>=', now()->subDays(90))
            ->get()
            ->groupBy('business_id')
            ->map(function ($interactions) {
                return $interactions->sum('weight');
            });

        $interactionsB = UserInteraction::where('user_id', $userB->id)
            ->where('created_at', '>=', now()->subDays(90))
            ->get()
            ->groupBy('business_id')
            ->map(function ($interactions) {
                return $interactions->sum('weight');
            });

        return $this->calculateCosineSimilarity($interactionsA, $interactionsB);
    }
}
