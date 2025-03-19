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

    // AI APIs
    'deepseek' => [
        'api_key' => env('DEEPSEEK_API_KEY'),
        'base_url' => 'https://api.deepseek.com',
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'base_url' => 'https://api.openai.com',
    ],

    'prompt' => [
        'slide_count' => env('SLIDE_COUNT', default: 8),
        'photo_model' => env('PHOTO_MODEL', 'dall-e-3'),
        'photo_size' => env('PHOTO_SIZE', '1024x1024'),
    ],

];
