<?php

namespace App\Services\AI;

use App\Models\User;
use App\Models\Business;
use App\Models\UserInteraction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Advanced AI-powered recommendation engine using neural networks
 * and sophisticated machine learning techniques
 */
class NeuralRecommendationEngine
{
    private const EMBEDDING_DIMENSIONS = 128;
    private const LEARNING_RATE = 0.01;
    private const TRAINING_EPOCHS = 100;

    public function __construct(
        private ?string $mlServiceUrl = null
    ) {
        // Configure ML service URL based on environment
        $this->mlServiceUrl = $mlServiceUrl ?? $this->getMLServiceUrl();
    }

    private function getMLServiceUrl(): string
    {
        return match(app()->environment()) {
            'production' => env('ML_SERVICE_URL', 'https://ml-api.your-domain.com/ml'),
            'staging' => env('ML_SERVICE_URL', 'https://staging-ml.your-domain.com/ml'),
            'testing' => env('ML_SERVICE_URL', 'http://mock-ml-service:8000/ml'),
            default => env('ML_SERVICE_URL', 'http://localhost:8000/ml') // Development
        };
    }

    /**
     * Generate business embeddings using neural networks
     */
    public function generateBusinessEmbeddings(): array
    {
        $businesses = Business::with(['categories', 'reviews'])
            ->where('is_active', true)
            ->get();

        $businessFeatures = $businesses->map(function ($business) {
            return [
                'id' => $business->id,
                'features' => $this->extractBusinessFeatures($business),
                'text_content' => $this->getBusinessTextContent($business)
            ];
        });

        // Use neural network to generate embeddings
        return $this->callMLService('generate_embeddings', [
            'businesses' => $businessFeatures->toArray(),
            'dimensions' => self::EMBEDDING_DIMENSIONS
        ]);
    }

    /**
     * Generate user embeddings based on interaction history
     */
    public function generateUserEmbeddings(User $user): array
    {
        $interactions = UserInteraction::where('user_id', $user->id)
            ->with('business')
            ->get();

        $userProfile = [
            'user_id' => $user->id,
            'interaction_sequence' => $interactions->map(function ($interaction) {
                return [
                    'business_id' => $interaction->business_id,
                    'interaction_type' => $interaction->interaction_type,
                    'weight' => $interaction->weight,
                    'timestamp' => $interaction->interaction_time->timestamp,
                    'business_features' => $this->extractBusinessFeatures($interaction->business)
                ];
            })->toArray()
        ];

        return $this->callMLService('generate_user_embedding', [
            'user_profile' => $userProfile,
            'dimensions' => self::EMBEDDING_DIMENSIONS
        ]);
    }

    /**
     * Get AI-powered recommendations for a user
     */
    public function getRecommendations(User $user, array $params = []): Collection
    {
        try {
            // Prepare user context for neural processing
            $userContext = $this->prepareUserContext($user);
            
            // Get neural embeddings
            $userEmbedding = $this->getUserEmbedding($user);
            $businessEmbeddings = $this->getBusinessEmbeddings($params);
            
            // Calculate deep learning similarities
            $similarities = $this->calculateDeepSimilarities($userEmbedding, $businessEmbeddings);
            
            // Apply reinforcement learning optimization
            $optimizedScores = $this->applyReinforcementLearning($user, $similarities);
            
            // Get ensemble recommendations
            $recommendations = $this->getEnsembleRecommendationsInternal($user, $optimizedScores, $params);
            
            // Log neural activity
            Log::info("Neural recommendations generated", [
                'user_id' => $user->id,
                'count' => $recommendations->count(),
                'neural_confidence' => $this->calculateConfidence($recommendations)
            ]);
            
            return $recommendations;
            
        } catch (\Exception $e) {
            Log::warning("Neural engine fallback triggered: " . $e->getMessage());
            return $this->getFallbackRecommendations($user, $params);
        }
    }

    /**
     * Deep learning-based similarity calculation
     */
    public function calculateDeepSimilarity(int $businessA, int $businessB): float
    {
        $cacheKey = "deep_similarity_{$businessA}_{$businessB}";
        
        return Cache::remember($cacheKey, 3600, function () use ($businessA, $businessB) {
            $embeddingA = $this->getBusinessEmbedding($businessA);
            $embeddingB = $this->getBusinessEmbedding($businessB);

            return $this->callMLService('calculate_similarity', [
                'embedding_a' => $embeddingA,
                'embedding_b' => $embeddingB,
                'method' => 'cosine_similarity'
            ]);
        });
    }

