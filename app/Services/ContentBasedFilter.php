<?php

namespace App\Services;

use App\Models\Business;
use App\Models\User;
use App\Models\UserPreference;
use App\Models\UserInteraction;
use Illuminate\Support\Collection;

class ContentBasedFilter
{
    public function getRecommendations(User $user, int $count = 20): Collection
    {
        $preferences = UserPreference::where('user_id', $user->id)->first();
        
        if (!$preferences) {
            return $this->getFallbackRecommendations($user, $count);
        }

        $query = Business::query()
            ->where('is_active', true)
            ->with(['categories', 'images']);

        // Filter by preferred categories
        if ($preferences->preferred_categories) {
            $query->whereHas('categories', function ($q) use ($preferences) {
                $q->whereIn('categories.id', $preferences->preferred_categories);
            });
        }

        // Filter by price range
        if ($preferences->price_range_min !== null || $preferences->price_range_max !== null) {
            if ($preferences->price_range_min !== null) {
                $query->where('price_range', '>=', $preferences->price_range_min);
            }
            if ($preferences->price_range_max !== null) {
                $query->where('price_range', '<=', $preferences->price_range_max);
            }
        }

        // Filter by minimum rating
        if ($preferences->minimum_rating) {
            $query->where('overall_rating', '>=', $preferences->minimum_rating);
        }

        $businesses = $query->get();

        // Score businesses based on user preferences
        return $businesses->map(function ($business) use ($user, $preferences) {
            $score = $this->calculateContentScore($business, $user, $preferences);
            return [
                'business_id' => $business->id,
                'business' => $business,
                'score' => $score,
                'algorithm' => 'content_based'
            ];
        })
        ->sortByDesc('score')
        ->take($count)
        ->values();
    }

    private function calculateContentScore(Business $business, User $user, UserPreference $preferences): float
    {
        $score = 0;

        // Category match score (40% weight)
        if ($preferences->preferred_categories) {
            $businessCategories = $business->categories->pluck('id')->toArray();
            $preferredCategories = $preferences->preferred_categories;
            
            $intersection = array_intersect($businessCategories, $preferredCategories);
            $categoryScore = count($intersection) / count($preferredCategories);
            $score += $categoryScore * 0.4;
        }

        // Rating score (25% weight)
        $ratingScore = $business->overall_rating / 5.0;
        $score += $ratingScore * 0.25;

        // Price preference score (20% weight)
        if ($preferences->price_range_min !== null && $preferences->price_range_max !== null) {
            $priceRange = $preferences->price_range_max - $preferences->price_range_min;
            if ($priceRange > 0 && $business->price_range) {
                $priceDiff = abs($business->price_range - (($preferences->price_range_min + $preferences->price_range_max) / 2));
                $priceScore = max(0, 1 - ($priceDiff / $priceRange));
                $score += $priceScore * 0.2;
            }
        }

        // Popularity score (15% weight)
        $popularityScore = min($business->total_reviews / 100, 1); // Cap at 100 reviews for full score
        $score += $popularityScore * 0.15;

        return round($score, 4);
    }

    private function getFallbackRecommendations(User $user, int $count): Collection
    {
        // For users without preferences, recommend based on their past interactions
        $interactedCategories = UserInteraction::where('user_id', $user->id)
            ->join('businesses', 'user_interactions.business_id', '=', 'businesses.id')
            ->join('business_categories', 'businesses.id', '=', 'business_categories.business_id')
            ->pluck('business_categories.category_id')
            ->unique()
            ->toArray();

        if (empty($interactedCategories)) {
            // No interaction history - recommend popular businesses
            return $this->getPopularBusinesses($count);
        }

        $businesses = Business::where('status', 'active')
            ->whereHas('categories', function ($q) use ($interactedCategories) {
                $q->whereIn('categories.id', $interactedCategories);
            })
            ->orderByDesc('overall_rating')
            ->orderByDesc('total_reviews')
            ->take($count)
            ->get();

        return $businesses->map(function ($business, $index) use ($count) {
            return [
                'business_id' => $business->id,
                'business' => $business,
                'score' => ($count - $index) / $count, // Decreasing score based on order
                'algorithm' => 'content_based_fallback'
            ];
        });
    }

    private function getPopularBusinesses(int $count): Collection
    {
        $businesses = Business::where('is_active', true)
            ->where('overall_rating', '>=', 4.0)
            ->orderByDesc('total_reviews')
            ->orderByDesc('overall_rating')
            ->take($count)
            ->get();

        return $businesses->map(function ($business, $index) use ($count) {
            return [
                'business_id' => $business->id,
                'business' => $business,
                'score' => ($count - $index) / $count,
                'algorithm' => 'popular_fallback'
            ];
        });
    }
}
