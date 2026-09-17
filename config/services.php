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

    'turnstile' => [
        'key' => env('TURNSTILE_SITE_KEY', '1x00000000000000000000AA'),
        'secret' => env('TURNSTILE_SECRET_KEY', '0x00000000000000000000AA'),
    ],

    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY'),
    ],

    'openai' => [
        'key' => env('OPENAI_API_KEY'),
    ],

    'rsvg' => [
        'bin' => env('RSVG_CONVERT_BIN', 'rsvg-convert'),
        'timeout' => env('RSVG_CONVERT_TIMEOUT', 30),
    ],

    /*
     * The hub FrankenPHP serves at /.well-known/mercure, which tells a wall and
     * a remote that the show has moved so they stop asking every second. Off
     * until all three are set: without it the devices simply keep polling.
     *
     * The publish URL is the hub as the server reaches it, not as a browser
     * does: localhost inside the app container, the app service's name from the
     * queue container beside it. The keys must match the ones the Caddyfile
     * hands the hub.
     */
    'mercure' => [
        'publish_url' => env('MERCURE_PUBLISH_URL'),
        'publisher_jwt_key' => env('MERCURE_PUBLISHER_JWT_KEY'),
        'subscriber_jwt_key' => env('MERCURE_SUBSCRIBER_JWT_KEY'),
    ],

    'musescore' => [
        'bin' => env('MUSESCORE_BIN', 'mscore-render'),
        'timeout' => env('MUSESCORE_TIMEOUT', 180),
    ],

    'pdftoppm' => [
        'bin' => env('PDFTOPPM_BIN', 'pdftoppm'),
        'timeout' => env('PDFTOPPM_TIMEOUT', 180),
    ],

    'pdftocairo' => [
        'bin' => env('PDFTOCAIRO_BIN', 'pdftocairo'),
        'timeout' => env('PDFTOCAIRO_TIMEOUT', 180),
    ],

    'szentiras' => [
        'base_url' => env('SZENTIRAS_EU_API_URL', 'https://szentiras.eu/api'),
        'key' => env('SZENTIRAS_EU_API_KEY'),
    ],

];
