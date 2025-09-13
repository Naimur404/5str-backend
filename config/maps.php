<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Maps Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for various map services
    |
    */

    'google' => [
        'api_key' => env('GOOGLE_MAPS_API_KEY', null),
    ],

    'mapbox' => [
        'access_token' => env('MAPBOX_ACCESS_TOKEN', null),
        'style' => env('MAPBOX_STYLE', 'mapbox://styles/mapbox/streets-v11'),
    ],

    'openstreetmap' => [
        'tile_server' => env('OSM_TILE_SERVER', 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png'),
        'attribution' => '© OpenStreetMap contributors',
    ],
];