<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Search Engine
    |--------------------------------------------------------------------------
    |
    | Default to `null` so a fresh checkout never crashes on missing
    | infrastructure. Production should set SCOUT_DRIVER=meilisearch.
    |
    */

    'driver' => env('SCOUT_DRIVER', 'null'),

    /*
    |--------------------------------------------------------------------------
    | Index Prefix
    |--------------------------------------------------------------------------
    |
    | Lets multiple environments share one Meilisearch instance without
    | colliding (e.g. `staging_businesses` vs `prod_businesses`).
    |
    */

    'prefix' => env('SCOUT_PREFIX', ''),

    /*
    |--------------------------------------------------------------------------
    | Queue Data Syncing
    |--------------------------------------------------------------------------
    |
    | Indexing happens off the request thread on the dedicated `low` queue
    | so high-traffic writes never block on Meilisearch.
    |
    */

    'queue' => [
        'connection' => env('SCOUT_QUEUE_CONNECTION', 'redis'),
        'queue' => env('SCOUT_QUEUE_NAME', 'low'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Transactions
    |--------------------------------------------------------------------------
    |
    | Wait for the wrapping transaction to commit before pushing to the
    | search engine — avoids indexing a row that was rolled back.
    |
    */

    'after_commit' => true,

    /*
    |--------------------------------------------------------------------------
    | Chunk Sizes
    |--------------------------------------------------------------------------
    */

    'chunk' => [
        'searchable' => 1000,
        'unsearchable' => 1000,
    ],

    'soft_delete' => false,

    'identify' => env('SCOUT_IDENTIFY', false),

    /*
    |--------------------------------------------------------------------------
    | Meilisearch
    |--------------------------------------------------------------------------
    |
    | One index per Eloquent model. `index-settings` is keyed by Scout's
    | `searchableAs()` (the index name), not the model class. The settings
    | are applied automatically by `php artisan scout:sync-index-settings`.
    |
    | filterableAttributes: columns the SDK can pass in `filter`.
    | sortableAttributes:   columns the SDK can pass in `sort`.
    |
    */

    'meilisearch' => [
        'host' => env('MEILISEARCH_HOST', 'http://localhost:7700'),
        'key' => env('MEILISEARCH_KEY'),
        'index-settings' => [
            'businesses' => [
                'filterableAttributes' => [
                    'id', 'category_id', 'subcategory_id', 'is_active', 'is_verified',
                    'is_national', 'is_featured', 'price_range', 'city', 'area',
                    'business_model', 'has_delivery', 'has_pickup', 'has_parking',
                    '_geo',
                ],
                'sortableAttributes' => [
                    'id', 'overall_rating', 'total_reviews', 'created_at', '_geo',
                ],
                'searchableAttributes' => [
                    'business_name', 'description', 'city', 'area', 'full_address',
                    'tags',
                ],
                'rankingRules' => [
                    'words', 'typo', 'proximity', 'attribute', 'sort', 'exactness',
                    'overall_rating:desc', 'total_reviews:desc',
                ],
            ],

            'attractions' => [
                'filterableAttributes' => [
                    'id', 'category', 'type', 'is_active', 'is_verified', 'is_featured',
                    'is_free', 'city', 'area', '_geo',
                ],
                'sortableAttributes' => [
                    'id', 'overall_rating', 'total_reviews', 'created_at', '_geo',
                ],
                'searchableAttributes' => [
                    'name', 'description', 'city', 'area', 'address', 'category', 'subcategory',
                ],
                'rankingRules' => [
                    'words', 'typo', 'proximity', 'attribute', 'sort', 'exactness',
                    'overall_rating:desc',
                ],
            ],

            'business_offerings' => [
                'filterableAttributes' => [
                    'id', 'business_id', 'category_id', 'is_active', 'price',
                ],
                'sortableAttributes' => [
                    'id', 'price', 'average_rating', 'total_reviews', 'created_at',
                ],
                'searchableAttributes' => [
                    'name', 'description',
                ],
            ],

            'user_collections' => [
                'filterableAttributes' => [
                    'id', 'user_id', 'is_public',
                ],
                'sortableAttributes' => [
                    'id', 'created_at',
                ],
                'searchableAttributes' => [
                    'name', 'description',
                ],
            ],
        ],
    ],

];
