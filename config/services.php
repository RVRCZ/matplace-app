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

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', '/auth/google/callback'),
    ],

    'facebook' => [
        'client_id' => env('FACEBOOK_CLIENT_ID'),
        'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
        'redirect' => env('FACEBOOK_REDIRECT_URI', '/auth/facebook/callback'),
    ],

    // Measurement, loaded in the browser only after the visitor agreed in the cookie bar (App\Support\Consent)
    'ga4' => ['id' => env('GA_MEASUREMENT_ID')],
    'meta' => ['pixel_id' => env('META_PIXEL_ID')],

    // Packeta (Zásilkovna): the API key also opens the pickup point picker in the browser, the password is server-side only
    'packeta' => [
        'api_key' => env('PACKETA_API_KEY'),
        'api_password' => env('PACKETA_API_PASS'),
        'eshop' => env('PACKETA_ESHOP'),
        'endpoint' => env('PACKETA_API_URL', 'https://www.zasilkovna.cz/api/rest'),
        // list of carriers (home delivery and partners' pickup points); {key} = the API key
        'carriers_url' => env('PACKETA_CARRIERS_URL', 'https://pickup-point.api.packeta.com/v5/{key}/carrier/json?lang=en'),
        'validate_url' => env('PACKETA_VALIDATE_URL', 'https://widget.packeta.com/v6/pps/api/widget/v1/validate'),
        'tracking_url' => 'https://tracking.packeta.com/{locale}/?id={barcode}',
        'timeout' => 20,
    ],

];
