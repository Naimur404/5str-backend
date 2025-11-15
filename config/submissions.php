<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Submission Points Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains the configuration for points awarded when user
    | submissions are approved by administrators.
    |
    */

    'points' => [
        /*
         * Points awarded for approved business submissions
         */
        'business' => 25,

        /*
         * Points awarded for approved attraction submissions
         */
        'attraction' => 20,

        /*
         * Points awarded for approved offering submissions
         */
        'offering' => 15,
    ],

    /*
    |--------------------------------------------------------------------------
    | Bonus Points Configuration
    |--------------------------------------------------------------------------
    |
    | Additional points that can be earned based on submission quality
    |
    */
    'bonus' => [
        /*
         * Bonus points for detailed descriptions (>100 characters)
         */
        'detailed_description' => 5,

        /*
         * Bonus points per image (max 10 total)
         */
        'per_image' => 2,
        'max_image_bonus' => 10,

        /*
         * Bonus points for providing additional information
         */
        'additional_info' => 3,

        /*
         * Bonus points for business submissions with complete contact info
         */
        'complete_contact_info' => 5,
    ],
];