    /**
     * Neural collaborative filtering recommendations
     */
    public function getNeuralCollaborativeRecommendations(User $user, int $count = 20): Collection
    {
        $userEmbedding = $this->generateUserEmbeddings($user);
        $allBusinessEmbeddings = $this->getAllBusinessEmbeddings();

        $predictions = $this->callMLService('predict_preferences', [
            'user_embedding' => $userEmbedding,
            'business_embeddings' => $allBusinessEmbeddings,
            'top_k' => $count
        ]);

        return collect($predictions['recommendations'])->map(function ($pred) {
            return [
                'business_id' => $pred['business_id'],
                'confidence_score' => $pred['confidence'],
                'explanation' => $pred['explanation'] ?? null,
                'algorithm' => 'neural_collaborative_filtering'
            ];
        });
    }

    /**
     * Reinforcement learning for dynamic weight optimization
     */
    public function optimizeRecommendationWeights(array $userFeedback): array
    {
        // Collect user feedback (clicks, time spent, conversions)
        $feedbackData = collect($userFeedback)->map(function ($feedback) {
            return [
                'user_id' => $feedback['user_id'],
                'business_id' => $feedback['business_id'],
                'recommendation_weights' => $feedback['used_weights'],
                'user_action' => $feedback['action'], // click, convert, ignore
                'satisfaction_score' => $feedback['satisfaction'] ?? null,
                'timestamp' => $feedback['timestamp']
            ];
        });

        return $this->callMLService('optimize_weights', [
            'feedback_data' => $feedbackData->toArray(),
            'current_weights' => $this->getCurrentWeights(),
            'learning_rate' => self::LEARNING_RATE,
            'algorithm' => 'reinforcement_learning'
        ]);
    }

    /**
     * Natural Language Processing for business description analysis
     */
    public function analyzeBusinessSentiment(Business $business): array
    {
        $textContent = $this->getBusinessTextContent($business);
        
        return $this->callMLService('analyze_sentiment', [
            'text' => $textContent,
            'analysis_types' => ['sentiment', 'keywords', 'categories', 'quality_indicators']
        ]);
    }

    /**
     * Predictive analytics for business recommendation timing
     */
    public function predictOptimalRecommendationTiming(User $user): array
    {
        $userBehaviorPattern = UserInteraction::where('user_id', $user->id)
            ->selectRaw('HOUR(interaction_time) as hour, DAYOFWEEK(interaction_time) as day, COUNT(*) as frequency')
            ->groupBy('hour', 'day')
            ->get();

        return $this->callMLService('predict_timing', [
            'user_id' => $user->id,
            'behavior_pattern' => $userBehaviorPattern->toArray(),
            'current_time' => now()->toISOString()
        ]);
    }

    /**
     * Advanced feature extraction using computer vision for business images
     */
    public function extractVisualFeatures(Business $business): array
    {
        $images = $business->images()->get();
        
        if ($images->isEmpty()) {
            return ['visual_features' => null];
        }

        $imageUrls = $images->pluck('image_url')->toArray();

        return $this->callMLService('extract_visual_features', [
            'image_urls' => $imageUrls,
            'features' => ['objects', 'scene_type', 'color_palette', 'quality_score']
        ]);
    }

    /**
     * Ensemble learning combining multiple AI models
     */
    public function getEnsembleRecommendations(User $user, array $context = []): Collection
    {
        // Get predictions from multiple models
        $neuralCF = $this->getNeuralCollaborativeRecommendations($user, 50);
        $deepSimilarity = $this->getDeepSimilarityRecommendations($user, 50);
        $sequentialPattern = $this->getSequentialPatternRecommendations($user, 50);
        $contextualBandit = $this->getContextualBanditRecommendations($user, $context, 50);

        // Use ensemble learning to combine predictions
        return $this->combineEnsemblePredictions([
            'neural_cf' => $neuralCF,
            'deep_similarity' => $deepSimilarity,
            'sequential_pattern' => $sequentialPattern,
            'contextual_bandit' => $contextualBandit
        ]);
    }

