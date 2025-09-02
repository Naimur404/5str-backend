<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Recommendation Settings
    |--------------------------------------------------------------------------
    |
    | Simple configuration for recommendation behavior without A/B testing
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
];
