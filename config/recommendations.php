<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Recommendation Settings
    |--------------------------------------------------------------------------
    |
    | Tunables for the recommendation pipeline. Anything that product
    | might want to A/B test belongs here — never hardcode magic
    | numbers in services.
    |
    */

    // Default personalization level: 'none', 'light', or 'full'
    'personalization_level' => env('RECOMMENDATION_PERSONALIZATION', 'light'),

    // Default number of recommendations
    'default_count' => 10,

    // Cache TTL for recommendations (in minutes)
    'cache_ttl' => 60,

    // Enable/disable recommendation logging for analytics
    'enable_analytics' => env('RECOMMENDATION_ANALYTICS', true),

    /*
    |--------------------------------------------------------------------------
    | Freshness Pass
    |--------------------------------------------------------------------------
    | Small additive deltas applied to every candidate's score
    | before ranking. The defaults are tuned to act as a tie-breaker
    | not as the dominant signal — relevance still wins, but among
    | otherwise-equal businesses the one with a fresh review or a
    | live offer comes out on top.
    */
    'freshness' => [
        'recent_review_boost' => env('REC_FRESH_RECENT_REVIEW_BOOST', 0.05),
        'active_offer_boost' => env('REC_FRESH_ACTIVE_OFFER_BOOST', 0.10),
        'stale_penalty' => env('REC_FRESH_STALE_PENALTY', 0.15),
        'recent_window_days' => env('REC_FRESH_RECENT_DAYS', 30),
        'stale_threshold_days' => env('REC_FRESH_STALE_DAYS', 90),
    ],

    /*
    |--------------------------------------------------------------------------
    | Diversification (MMR)
    |--------------------------------------------------------------------------
    | λ (lambda) is the relevance/diversity trade-off knob:
    |   1.0 → pure relevance (no diversification)
    |   0.5 → half relevance, half diversity
    |   0.0 → pure diversity (rarely useful)
    |
    | The duplicate-penalty values are how strongly the second item's
    | MMR is penalised when it shares a brand / category / area with
    | something already picked. 1.0 means "almost never adjacent".
    */
    'diversity' => [
        'lambda' => env('REC_DIVERSITY_LAMBDA', 0.7),
        'brand_duplicate_penalty' => env('REC_DIVERSITY_BRAND', 1.0),
        'category_duplicate_penalty' => env('REC_DIVERSITY_CATEGORY', 0.5),
        'area_duplicate_penalty' => env('REC_DIVERSITY_AREA', 0.25),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cold-Start Ladder
    |--------------------------------------------------------------------------
    | When a user has fewer than `min_signal_events` events in the
    | last `lookback_days`, we fall through a documented ladder of
    | fallbacks instead of trying to personalise from noise.
    | The frontend reads `algorithm` from the response so it can
    | render different copy ("popular near you" vs "for you").
    */
    'cold_start' => [
        'min_signal_events' => env('REC_COLDSTART_MIN_EVENTS', 3),
        'lookback_days' => env('REC_COLDSTART_LOOKBACK_DAYS', 14),
    ],
];