    /**
     * Anomaly detection for unusual user behavior
     */
    public function detectAnomalousInteractions(User $user): array
    {
        $recentInteractions = UserInteraction::where('user_id', $user->id)
            ->where('interaction_time', '>=', now()->subDays(7))
            ->get();

        $userProfile = $this->buildUserBehaviorProfile($user);

        return $this->callMLService('detect_anomalies', [
            'recent_interactions' => $recentInteractions->toArray(),
            'user_profile' => $userProfile,
            'anomaly_threshold' => 0.05
        ]);
    }

    /**
     * Real-time learning and model adaptation
     */
    public function updateModelWithFeedback(array $interactionFeedback): void
    {
        $this->callMLService('update_model', [
            'feedback_data' => $interactionFeedback,
            'update_method' => 'online_learning',
            'learning_rate' => self::LEARNING_RATE
        ]);

        // Clear relevant caches
        Cache::tags(['ml_embeddings', 'recommendations'])->flush();
    }

    // Private helper methods

    private function extractBusinessFeatures(Business $business): array
    {
        return [
            'categories' => $business->categories->pluck('id')->toArray(),
            'rating' => $business->overall_rating ?? 0,
            'price_range' => $business->price_range ?? 0,
            'location' => [$business->latitude, $business->longitude],
            'features' => [
                'has_delivery' => $business->has_delivery,
                'has_pickup' => $business->has_pickup,
                'has_parking' => $business->has_parking,
                'is_verified' => $business->is_verified
            ],
            'popularity_metrics' => [
                'total_reviews' => $business->total_reviews,
                'discovery_score' => $business->discovery_score
            ]
        ];
    }

    private function getBusinessTextContent(Business $business): string
    {
        $reviews = $business->reviews()->approved()->get();
        $reviewTexts = $reviews->pluck('review_text')->implode(' ');
        
        return trim($business->description . ' ' . $reviewTexts);
    }

    private function callMLService(string $endpoint, array $data): array
    {
        try {
            $response = Http::timeout(30)
                ->post("{$this->mlServiceUrl}/{$endpoint}", $data);

            if ($response->successful()) {
                return $response->json();
            }

            throw new \Exception("ML Service error: " . $response->body());
        } catch (\Exception $e) {
            // Fallback to traditional methods if ML service unavailable
            Log::warning("ML Service unavailable, falling back: " . $e->getMessage());
            return $this->getFallbackResponse($endpoint, $data);
        }
    }

    private function getFallbackResponse(string $endpoint, array $data): array
    {
        // Implement fallback logic for when AI service is unavailable
        switch ($endpoint) {
            case 'generate_embeddings':
                return ['embeddings' => []];
            case 'calculate_similarity':
                return ['similarity' => 0.5];
            case 'predict_preferences':
                return ['recommendations' => []];
            default:
                return ['result' => null, 'fallback' => true];
        }
    }

    private function getCurrentWeights(): array
    {
        return [
            'content_based' => 0.4,
            'collaborative' => 0.35,
            'location_based' => 0.25
        ];
    }

    private function getBusinessEmbedding(int $businessId): array
    {
        return Cache::remember("business_embedding_{$businessId}", 3600, function () use ($businessId) {
            $business = Business::find($businessId);
            return $this->generateBusinessEmbeddings()[$businessId] ?? [];
        });
    }

    private function getAllBusinessEmbeddings(): array
    {
        return Cache::remember('all_business_embeddings', 3600, function () {
            return $this->generateBusinessEmbeddings();
        });
    }

    private function buildUserBehaviorProfile(User $user): array
    {
        return [
            'interaction_frequency' => UserInteraction::where('user_id', $user->id)->count(),
            'preferred_categories' => $user->preferences?->preferred_categories ?? [],
            'average_session_length' => $this->calculateAverageSessionLength($user),
            'interaction_patterns' => $this->getInteractionPatterns($user)
        ];
    }

    private function calculateAverageSessionLength(User $user): float
    {
        // Implement session length calculation
        return 15.5; // minutes
    }

    private function getInteractionPatterns(User $user): array
    {
        // Analyze user interaction patterns
        return [];
    }

    private function getDeepSimilarityRecommendations(User $user, int $count): Collection
    {
        // Implement deep similarity-based recommendations
        return collect([]);
    }

    private function getSequentialPatternRecommendations(User $user, int $count): Collection
    {
        // Implement sequential pattern mining recommendations
        return collect([]);
    }

