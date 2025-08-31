<?php

namespace App\Services;

use App\Models\Business;
use App\Models\User;
use App\Models\UserPreference;
use App\Models\UserInteraction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LocationBasedFilter
{
    private const DEFAULT_RADIUS_KM = 25;
    private const MAX_RADIUS_KM = 100;

    public function getRecommendations(
        User $user,
        ?float $latitude = null,
        ?float $longitude = null,
        int $count = 20
    ): Collection {
        // If no location provided, try to get from user preferences
        if (!$latitude || !$longitude) {
            $preferences = UserPreference::where('user_id', $user->id)->first();
            if ($preferences && $preferences->preferred_location_lat && $preferences->preferred_location_lng) {
                $latitude = $preferences->preferred_location_lat;
                $longitude = $preferences->preferred_location_lng;
            }
        }

        // If still no location, try to infer from recent interactions
        if (!$latitude || !$longitude) {
            $recentLocation = $this->inferLocationFromInteractions($user);
            if ($recentLocation) {
                $latitude = $recentLocation['latitude'];
                $longitude = $recentLocation['longitude'];
            }
        }

        // If no location available, return empty collection
        if (!$latitude || !$longitude) {
            return collect();
        }

        return $this->getNearbyRecommendations($user, $latitude, $longitude, $count);
    }

    private function getNearbyRecommendations(
        User $user,
        float $latitude,
        float $longitude,
        int $count
    ): Collection {
        $preferences = UserPreference::where('user_id', $user->id)->first();
        $radius = $preferences?->preferred_radius_km ?? self::DEFAULT_RADIUS_KM;
        $radius = min($radius, self::MAX_RADIUS_KM); // Cap the radius

        // Get businesses within the radius
        $businesses = Business::select([
            'businesses.*',
            DB::raw("ST_Distance_Sphere(POINT(longitude, latitude), POINT(?, ?)) as distance")
        ])
        ->whereRaw("ST_Distance_Sphere(POINT(longitude, latitude), POINT(?, ?)) <= ?", [
            $longitude, $latitude, $longitude, $latitude, $radius * 1000
        ])
        ->where('is_active', true)
        ->with(['categories', 'images'])
        ->orderBy('distance')
        ->get();

        // Score businesses based on distance, popularity, and user preferences
        return $businesses->map(function ($business) use ($user, $latitude, $longitude, $radius) {
            $score = $this->calculateLocationScore($business, $user, $latitude, $longitude, $radius);
            
            return [
                'business_id' => $business->id,
                'business' => $business,
                'score' => $score,
                'distance_km' => round($business->distance / 1000, 2),
                'algorithm' => 'location_based'
            ];
        })
        ->sortByDesc('score')
        ->take($count)
        ->values();
    }

    private function calculateLocationScore(
        Business $business,
        User $user,
        float $userLat,
        float $userLng,
        float $maxRadius
    ): float {
        $score = 0;

        // Distance score (40% weight) - closer is better
        $distance = $business->distance / 1000; // Convert to km
        $distanceScore = max(0, 1 - ($distance / $maxRadius));
        $score += $distanceScore * 0.4;

        // Business quality score (30% weight)
        $qualityScore = ($business->overall_rating / 5.0) * 0.7 + 
                       (min($business->total_reviews / 50, 1)) * 0.3;
        $score += $qualityScore * 0.3;

        // User preference alignment (20% weight)
        $preferenceScore = $this->calculatePreferenceAlignment($business, $user);
        $score += $preferenceScore * 0.2;

        // Popularity in area score (10% weight)
        $popularityScore = $this->calculateLocalPopularity($business, $userLat, $userLng);
        $score += $popularityScore * 0.1;

        return round($score, 4);
    }

    private function calculatePreferenceAlignment(Business $business, User $user): float
    {
        $preferences = UserPreference::where('user_id', $user->id)->first();
        
        if (!$preferences) {
            return 0.5; // Neutral score if no preferences
        }

        $score = 0;
        $factors = 0;

        // Check category preferences
        if ($preferences->preferred_categories) {
            $businessCategories = $business->categories->pluck('id')->toArray();
            $intersection = array_intersect($businessCategories, $preferences->preferred_categories);
            $categoryScore = count($intersection) / count($preferences->preferred_categories);
            $score += $categoryScore;
            $factors++;
        }

        // Check price range preference
        if ($preferences->price_range_min !== null && $preferences->price_range_max !== null && $business->price_range) {
            $inPriceRange = $business->price_range >= $preferences->price_range_min && 
                           $business->price_range <= $preferences->price_range_max;
            $score += $inPriceRange ? 1 : 0;
            $factors++;
        }

        // Check minimum rating preference
        if ($preferences->minimum_rating && $business->overall_rating) {
            $meetsRating = $business->overall_rating >= $preferences->minimum_rating;
            $score += $meetsRating ? 1 : 0;
            $factors++;
        }

        return $factors > 0 ? $score / $factors : 0.5;
    }

    private function calculateLocalPopularity(Business $business, float $userLat, float $userLng): float
    {
        // Calculate popularity based on interactions from users in the same area
        $localInteractions = UserInteraction::where('business_id', $business->id)
            ->where('user_latitude', '!=', null)
            ->where('user_longitude', '!=', null)
            ->whereRaw(
                "ST_Distance_Sphere(POINT(user_longitude, user_latitude), POINT(?, ?)) <= ?",
                [$userLng, $userLat, 10000] // 10km radius for local popularity
            )
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        // Normalize the score (assume 20 local interactions = full score)
        return min($localInteractions / 20, 1);
    }

    private function inferLocationFromInteractions(User $user): ?array
    {
        // Get recent interactions with location data
        $recentInteraction = UserInteraction::where('user_id', $user->id)
            ->where('user_latitude', '!=', null)
            ->where('user_longitude', '!=', null)
            ->where('created_at', '>=', now()->subDays(7))
            ->orderByDesc('created_at')
            ->first();

        if ($recentInteraction) {
            return [
                'latitude' => $recentInteraction->user_latitude,
                'longitude' => $recentInteraction->user_longitude
            ];
        }

        // If no recent interactions with location, try to get from business locations
        $businessLocation = UserInteraction::where('user_id', $user->id)
            ->join('businesses', 'user_interactions.business_id', '=', 'businesses.id')
            ->where('businesses.latitude', '!=', null)
            ->where('businesses.longitude', '!=', null)
            ->where('user_interactions.created_at', '>=', now()->subDays(30))
            ->select('businesses.latitude', 'businesses.longitude')
            ->orderByDesc('user_interactions.created_at')
            ->first();

        if ($businessLocation) {
            return [
                'latitude' => $businessLocation->latitude,
                'longitude' => $businessLocation->longitude
            ];
        }

        return null;
    }

    public function getBusinessesByDistance(
        float $latitude,
        float $longitude,
        float $radiusKm = self::DEFAULT_RADIUS_KM,
        ?array $categories = null,
        int $limit = 50
    ): Collection {
        $query = Business::select([
            'businesses.*',
            DB::raw("ST_Distance_Sphere(POINT(longitude, latitude), POINT(?, ?)) as distance")
        ])
        ->whereRaw("ST_Distance_Sphere(POINT(longitude, latitude), POINT(?, ?)) <= ?", [
            $longitude, $latitude, $longitude, $latitude, $radiusKm * 1000
        ])
        ->where('is_active', true)
        ->with(['categories', 'images']);

        if ($categories) {
            $query->whereHas('categories', function ($q) use ($categories) {
                $q->whereIn('categories.id', $categories);
            });
        }

        return $query->orderBy('distance')
            ->take($limit)
            ->get()
            ->map(function ($business) {
                $business->distance_km = round($business->distance / 1000, 2);
                return $business;
            });
    }

    public function getTrendingInArea(
        float $latitude,
        float $longitude,
        float $radiusKm = self::DEFAULT_RADIUS_KM,
        int $days = 7,
        int $limit = 20
    ): Collection {
        // Get businesses with most interactions in the area recently
        $trendingBusinessIds = UserInteraction::where('user_latitude', '!=', null)
            ->where('user_longitude', '!=', null)
            ->whereRaw(
                "ST_Distance_Sphere(POINT(user_longitude, user_latitude), POINT(?, ?)) <= ?",
                [$longitude, $latitude, $radiusKm * 1000]
            )
            ->where('created_at', '>=', now()->subDays($days))
            ->select('business_id', DB::raw('COUNT(*) as interaction_count'))
            ->groupBy('business_id')
            ->orderByDesc('interaction_count')
            ->take($limit)
            ->pluck('business_id');

        return Business::whereIn('id', $trendingBusinessIds)
            ->where('is_active', true)
            ->with(['categories', 'images'])
            ->get();
    }
}
