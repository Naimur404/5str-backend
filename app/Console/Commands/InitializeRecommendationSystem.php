<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Business;
use App\Models\UserPreference;
use App\Models\UserInteraction;
use App\Models\BusinessSimilarity;
use App\Models\Favorite;
use App\Models\Review;
use App\Services\RecommendationService;

class InitializeRecommendationSystem extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'recommendations:init 
                          {--users= : Specific user IDs to process (comma-separated)}
                          {--businesses= : Specific business IDs to process (comma-separated)}
                          {--similarities : Only calculate business similarities}
                          {--preferences : Only initialize user preferences}
                          {--interactions : Only backfill interactions}
                          {--force : Force recalculation of existing data}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Initialize the AI recommendation system by creating user preferences, backfilling interactions, and calculating business similarities';

    private RecommendationService $recommendationService;

    public function __construct(RecommendationService $recommendationService)
    {
        parent::__construct();
        $this->recommendationService = $recommendationService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 Initializing AI Recommendation System...');

        $users = $this->option('users') ? 
            explode(',', $this->option('users')) : 
            null;

        $businesses = $this->option('businesses') ? 
            explode(',', $this->option('businesses')) : 
            null;

        $onlySimilarities = $this->option('similarities');
        $onlyPreferences = $this->option('preferences');
        $onlyInteractions = $this->option('interactions');
        $force = $this->option('force');

        // If no specific option is provided, run all initialization steps
        if (!$onlySimilarities && !$onlyPreferences && !$onlyInteractions) {
            $this->initializeUserPreferences($users, $force);
            $this->backfillUserInteractions($users, $force);
            $this->calculateBusinessSimilarities($businesses, $force);
        } else {
            if ($onlyPreferences) {
                $this->initializeUserPreferences($users, $force);
            }
            
            if ($onlyInteractions) {
                $this->backfillUserInteractions($users, $force);
            }
            
            if ($onlySimilarities) {
                $this->calculateBusinessSimilarities($businesses, $force);
            }
        }

        $this->info('✅ Recommendation system initialization completed!');
        
        // Show statistics
        $this->showStatistics();
    }

    private function initializeUserPreferences(?array $userIds = null, bool $force = false): void
    {
        $this->info('📊 Initializing user preferences...');

        $query = User::query();
        if ($userIds) {
            $query->whereIn('id', $userIds);
        }

        $users = $query->get();
        $bar = $this->output->createProgressBar($users->count());

        foreach ($users as $user) {
            // Skip if preferences already exist and not forcing
            if (!$force && UserPreference::where('user_id', $user->id)->exists()) {
                $bar->advance();
                continue;
            }

            $this->createUserPreferences($user);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("✅ Processed {$users->count()} users for preference initialization");
    }

    private function createUserPreferences(User $user): void
    {
        // Get user's favorite categories from their business favorites
        $favoriteCategories = Favorite::where('user_id', $user->id)
            ->where('favoritable_type', 'App\\Models\\Business')
            ->join('businesses', 'favorites.favoritable_id', '=', 'businesses.id')
            ->join('business_categories', 'businesses.id', '=', 'business_categories.business_id')
            ->pluck('business_categories.category_id')
            ->unique()
            ->toArray();

        // Get categories from reviewed businesses
        $reviewedCategories = Review::where('user_id', $user->id)
            ->where('reviewable_type', 'App\\Models\\Business')
            ->join('businesses', 'reviews.reviewable_id', '=', 'businesses.id')
            ->join('business_categories', 'businesses.id', '=', 'business_categories.business_id')
            ->pluck('business_categories.category_id')
            ->unique()
            ->toArray();

        // Combine and deduplicate categories
        $preferredCategories = array_unique(array_merge($favoriteCategories, $reviewedCategories));

        // Calculate price range from favorites and reviews
        $favoriteBusinessIds = Favorite::where('user_id', $user->id)
            ->where('favoritable_type', 'App\\Models\\Business')
            ->pluck('favoritable_id')
            ->toArray();

        $reviewedBusinessIds = Review::where('user_id', $user->id)
            ->where('reviewable_type', 'App\\Models\\Business')
            ->pluck('reviewable_id')
            ->toArray();

        $businessIds = array_merge($favoriteBusinessIds, $reviewedBusinessIds);

        $priceData = Business::whereIn('id', $businessIds)
            ->whereNotNull('price_range')
            ->pluck('price_range');

        $priceRangeMin = $priceData->isNotEmpty() ? $priceData->min() : null;
        $priceRangeMax = $priceData->isNotEmpty() ? $priceData->max() : null;

        // Calculate minimum rating preference (use average of user's ratings if available)
        $avgUserRating = Review::where('user_id', $user->id)
            ->where('reviewable_type', 'App\\Models\\Business')
            ->avg('overall_rating');
        $minimumRating = $avgUserRating ? max(1, $avgUserRating - 0.5) : null;

        UserPreference::updateOrCreate(
            ['user_id' => $user->id],
            [
                'preferred_categories' => $preferredCategories ?: null,
                'price_range_min' => $priceRangeMin,
                'price_range_max' => $priceRangeMax,
                'minimum_rating' => $minimumRating,
                'preferred_location_lat' => $user->current_latitude,
                'preferred_location_lng' => $user->current_longitude,
                'preferred_radius_km' => 25.0, // Default 25km radius
                'notification_preferences' => [
                    'new_recommendations' => true,
                    'similar_businesses' => true,
                    'trending_nearby' => true
                ]
            ]
        );
    }

    private function backfillUserInteractions(?array $userIds = null, bool $force = false): void
    {
        $this->info('🔄 Backfilling user interactions...');

        $query = User::query();
        if ($userIds) {
            $query->whereIn('id', $userIds);
        }

        $users = $query->get();
        $bar = $this->output->createProgressBar($users->count());

        foreach ($users as $user) {
            $this->backfillUserInteractionsForUser($user, $force);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("✅ Processed {$users->count()} users for interaction backfill");
    }

    private function backfillUserInteractionsForUser(User $user, bool $force = false): void
    {
        // Backfill favorite interactions (for business favorites only)
        $favorites = Favorite::where('user_id', $user->id)
            ->where('favoritable_type', 'App\\Models\\Business')
            ->get();
            
        foreach ($favorites as $favorite) {
            $existingInteraction = UserInteraction::where('user_id', $user->id)
                ->where('business_id', $favorite->favoritable_id)
                ->where('interaction_type', 'favorite')
                ->first();

            if (!$existingInteraction || $force) {
                UserInteraction::updateOrCreate([
                    'user_id' => $user->id,
                    'business_id' => $favorite->favoritable_id,
                    'interaction_type' => 'favorite',
                ], [
                    'source' => 'backfill',
                    'weight' => UserInteraction::$interactionWeights['favorite'],
                    'interaction_time' => $favorite->created_at,
                    'created_at' => $favorite->created_at,
                    'updated_at' => now()
                ]);
            }
        }

        // Backfill review interactions (for business reviews only)
        $reviews = Review::where('user_id', $user->id)
            ->where('reviewable_type', 'App\\Models\\Business')
            ->get();
            
        foreach ($reviews as $review) {
            $existingInteraction = UserInteraction::where('user_id', $user->id)
                ->where('business_id', $review->reviewable_id)
                ->where('interaction_type', 'review')
                ->first();

            if (!$existingInteraction || $force) {
                UserInteraction::updateOrCreate([
                    'user_id' => $user->id,
                    'business_id' => $review->reviewable_id,
                    'interaction_type' => 'review',
                ], [
                    'source' => 'backfill',
                    'weight' => UserInteraction::$interactionWeights['review'],
                    'context_data' => [
                        'rating' => $review->overall_rating,
                        'review_id' => $review->id
                    ],
                    'interaction_time' => $review->created_at,
                    'created_at' => $review->created_at,
                    'updated_at' => now()
                ]);
            }
        }
    }

    private function calculateBusinessSimilarities(?array $businessIds = null, bool $force = false): void
    {
        $this->info('🧮 Calculating business similarities...');

        $query = Business::where('is_active', true);
        if ($businessIds) {
            $query->whereIn('id', $businessIds);
        }

        $businesses = $query->get();
        $totalPairs = $businesses->count() * ($businesses->count() - 1) / 2;
        
        $this->info("Processing {$businesses->count()} businesses ({$totalPairs} potential pairs)");
        
        $bar = $this->output->createProgressBar($businesses->count());
        $processed = 0;

        foreach ($businesses as $businessA) {
            $this->calculateSimilaritiesForBusiness($businessA, $businesses, $force);
            $processed++;
            $bar->advance();

            // Process in batches to avoid memory issues
            if ($processed % 100 === 0) {
                $this->line(" Processed {$processed} businesses...");
            }
        }

        $bar->finish();
        $this->newLine();
        $this->info("✅ Calculated similarities for {$businesses->count()} businesses");
    }

    private function calculateSimilaritiesForBusiness(Business $businessA, $allBusinesses, bool $force = false): void
    {
        foreach ($allBusinesses as $businessB) {
            if ($businessA->id >= $businessB->id) {
                continue; // Skip same business and avoid duplicates
            }

            // Check if similarity already exists
            $existingSimilarity = BusinessSimilarity::where('business_a_id', min($businessA->id, $businessB->id))
                ->where('business_b_id', max($businessA->id, $businessB->id))
                ->first();

            if ($existingSimilarity && !$force) {
                continue;
            }

            $factors = $this->calculateSimilarityFactors($businessA, $businessB);
            BusinessSimilarity::calculateAndStore($businessA->id, $businessB->id, $factors);
        }
    }

    private function calculateSimilarityFactors(Business $businessA, Business $businessB): array
    {
        // Category similarity
        $categoriesA = $businessA->categories->pluck('id')->toArray();
        $categoriesB = $businessB->categories->pluck('id')->toArray();
        
        $categoryMatch = 0;
        if (!empty($categoriesA) && !empty($categoriesB)) {
            $intersection = array_intersect($categoriesA, $categoriesB);
            $union = array_unique(array_merge($categoriesA, $categoriesB));
            $categoryMatch = count($intersection) / count($union);
        }

        // Location proximity
        $locationProximity = 0;
        if ($businessA->latitude && $businessA->longitude && $businessB->latitude && $businessB->longitude) {
            $distance = $this->calculateDistance(
                $businessA->latitude, $businessA->longitude,
                $businessB->latitude, $businessB->longitude
            );
            $locationProximity = max(0, 1 - ($distance / 10)); // 10km max for full score
        }

        // Rating similarity
        $reviewSentiment = 0;
        if ($businessA->average_rating && $businessB->average_rating) {
            $ratingDiff = abs($businessA->average_rating - $businessB->average_rating);
            $reviewSentiment = max(0, 1 - ($ratingDiff / 5));
        }

        // User overlap (users who interacted with both)
        $usersA = UserInteraction::where('business_id', $businessA->id)->pluck('user_id')->unique();
        $usersB = UserInteraction::where('business_id', $businessB->id)->pluck('user_id')->unique();
        
        $userOverlap = 0;
        if ($usersA->isNotEmpty() && $usersB->isNotEmpty()) {
            $intersection = $usersA->intersect($usersB);
            $union = $usersA->merge($usersB)->unique();
            $userOverlap = $intersection->count() / $union->count();
        }

        return [
            'category_match' => $categoryMatch,
            'location_proximity' => $locationProximity,
            'review_sentiment' => $reviewSentiment,
            'feature_overlap' => 0.5, // Placeholder for future feature comparison
            'user_overlap' => $userOverlap
        ];
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

    private function showStatistics(): void
    {
        $this->newLine();
        $this->info('📈 Recommendation System Statistics:');
        
        $userPreferences = UserPreference::count();
        $userInteractions = UserInteraction::count();
        $businessSimilarities = BusinessSimilarity::count();
        
        $this->table(
            ['Component', 'Count'],
            [
                ['User Preferences', number_format($userPreferences)],
                ['User Interactions', number_format($userInteractions)],
                ['Business Similarities', number_format($businessSimilarities)],
            ]
        );

        // Show recent interaction types
        $interactionTypes = UserInteraction::select('interaction_type')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('interaction_type')
            ->orderByDesc('count')
            ->get();

        if ($interactionTypes->isNotEmpty()) {
            $this->newLine();
            $this->info('🔥 Most Common Interaction Types:');
            $this->table(
                ['Interaction Type', 'Count'],
                $interactionTypes->map(function ($type) {
                    return [$type->interaction_type, number_format($type->count)];
                })->toArray()
            );
        }
    }
}