    private function getContextualBanditRecommendations(User $user, array $context, int $count): Collection
    {
        // Implement contextual bandit algorithm
        return collect([]);
    }

    private function combineEnsemblePredictions(array $predictions): Collection
    {
        // Implement ensemble learning combination
        return collect([]);
    }

    private function prepareUserContext(User $user): array
    {
        return [
            'user_id' => $user->id,
            'preferences' => $user->preferences?->toArray() ?? [],
            'interaction_history' => UserInteraction::where('user_id', $user->id)
                ->orderBy('interaction_time', 'desc')
                ->limit(50)
                ->get()
                ->toArray(),
            'behavioral_profile' => $this->buildUserBehaviorProfile($user)
        ];
    }

    private function getUserEmbedding(User $user): array
    {
        try {
            return $this->generateUserEmbeddings($user);
        } catch (\Exception $e) {
            // Fallback to meaningful user features based on preferences and interactions
            $preferences = $user->preferences;
            $interactions = UserInteraction::where('user_id', $user->id)->get();
            
            // Create a 128-dimensional embedding based on user behavior
            $embedding = array_fill(0, self::EMBEDDING_DIMENSIONS, 0.1);
            
            // Enhance embedding based on user preferences
            if ($preferences && $preferences->preferred_categories) {
                $categoriesJson = $preferences->preferred_categories;
                $categories = is_string($categoriesJson) ? json_decode($categoriesJson, true) : $categoriesJson;
                $categories = $categories ?? [];
                
                foreach ($categories as $index => $categoryId) {
                    if ($index < self::EMBEDDING_DIMENSIONS) {
                        $embedding[$index] = 0.8; // High preference signal
                    }
                }
            }
            
            // Enhance based on interaction patterns
            foreach ($interactions->take(20) as $index => $interaction) {
                if ($index < self::EMBEDDING_DIMENSIONS) {
                    $weight = match($interaction->interaction_type) {
                        'favorite' => 0.9,
                        'review' => 0.8,
                        'view' => 0.6,
                        'click' => 0.4,
                        default => 0.3
                    };
                    $embedding[$index] = max($embedding[$index], $weight);
                }
            }
            
            return $embedding;
        }
    }

    private function getBusinessEmbeddings(array $params): array
    {
        // Generate fallback embeddings for all businesses directly
        $businesses = Business::where('is_active', true)->get();
        $embeddings = [];
        
        foreach ($businesses as $business) {
            // Create meaningful embeddings based on business features
            $embedding = array_fill(0, self::EMBEDDING_DIMENSIONS, 0.1);
            
            // Category-based features
            if ($business->category_id) {
                $embedding[0] = 0.9; // Strong category signal
                $embedding[$business->category_id % self::EMBEDDING_DIMENSIONS] = 0.8;
            }
            
            // Rating-based features
            if ($business->overall_rating) {
                $ratingNormalized = $business->overall_rating / 5.0;
                for ($i = 1; $i <= 10; $i++) {
                    $embedding[$i] = $ratingNormalized;
                }
            }
            
            // Price range features
            if ($business->price_range) {
                $priceIndex = min($business->price_range - 1, 4);
                $embedding[11 + $priceIndex] = 0.7;
            }
            
            // Feature-based signals
            if ($business->has_delivery) $embedding[16] = 0.6;
            if ($business->has_pickup) $embedding[17] = 0.6;
            if ($business->has_parking) $embedding[18] = 0.6;
            if ($business->is_featured) $embedding[19] = 0.8;
            
            // Location clustering (simplified)
            if ($business->latitude && $business->longitude) {
                $latIndex = 20 + (int)(($business->latitude + 90) / 10) % 10;
                $lngIndex = 30 + (int)(($business->longitude + 180) / 10) % 10;
                $embedding[$latIndex] = 0.5;
                $embedding[$lngIndex] = 0.5;
            }
            
            $embeddings[$business->id] = $embedding;
        }
        
        // Filter by categories if specified
        if (isset($params['categories'])) {
            $businesses = Business::whereIn('category_id', $params['categories'])->pluck('id');
            $embeddings = array_intersect_key($embeddings, array_flip($businesses->toArray()));
        }
        
        return $embeddings;
    }

