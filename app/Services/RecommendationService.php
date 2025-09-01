<?php

namespace App\Services;

use App\Models\Business;
use App\Models\User;
use App\Models\UserPreference;
use App\Models\UserInteraction;
use App\Models\BusinessSimilarity;
use App\Models\Category;
use App\Services\AI\NeuralRecommendationEngine;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class RecommendationService
{
    private const DEFAULT_RECOMMENDATIONS_COUNT = 20;
    private const LOCATION_RADIUS_KM = 50;
    private const INTERACTION_DECAY_DAYS = 30;
    private const CACHE_TTL = 900; // 15 minutes

    public function __construct(
        private ContentBasedFilter $contentBasedFilter,
        private CollaborativeFilter $collaborativeFilter,
        private LocationBasedFilter $locationBasedFilter,
        private NeuralRecommendationEngine $neuralEngine
    ) {}

    public function getRecommendations(
        User $user,
        ?float $latitude = null,
        ?float $longitude = null,
        ?array $categories = null,
        int $count = self::DEFAULT_RECOMMENDATIONS_COUNT
    ): Collection {
        // A/B test personalization levels
        $personalizationLevel = app(ABTestingService::class)
            ->getVariantForUser('personalization_level', $user->id);
        
        // Generate cache key with personalization level
        $cacheKey = $this->generateCacheKey($user->id, $latitude, $longitude, $categories, $count, $personalizationLevel);
        
        $startTime = microtime(true);
        
        // Try to get from cache first
        $cached = Cache::get($cacheKey);
        if ($cached) {
            return collect($cached);
        }

        // Get recommendations based on personalization level
        $recommendations = match($personalizationLevel) {
            'none' => $this->getFastRecommendations($user, $latitude, $longitude, $categories, $count),
            'light' => $this->getLightPersonalizedRecommendations($user, $latitude, $longitude, $categories, $count),
            'full' => $this->getFullPersonalizedRecommendations($user, $latitude, $longitude, $categories, $count),
            default => $this->getFastRecommendations($user, $latitude, $longitude, $categories, $count)
        };
        
        // Cache the results
        Cache::put($cacheKey, $recommendations->toArray(), self::CACHE_TTL);
        
        // Track A/B testing metrics
        $responseTime = round((microtime(true) - $startTime) * 1000, 2);
        $this->trackPersonalizationMetrics($user->id, $personalizationLevel, $responseTime, count($recommendations));
        
        return $recommendations;
    }

    private function getFastRecommendations(
        User $user,
        ?float $latitude = null,
        ?float $longitude = null,
        ?array $categories = null,
        int $count = self::DEFAULT_RECOMMENDATIONS_COUNT
    ): Collection {
        // Use a simplified approach for better performance
        // Priority: Location-based (if coordinates provided) + Content-based + Simple scoring
        
        $businesses = Business::where('is_active', true)
        ->with(['categories:id,name', 'images' => function($query) {
            $query->where('is_primary', true)->select('id', 'business_id', 'image_url', 'image_type');
        }]);

        // Apply location filter if coordinates provided
        if ($latitude && $longitude) {
            $businesses = $businesses->selectRaw(
                "businesses.*, ST_Distance_Sphere(POINT(longitude, latitude), POINT(?, ?)) as distance",
                [$longitude, $latitude]
            )
            ->whereRaw(
                "ST_Distance_Sphere(POINT(longitude, latitude), POINT(?, ?)) <= ?",
                [$longitude, $latitude, self::LOCATION_RADIUS_KM * 1000]
            )
            ->orderBy('distance');
        } else {
            // If no location, prioritize by rating and discovery score
            $businesses = $businesses->select('businesses.*')
                ->orderByDesc('overall_rating')
                ->orderByDesc('discovery_score');
        }

        // Apply category filter
        if ($categories) {
            $businesses = $businesses->whereIn('category_id', $categories);
        }

        $results = $businesses->take($count * 2) // Get more for scoring
            ->get()
            ->map(function ($business) use ($user, $latitude, $longitude) {
                return [
                    'business_id' => $business->id,
                    'business' => $business,
                    'final_score' => $this->calculateSimpleScore($business, $user, $latitude, $longitude),
                    'contributing_algorithms' => ['fast']
                ];
            })
            ->sortByDesc('final_score')
            ->take($count)
            ->values();

        return $results;
    }

    private function calculateSimpleScore(Business $business, User $user, ?float $latitude = null, ?float $longitude = null): float
    {
        $score = 0.0;

        // Base score from rating and reviews
        $score += (float)$business->overall_rating * 0.3;
        $score += min($business->total_reviews / 50, 1) * 0.2; // Max 1 point for 50+ reviews

        // Discovery score (popularity)
        $score += (float)$business->discovery_score / 100 * 0.2;

        // Location bonus if coordinates provided
        if ($latitude && $longitude && isset($business->distance)) {
            $distanceKm = $business->distance / 1000;
            $locationScore = max(0, 1 - ($distanceKm / self::LOCATION_RADIUS_KM));
            $score += $locationScore * 0.3;
        }

        // Verification bonus
        if ($business->is_verified) {
            $score += 0.1;
        }

        // Featured bonus
        if ($business->is_featured) {
            $score += 0.15;
        }

        return $score;
    }

    private function generateCacheKey(int $userId, ?float $latitude, ?float $longitude, ?array $categories, int $count, string $personalizationLevel = 'none'): string
    {
        $locationKey = $latitude && $longitude ? round($latitude, 3) . '_' . round($longitude, 3) : 'no_location';
        $categoryKey = $categories ? 'cat_' . implode('_', $categories) : 'no_cat';
        
        return "recommendations:{$userId}:{$locationKey}:{$categoryKey}:{$count}:p_{$personalizationLevel}";
    }

    /**
     * Get advanced AI-powered recommendations (slower but more accurate)
     * This method should be used for special cases where performance is less critical
     */
    public function getAdvancedAIRecommendations(
        User $user,
        ?float $latitude = null,
        ?float $longitude = null,
        ?array $categories = null,
        int $count = self::DEFAULT_RECOMMENDATIONS_COUNT
    ): Collection {
        // First try neural recommendations for advanced AI
        $neuralRecommendations = $this->neuralEngine->getRecommendations($user, [
            'latitude' => $latitude,
            'longitude' => $longitude,
            'categories' => $categories,
            'count' => $count
        ]);

        // If neural engine returns sufficient results, use hybrid approach
        if ($neuralRecommendations->count() >= $count * 0.7) {
            return $this->combineNeuralWithTraditional($user, $neuralRecommendations, $latitude, $longitude, $categories, $count);
        }

        // Fallback to traditional algorithms
        $contentBased = $this->contentBasedFilter->getRecommendations($user, $count);
        $collaborative = $this->collaborativeFilter->getRecommendations($user, $count);
        $locationBased = $this->locationBasedFilter->getRecommendations(
            $user, 
            $latitude, 
            $longitude, 
            $count
        );

        // Combine and weight the recommendations
        $combinedScores = $this->combineRecommendations([
            'content' => $contentBased,
            'collaborative' => $collaborative,
            'location' => $locationBased
        ]);

        // Filter by categories if specified
        if ($categories) {
            $combinedScores = $this->filterByCategories($combinedScores, $categories);
        }

        // Remove businesses user has already interacted with recently
        $combinedScores = $this->filterRecentInteractions($combinedScores, $user);

        // Sort by final score and limit results
        return $combinedScores
            ->sortByDesc('final_score')
            ->take($count)
            ->values();
    }

    public function getPersonalizedBusinesses(
        User $user,
        ?float $latitude = null,
        ?float $longitude = null,
        array $options = []
    ): Collection {
        $preferences = UserPreference::where('user_id', $user->id)->first();
        
        $query = Business::query()
            ->where('is_active', true)
            ->with(['categories', 'images']);

        // Apply preference-based filtering
        if ($preferences) {
            $this->applyPreferenceFilters($query, $preferences);
        }

        // Apply location filtering
        if ($latitude && $longitude) {
            $this->applyLocationFilter($query, $latitude, $longitude);
        }

        // Get businesses and add recommendation scores
        $businesses = $query->get();
        
        return $businesses->map(function ($business) use ($user, $latitude, $longitude) {
            $score = $this->calculateBusinessScore($business, $user, $latitude, $longitude);
            $business->recommendation_score = $score;
            $business->recommendation_reasons = $this->getRecommendationReasons($business, $user);
            return $business;
        })->sortByDesc('recommendation_score');
    }

    public function getSimilarBusinesses(Business $business, int $count = 10): Collection
    {
        // First try to get pre-calculated similarities
        $similarities = BusinessSimilarity::getSimilarBusinesses($business->id);
        
        $businesses = $similarities->take($count)
            ->map(function ($similarity) {
                $foundBusiness = Business::with(['category:id,name,icon_image', 'subcategory:id,name', 'logoImage', 'coverImage'])
                    ->find($similarity['business_id']);
                
                if ($foundBusiness) {
                    $foundBusiness->similarity_score = $similarity['similarity_score'];
                    $foundBusiness->similarity_type = $similarity['similarity_type'];
                    $foundBusiness->similarity_reasons = $similarity['contributing_factors'];
                }
                
                return $foundBusiness;
            })
            ->filter()
            ->values();

        // If no pre-calculated similarities exist, use fallback method
        if ($businesses->isEmpty()) {
            $businesses = $this->getSimilarBusinessesFallback($business, $count);
        }

        return $businesses;
    }

    /**
     * Fallback method to find similar businesses when no pre-calculated similarities exist
     */
    private function getSimilarBusinessesFallback(Business $business, int $count = 10): Collection
    {
        $similarBusinesses = collect();

        // Find businesses in the same category
        $sameCategoryBusinesses = Business::where('id', '!=', $business->id)
            ->where('category_id', $business->category_id)
            ->where('is_active', true)
            ->with(['category:id,name,icon_image', 'subcategory:id,name', 'logoImage', 'coverImage'])
            ->orderByDesc('overall_rating')
            ->orderByDesc('total_reviews')
            ->take($count)
            ->get();

        foreach ($sameCategoryBusinesses as $similarBusiness) {
            $similarBusiness->similarity_score = $this->calculateRealTimeSimilarity($business, $similarBusiness);
            $similarBusiness->similarity_type = 'category_similar';
            $similarBusiness->similarity_reasons = ['Same category', 'Similar ratings'];
            $similarBusinesses->push($similarBusiness);
        }

        // If we still don't have enough, find businesses with similar ratings in nearby area
        if ($similarBusinesses->count() < $count && $business->latitude && $business->longitude) {
            $nearbyBusinesses = Business::where('id', '!=', $business->id)
                ->where('is_active', true)
                ->whereNotIn('id', $similarBusinesses->pluck('id'))
                ->whereRaw(
                    "ST_Distance_Sphere(POINT(longitude, latitude), POINT(?, ?)) <= ?",
                    [$business->longitude, $business->latitude, 10000] // 10km radius
                )
                ->where('overall_rating', '>=', max(3.0, $business->overall_rating - 1))
                ->with(['category:id,name,icon_image', 'subcategory:id,name', 'logoImage', 'coverImage'])
                ->orderByDesc('overall_rating')
                ->take($count - $similarBusinesses->count())
                ->get();

            foreach ($nearbyBusinesses as $nearbyBusiness) {
                $nearbyBusiness->similarity_score = $this->calculateRealTimeSimilarity($business, $nearbyBusiness);
                $nearbyBusiness->similarity_type = 'location_similar';
                $nearbyBusiness->similarity_reasons = ['Nearby location', 'Similar ratings'];
                $similarBusinesses->push($nearbyBusiness);
            }
        }

        // If still not enough, get highly rated businesses from any category
        if ($similarBusinesses->count() < $count) {
            $topRatedBusinesses = Business::where('id', '!=', $business->id)
                ->where('is_active', true)
                ->whereNotIn('id', $similarBusinesses->pluck('id'))
                ->where('overall_rating', '>=', 4.0)
                ->with(['category:id,name,icon_image', 'subcategory:id,name', 'logoImage', 'coverImage'])
                ->orderByDesc('overall_rating')
                ->orderByDesc('total_reviews')
                ->take($count - $similarBusinesses->count())
                ->get();

            foreach ($topRatedBusinesses as $topBusiness) {
                $topBusiness->similarity_score = $this->calculateRealTimeSimilarity($business, $topBusiness);
                $topBusiness->similarity_type = 'general_similar';
                $topBusiness->similarity_reasons = ['Highly rated', 'Popular choice'];
                $similarBusinesses->push($topBusiness);
            }
        }

        return $similarBusinesses->take($count);
    }

    /**
     * Calculate real-time similarity score between two businesses
     */
    private function calculateRealTimeSimilarity(Business $businessA, Business $businessB): float
    {
        $score = 0.0;

        // Category similarity (40% weight)
        if ($businessA->category_id === $businessB->category_id) {
            $score += 0.4;
        }

        // Rating similarity (30% weight)
        $ratingDiff = abs($businessA->overall_rating - $businessB->overall_rating);
        $ratingScore = max(0, 1 - ($ratingDiff / 5));
        $score += $ratingScore * 0.3;

        // Location proximity (20% weight) - if both have coordinates
        if ($businessA->latitude && $businessA->longitude && 
            $businessB->latitude && $businessB->longitude) {
            $distance = $this->calculateDistance(
                $businessA->latitude, $businessA->longitude,
                $businessB->latitude, $businessB->longitude
            );
            $locationScore = max(0, 1 - ($distance / 10)); // 10km max for full score
            $score += $locationScore * 0.2;
        }

        // Price range similarity (10% weight)
        if ($businessA->price_range && $businessB->price_range) {
            $priceDiff = abs($businessA->price_range - $businessB->price_range);
            $priceScore = max(0, 1 - ($priceDiff / 3)); // Assuming price range 1-4
            $score += $priceScore * 0.1;
        }

        return round($score, 2);
    }

    public function trackInteraction(
        User $user,
        Business $business,
        string $interactionType,
        array $context = []
    ): void {
        // Track the interaction
        UserInteraction::track(
            $user->id,
            $business->id,
            $interactionType,
            $context['source'] ?? null,
            $context
        );

        // Update user preferences based on the interaction
        $this->updateUserPreferences($user, $business, $interactionType);

        // Trigger similarity calculation if needed
        if (in_array($interactionType, ['favorite', 'review', 'collection_add'])) {
            $this->queueSimilarityCalculation($business);
        }
    }

    private function combineRecommendations(array $recommendations): Collection
    {
        $weights = [
            'content' => 0.4,
            'collaborative' => 0.35,
            'location' => 0.25
        ];

        $combined = collect();

        foreach ($recommendations as $type => $recs) {
            foreach ($recs as $rec) {
                $businessId = $rec['business_id'];
                $score = $rec['score'] * $weights[$type];

                if ($combined->has($businessId)) {
                    $existing = $combined->get($businessId);
                    $existing['final_score'] += $score;
                    $existing['contributing_algorithms'][] = $type;
                    $combined->put($businessId, $existing);
                } else {
                    $combined->put($businessId, [
                        'business_id' => $businessId,
                        'final_score' => $score,
                        'contributing_algorithms' => [$type],
                        'business' => $rec['business'] ?? null
                    ]);
                }
            }
        }

        return $combined;
    }

    private function filterByCategories(Collection $recommendations, array $categories): Collection
    {
        return $recommendations->filter(function ($rec) use ($categories) {
            $business = $rec['business'] ?? Business::find($rec['business_id']);
            if (!$business) return false;

            $businessCategories = $business->categories->pluck('id')->toArray();
            return !empty(array_intersect($businessCategories, $categories));
        });
    }

    private function filterRecentInteractions(Collection $recommendations, User $user): Collection
    {
        $recentInteractionIds = UserInteraction::where('user_id', $user->id)
            ->where('created_at', '>=', now()->subDays(7))
            ->whereIn('interaction_type', ['view', 'favorite', 'phone_call'])
            ->pluck('business_id')
            ->toArray();

        return $recommendations->reject(function ($rec) use ($recentInteractionIds) {
            return in_array($rec['business_id'], $recentInteractionIds);
        });
    }

    private function applyPreferenceFilters($query, UserPreference $preferences): void
    {
        // Filter by preferred categories
        if ($preferences->preferred_categories) {
            $query->whereHas('categories', function ($q) use ($preferences) {
                $q->whereIn('categories.id', $preferences->preferred_categories);
            });
        }

        // Filter by price range
        if ($preferences->price_range_min !== null) {
            $query->where('price_range', '>=', $preferences->price_range_min);
        }
        if ($preferences->price_range_max !== null) {
            $query->where('price_range', '<=', $preferences->price_range_max);
        }

        // Filter by rating
        if ($preferences->minimum_rating) {
            $query->where('overall_rating', '>=', $preferences->minimum_rating);
        }
    }

    private function applyLocationFilter($query, float $latitude, float $longitude): void
    {
        $query->whereRaw(
            "ST_Distance_Sphere(POINT(longitude, latitude), POINT(?, ?)) <= ?",
            [$longitude, $latitude, self::LOCATION_RADIUS_KM * 1000]
        );
    }

    private function calculateBusinessScore(
        Business $business,
        User $user,
        ?float $latitude = null,
        ?float $longitude = null
    ): float {
        $score = 0;

        // Base score from rating and popularity
        $score += $business->overall_rating * 0.3;
        $score += min($business->total_reviews / 100, 1) * 0.2;

        // Category preference score
        $preferences = UserPreference::where('user_id', $user->id)->first();
        if ($preferences && $preferences->preferred_categories) {
            $businessCategories = $business->categories->pluck('id')->toArray();
            $overlap = array_intersect($businessCategories, $preferences->preferred_categories);
            $score += (count($overlap) / count($preferences->preferred_categories)) * 0.3;
        }

        // Location score
        if ($latitude && $longitude && $business->latitude && $business->longitude) {
            $distance = $this->calculateDistance(
                $latitude, $longitude,
                $business->latitude, $business->longitude
            );
            $locationScore = max(0, 1 - ($distance / self::LOCATION_RADIUS_KM));
            $score += $locationScore * 0.2;
        }

        return round($score, 2);
    }

    private function getRecommendationReasons(Business $business, User $user): array
    {
        $reasons = [];

        // Check category preferences
        $preferences = UserPreference::where('user_id', $user->id)->first();
        if ($preferences && $preferences->preferred_categories) {
            $businessCategories = $business->categories->pluck('id')->toArray();
            $overlap = array_intersect($businessCategories, $preferences->preferred_categories);
            if (!empty($overlap)) {
                $categoryNames = Category::whereIn('id', $overlap)->pluck('name')->toArray();
                $reasons[] = "Matches your interest in " . implode(', ', $categoryNames);
            }
        }

        // Check rating
        if ($business->overall_rating >= 4.0) {
            $reasons[] = "Highly rated (" . $business->overall_rating . " stars)";
        }

        // Check popularity
        if ($business->total_reviews >= 50) {
            $reasons[] = "Popular with " . $business->total_reviews . " reviews";
        }

        return $reasons;
    }

    private function updateUserPreferences(User $user, Business $business, string $interactionType): void
    {
        $preferences = UserPreference::firstOrCreate(['user_id' => $user->id]);

        // Update category preferences based on interaction
        $businessCategories = $business->categories->pluck('id')->toArray();
        $currentCategories = $preferences->preferred_categories ?? [];

        foreach ($businessCategories as $categoryId) {
            if (!in_array($categoryId, $currentCategories)) {
                $currentCategories[] = $categoryId;
            }
        }

        $preferences->preferred_categories = $currentCategories;
        $preferences->save();
    }

    private function queueSimilarityCalculation(Business $business): void
    {
        // This would typically queue a job to calculate business similarities
        // For now, we'll just mark that it needs updating
        dispatch(function () use ($business) {
            $this->calculateBusinessSimilarities($business);
        })->afterResponse();
    }

    private function calculateBusinessSimilarities(Business $business): void
    {
        // Find similar businesses based on various factors
        $similarBusinesses = Business::where('id', '!=', $business->id)
            ->whereHas('categories', function ($q) use ($business) {
                $q->whereIn('categories.id', $business->categories->pluck('id'));
            })
            ->limit(100)
            ->get();

        foreach ($similarBusinesses as $similarBusiness) {
            $factors = $this->calculateSimilarityFactors($business, $similarBusiness);
            BusinessSimilarity::calculateAndStore($business->id, $similarBusiness->id, $factors);
        }
    }

    private function calculateSimilarityFactors(Business $businessA, Business $businessB): array
    {
        $factors = [
            'category_match' => $this->calculateCategoryMatch($businessA, $businessB),
            'location_proximity' => $this->calculateLocationProximity($businessA, $businessB),
            'review_sentiment' => $this->calculateReviewSentimentSimilarity($businessA, $businessB),
            'feature_overlap' => $this->calculateFeatureOverlap($businessA, $businessB),
            'user_overlap' => $this->calculateUserOverlap($businessA, $businessB)
        ];

        return $factors;
    }

    private function calculateCategoryMatch(Business $businessA, Business $businessB): float
    {
        $categoriesA = $businessA->categories->pluck('id')->toArray();
        $categoriesB = $businessB->categories->pluck('id')->toArray();

        if (empty($categoriesA) || empty($categoriesB)) {
            return 0;
        }

        $intersection = array_intersect($categoriesA, $categoriesB);
        $union = array_unique(array_merge($categoriesA, $categoriesB));

        return count($intersection) / count($union);
    }

    private function calculateLocationProximity(Business $businessA, Business $businessB): float
    {
        if (!$businessA->latitude || !$businessA->longitude || 
            !$businessB->latitude || !$businessB->longitude) {
            return 0;
        }

        $distance = $this->calculateDistance(
            $businessA->latitude, $businessA->longitude,
            $businessB->latitude, $businessB->longitude
        );

        // Closer businesses get higher scores
        return max(0, 1 - ($distance / 10)); // 10km max for full proximity score
    }

    private function calculateReviewSentimentSimilarity(Business $businessA, Business $businessB): float
    {
        // Simple rating-based similarity for now
        $ratingDiff = abs($businessA->overall_rating - $businessB->overall_rating);
        return max(0, 1 - ($ratingDiff / 5));
    }

    private function calculateFeatureOverlap(Business $businessA, Business $businessB): float
    {
        // This would compare business features/amenities when available
        // For now, return a baseline score
        return 0.5;
    }

    private function calculateUserOverlap(Business $businessA, Business $businessB): float
    {
        $usersA = UserInteraction::where('business_id', $businessA->id)
            ->pluck('user_id')
            ->unique()
            ->toArray();

        $usersB = UserInteraction::where('business_id', $businessB->id)
            ->pluck('user_id')
            ->unique()
            ->toArray();

        if (empty($usersA) || empty($usersB)) {
            return 0;
        }

        $intersection = array_intersect($usersA, $usersB);
        $union = array_unique(array_merge($usersA, $usersB));

        return count($intersection) / count($union);
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

    private function combineNeuralWithTraditional(
        User $user,
        Collection $neuralRecommendations,
        ?float $latitude,
        ?float $longitude,
        ?array $categories,
        int $count
    ): Collection {
        // Get traditional recommendations for diversity
        $traditionalCount = max(1, intval($count * 0.3));
        $contentBased = $this->contentBasedFilter->getRecommendations($user, $traditionalCount);
        $collaborative = $this->collaborativeFilter->getRecommendations($user, $traditionalCount);

        // Combine neural (70%) with traditional (30%) for balanced results
        $combined = collect();
        
        // Add neural recommendations with higher weight
        foreach ($neuralRecommendations->take($count * 0.7) as $business) {
            $combined->push([
                'business' => $business,
                'score' => $business->ai_score ?? 0.8,
                'source' => 'neural'
            ]);
        }

        // Add some traditional recommendations for diversity
        foreach ($contentBased->merge($collaborative)->take($traditionalCount) as $businessData) {
            // Traditional filters return arrays with 'business' key
            $business = $businessData['business'] ?? $businessData;
            $businessId = $business->id ?? null;
            
            if ($businessId && !$combined->contains(function ($item) use ($businessId) {
                $itemBusiness = $item['business'];
                return ($itemBusiness->id ?? null) === $businessId;
            })) {
                $combined->push([
                    'business' => $business,
                    'score' => $businessData['score'] ?? 0.6,
                    'source' => 'traditional'
                ]);
            }
        }

        // Return clean Business objects
        return $combined->sortByDesc('score')->take($count)->map(function ($item) {
            return $item['business'];
        })->values();
    }

    /**
     * Light personalization - adds basic user preference scoring
     */
    private function getLightPersonalizedRecommendations(
        User $user,
        ?float $latitude = null,
        ?float $longitude = null,
        ?array $categories = null,
        int $count = self::DEFAULT_RECOMMENDATIONS_COUNT
    ): Collection {
        // Get user profile quickly
        $userProfile = $this->getUserProfileFast($user->id);
        
        // Start with fast recommendations
        $businesses = $this->getBaseBusinessQuery($latitude, $longitude, $categories, $count);
        
        return $businesses->map(function ($business) use ($user, $userProfile, $latitude, $longitude) {
            $baseScore = $this->calculateSimpleScore($business, $user, $latitude, $longitude);
            $personalBoost = $this->calculateLightPersonalBoost($business, $userProfile);
            
            return [
                'business_id' => $business->id,
                'business' => $business,
                'final_score' => $baseScore + $personalBoost,
                'contributing_algorithms' => ['fast', 'light_personal'],
                'personalization_applied' => $personalBoost > 0
            ];
        })->sortByDesc('final_score')->take($count)->values();
    }

    /**
     * Full personalization - comprehensive user behavior analysis
     */
    private function getFullPersonalizedRecommendations(
        User $user,
        ?float $latitude = null,
        ?float $longitude = null,
        ?array $categories = null,
        int $count = self::DEFAULT_RECOMMENDATIONS_COUNT
    ): Collection {
        // Get comprehensive user profile
        $userProfile = $this->getUserProfileComprehensive($user->id);
        
        // Apply personalized filters to query
        $businesses = $this->getPersonalizedBusinessQuery($user, $userProfile, $latitude, $longitude, $categories, $count);
        
        return $businesses->map(function ($business) use ($user, $userProfile, $latitude, $longitude) {
            $baseScore = $this->calculateSimpleScore($business, $user, $latitude, $longitude);
            $personalScore = $this->calculateFullPersonalScore($business, $userProfile);
            
            return [
                'business_id' => $business->id,
                'business' => $business,
                'final_score' => ($baseScore * 0.6) + ($personalScore * 0.4),
                'contributing_algorithms' => ['fast', 'full_personal'],
                'personalization_applied' => true,
                'personalization_factors' => $this->getPersonalizationFactors($business, $userProfile)
            ];
        })->sortByDesc('final_score')->take($count)->values();
    }

    /**
     * Get base business query without personalization
     */
    private function getBaseBusinessQuery(?float $latitude, ?float $longitude, ?array $categories, int $count): Collection
    {
        $businesses = Business::where('is_active', true)
            ->with(['categories:id,name', 'images' => function($query) {
                $query->where('is_primary', true)->select('id', 'business_id', 'image_url', 'image_type');
            }]);

        if ($latitude && $longitude) {
            $businesses = $businesses->selectRaw(
                "businesses.*, ST_Distance_Sphere(POINT(longitude, latitude), POINT(?, ?)) as distance",
                [$longitude, $latitude]
            )
            ->whereRaw(
                "ST_Distance_Sphere(POINT(longitude, latitude), POINT(?, ?)) <= ?",
                [$longitude, $latitude, self::LOCATION_RADIUS_KM * 1000]
            )
            ->orderBy('distance');
        } else {
            $businesses = $businesses->select('businesses.*')
                ->orderByDesc('overall_rating')
                ->orderByDesc('discovery_score');
        }

        if ($categories) {
            $businesses = $businesses->whereIn('category_id', $categories);
        }

        return $businesses->take($count * 2)->get();
    }

    /**
     * Get personalized business query with preference filters
     */
    private function getPersonalizedBusinessQuery(User $user, array $userProfile, ?float $latitude, ?float $longitude, ?array $categories, int $count): Collection
    {
        $businesses = Business::where('is_active', true)
            ->with(['categories:id,name', 'images' => function($query) {
                $query->where('is_primary', true)->select('id', 'business_id', 'image_url', 'image_type');
            }]);

        // Apply location filter
        if ($latitude && $longitude) {
            $businesses = $businesses->selectRaw(
                "businesses.*, ST_Distance_Sphere(POINT(longitude, latitude), POINT(?, ?)) as distance",
                [$longitude, $latitude]
            )
            ->whereRaw(
                "ST_Distance_Sphere(POINT(longitude, latitude), POINT(?, ?)) <= ?",
                [$longitude, $latitude, self::LOCATION_RADIUS_KM * 1000]
            );
        } else {
            $businesses = $businesses->select('businesses.*');
        }

        // Personalization filters
        if (!empty($userProfile['preferred_categories'])) {
            $preferredCats = array_keys($userProfile['preferred_categories']);
            $businesses = $businesses->where(function($q) use ($preferredCats) {
                $q->whereIn('category_id', $preferredCats)
                  ->orWhere('is_featured', true); // Always include featured
            });
        }

        if (!empty($userProfile['preferred_price_range'])) {
            $businesses = $businesses->whereIn('price_range', $userProfile['preferred_price_range']);
        }

        if ($categories) {
            $businesses = $businesses->whereIn('category_id', $categories);
        }

        return $businesses->take($count * 2)->get();
    }

    /**
     * Fast user profile for light personalization
     */
    private function getUserProfileFast(int $userId): array
    {
        return Cache::remember("user_profile_fast:{$userId}", 1800, function () use ($userId) {
            $recentInteractions = UserInteraction::where('user_id', $userId)
                ->where('created_at', '>=', now()->subDays(30))
                ->with('business:id,category_id,price_range')
                ->limit(100)
                ->get();

            $categoryPreferences = [];
            $visitedBusinesses = [];
            $priceRangePrefs = [];

            foreach ($recentInteractions as $interaction) {
                if (in_array($interaction->interaction_type, ['favorite', 'review', 'phone_call', 'visit'])) {
                    $visitedBusinesses[] = $interaction->business_id;
                    
                    if ($interaction->business) {
                        $categoryId = $interaction->business->category_id;
                        $weight = $interaction->weight ?? UserInteraction::$interactionWeights[$interaction->interaction_type] ?? 1.0;
                        $categoryPreferences[$categoryId] = ($categoryPreferences[$categoryId] ?? 0) + $weight;
                        
                        if ($interaction->business->price_range) {
                            $priceRangePrefs[] = $interaction->business->price_range;
                        }
                    }
                }
            }

            return [
                'preferred_categories' => array_slice($categoryPreferences, 0, 5, true),
                'visited_businesses' => array_unique($visitedBusinesses),
                'preferred_price_range' => array_unique($priceRangePrefs)
            ];
        });
    }

    /**
     * Comprehensive user profile for full personalization
     */
    private function getUserProfileComprehensive(int $userId): array
    {
        return Cache::remember("user_profile_full:{$userId}", 3600, function () use ($userId) {
            $profile = $this->getUserProfileFast($userId);
            
            // Add similar user behavior
            $profile['similar_users_liked'] = $this->getSimilarUserBusinessesFast($userId);
            
            // Add time-based preferences
            $profile['time_preferences'] = $this->getTimeBasedPreferences($userId);
            
            // Add rating sensitivity
            $profile['rating_sensitivity'] = $this->getRatingSensitivity($userId);
            
            return $profile;
        });
    }

    /**
     * Calculate light personalization boost
     */
    private function calculateLightPersonalBoost(Business $business, array $userProfile): float
    {
        $boost = 0;
        
        // Category preference boost (max 0.1)
        if (isset($userProfile['preferred_categories'][$business->category_id])) {
            $categoryWeight = $userProfile['preferred_categories'][$business->category_id];
            $boost += min($categoryWeight / 10, 0.1);
        }
        
        // Price range preference boost (max 0.05)
        if (in_array($business->price_range, $userProfile['preferred_price_range'] ?? [])) {
            $boost += 0.05;
        }
        
        // Repeat visit boost (max 0.1)
        if (in_array($business->id, $userProfile['visited_businesses'] ?? [])) {
            $boost += 0.1;
        }
        
        return min($boost, 0.25); // Cap total boost
    }

    /**
     * Calculate full personalization score
     */
    private function calculateFullPersonalScore(Business $business, array $userProfile): float
    {
        $score = 0;
        
        // Category preference (0.4 weight)
        if (isset($userProfile['preferred_categories'][$business->category_id])) {
            $categoryWeight = $userProfile['preferred_categories'][$business->category_id];
            $score += min($categoryWeight / 10, 0.4);
        }
        
        // Price range preference (0.2 weight)
        if (in_array($business->price_range, $userProfile['preferred_price_range'] ?? [])) {
            $score += 0.2;
        }
        
        // Similar user behavior (0.2 weight)
        if (in_array($business->id, $userProfile['similar_users_liked'] ?? [])) {
            $score += 0.2;
        }
        
        // Time-based preferences (0.1 weight)
        if ($this->matchesTimePreferences($business, $userProfile['time_preferences'] ?? [])) {
            $score += 0.1;
        }
        
        // Rating sensitivity adjustment (0.1 weight)
        $ratingSensitivity = $userProfile['rating_sensitivity'] ?? 0.5;
        if ($business->overall_rating >= 4.0 && $ratingSensitivity > 0.7) {
            $score += 0.1;
        }
        
        return min($score, 1.0);
    }

    /**
     * Get similar user businesses using collaborative filtering
     */
    private function getSimilarUserBusinessesFast(int $userId): array
    {
        return Cache::remember("similar_users_businesses:{$userId}", 3600, function () use ($userId) {
            return DB::table('user_interactions as ui1')
                ->join('user_interactions as ui2', function($join) use ($userId) {
                    $join->on('ui1.business_id', '=', 'ui2.business_id')
                         ->where('ui1.user_id', $userId)
                         ->where('ui2.user_id', '!=', $userId)
                         ->whereIn('ui1.interaction_type', ['favorite', 'review', 'phone_call'])
                         ->whereIn('ui2.interaction_type', ['favorite', 'review', 'phone_call']);
                })
                ->select('ui2.business_id')
                ->distinct()
                ->limit(20)
                ->pluck('business_id')
                ->toArray();
        });
    }

    /**
     * Get time-based preferences
     */
    private function getTimeBasedPreferences(int $userId): array
    {
        // This would analyze when user typically interacts with businesses
        // For now, return empty array as placeholder
        return [];
    }

    /**
     * Get user's rating sensitivity
     */
    private function getRatingSensitivity(int $userId): float
    {
        $interactions = DB::table('user_interactions')
            ->join('businesses', 'user_interactions.business_id', '=', 'businesses.id')
            ->where('user_interactions.user_id', $userId)
            ->whereIn('interaction_type', ['favorite', 'review'])
            ->avg('businesses.overall_rating');
            
        return $interactions ? min($interactions / 5, 1.0) : 0.5;
    }

    /**
     * Check if business matches time preferences
     */
    private function matchesTimePreferences(Business $business, array $timePreferences): bool
    {
        // Placeholder - would implement time-based matching
        return false;
    }

    /**
     * Get personalization factors for explanation
     */
    private function getPersonalizationFactors(Business $business, array $userProfile): array
    {
        $factors = [];
        
        if (isset($userProfile['preferred_categories'][$business->category_id])) {
            $factors[] = 'matches_category_preference';
        }
        
        if (in_array($business->price_range, $userProfile['preferred_price_range'] ?? [])) {
            $factors[] = 'matches_price_preference';
        }
        
        if (in_array($business->id, $userProfile['similar_users_liked'] ?? [])) {
            $factors[] = 'liked_by_similar_users';
        }
        
        return $factors;
    }

    /**
     * Track personalization metrics for analytics and A/B testing
     */
    private function trackPersonalizationMetrics(
        int $userId,
        string $personalizationLevel,
        float $responseTimeMs,
        int $recommendationCount,
        array $additionalMetrics = []
    ): void {
        try {
            // Record detailed metrics in the database
            \App\Models\PersonalizationMetrics::record(
                $userId,
                $personalizationLevel,
                $responseTimeMs,
                $recommendationCount,
                array_merge([
                    'timestamp' => now()->toISOString(),
                    'cache_hit' => false, // This was a fresh generation
                    'source' => 'recommendation_service'
                ], $additionalMetrics),
                session()->getId()
            );

            // Also track for A/B testing service
            dispatch(function () use ($userId, $personalizationLevel, $responseTimeMs, $recommendationCount) {
                app(ABTestingService::class)->trackExperiment('personalization_level', $userId, $personalizationLevel, [
                    'response_time_ms' => $responseTimeMs,
                    'recommendation_count' => $recommendationCount,
                    'timestamp' => now()->toISOString()
                ]);
            })->afterResponse();

        } catch (\Exception $e) {
            // Don't let metrics tracking break recommendations
            \Illuminate\Support\Facades\Log::warning('Failed to track personalization metrics', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
        }
    }
}
