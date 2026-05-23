<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'expo' => [
        'access_token' => env('EXPO_ACCESS_TOKEN'),
        'push_url' => env('EXPO_PUSH_URL', 'https://exp.host/--/api/v2/push/send'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URL'),
    ],

    /*
    | OpenAI — used by the menu-OCR pipeline to parse a photographed
    | menu into structured offering rows. We use GPT-4o vision because
    | it handles mixed Bangla / English text, multi-column menus, and
    | hand-written prices in one call (Vision + LLM chained gives us
    | worse results on Bangla-heavy menus).
    |
    | Required envs in production:
    |   OPENAI_API_KEY      sk-... (rotate via Coolify when needed)
    |   OPENAI_VISION_MODEL gpt-4o (default; can be set to gpt-4o-mini
    |                       for cost-sensitive deployments)
    */
    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        'vision_model' => env('OPENAI_VISION_MODEL', 'gpt-4o'),
        'embedding_model' => env('OPENAI_EMBEDDING_MODEL', 'text-embedding-3-small'),
        'request_timeout' => (int) env('OPENAI_REQUEST_TIMEOUT', 60),
    ],

];