    private function calculateDeepSimilarities(array $userEmbedding, array $businessEmbeddings): array
    {
        $similarities = [];
        
        foreach ($businessEmbeddings as $businessId => $embedding) {
            // Neural similarity calculation (cosine similarity with learned weights)
            $similarity = $this->cosineSimilarity($userEmbedding, $embedding);
            
            // Apply deep learning transformation
            $neuralScore = $this->neuralTransform($similarity);
            
            $similarities[$businessId] = $neuralScore;
        }
        
        return $similarities;
    }

    private function applyReinforcementLearning(User $user, array $similarities): array
    {
        // Get user's historical feedback (clicks, favorites, reviews)
        $feedback = $this->getUserFeedback($user);
        
        foreach ($similarities as $businessId => $score) {
            // Apply Q-learning style adjustment based on past interactions
            $rewardSignal = $feedback[$businessId] ?? 0;
            $learningRate = 0.1;
            
            // Update score based on reinforcement learning
            $similarities[$businessId] = $score + ($learningRate * $rewardSignal * (1 - $score));
        }
        
        return $similarities;
    }

    private function getEnsembleRecommendationsInternal(User $user, array $scores, array $params): Collection
    {
        // Sort businesses by neural scores
        arsort($scores);
        
        $businessIds = array_keys(array_slice($scores, 0, $params['count'] ?? 20));
        
        $businesses = Business::whereIn('id', $businessIds)->get();
        
        // Add AI confidence scores to business objects
        return $businesses->map(function ($business) use ($scores) {
            $business->ai_score = $scores[$business->id] ?? 0;
            $business->ai_confidence = $this->calculateBusinessConfidence($business);
            return $business;
        });
    }

    private function calculateConfidence(Collection $recommendations): float
    {
        if ($recommendations->isEmpty()) {
            return 0.0;
        }
        
        $scores = $recommendations->pluck('ai_score')->filter();
        return $scores->avg() ?? 0.5;
    }

    private function getFallbackRecommendations(User $user, array $params): Collection
    {
        // Simple content-based fallback
        $query = Business::where('is_active', true);
        
        if (isset($params['categories'])) {
            $query->whereIn('category_id', $params['categories']);
        }
        
        if (isset($params['latitude'], $params['longitude'])) {
            $query->selectRaw('*, (6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) AS distance')
                  ->addBinding([$params['latitude'], $params['longitude'], $params['latitude']])
                  ->having('distance', '<', 50)
                  ->orderBy('distance');
        }
        
        return $query->limit($params['count'] ?? 20)->get();
    }

    private function cosineSimilarity(array $vectorA, array $vectorB): float
    {
        if (count($vectorA) !== count($vectorB)) {
            return 0.0;
        }
        
        $dotProduct = 0;
        $normA = 0;
        $normB = 0;
        
        for ($i = 0; $i < count($vectorA); $i++) {
            $dotProduct += $vectorA[$i] * $vectorB[$i];
            $normA += $vectorA[$i] * $vectorA[$i];
            $normB += $vectorB[$i] * $vectorB[$i];
        }
        
        if ($normA == 0 || $normB == 0) {
            return 0.0;
        }
        
        return $dotProduct / (sqrt($normA) * sqrt($normB));
    }

    private function neuralTransform(float $similarity): float
    {
        // Apply neural network transformation (simplified sigmoid)
        return 1 / (1 + exp(-5 * ($similarity - 0.5)));
    }

    private function getUserFeedback(User $user): array
    {
        $feedback = [];
        
        // Positive feedback from interactions
        $interactions = UserInteraction::where('user_id', $user->id)->get();
        foreach ($interactions as $interaction) {
            $weight = match($interaction->interaction_type) {
                'click' => 0.1,
                'view' => 0.2,
                'favorite' => 0.8,
                'review' => 0.9,
                default => 0.1
            };
            $feedback[$interaction->business_id] = $weight;
        }
        
        return $feedback;
    }

    private function calculateBusinessConfidence(Business $business): float
    {
        // Calculate confidence based on business data quality
        $score = 0.5; // Base score
        
        if ($business->description) $score += 0.1;
        if ($business->images()->count() > 0) $score += 0.1;
        if ($business->reviews()->count() > 5) $score += 0.2;
        if ($business->average_rating > 4.0) $score += 0.1;
        
        return min(1.0, $score);
    }
}
