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
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    /*
    |--------------------------------------------------------------------------
    | NStores (branch locations scraping)
    |--------------------------------------------------------------------------
    |
    | Used to scrape branch pages from the NStores link and extract Google Maps
    | latitude/longitude. The import endpoint is protected by a simple key.
    |
    */
    'nstores' => [
        'source_url' => env('NSTORES_SOURCE_URL', 'https://bit.ly/m/NStores'),
        'import_key' => env('NSTORES_IMPORT_KEY'),
        'timeout' => (int) env('NSTORES_HTTP_TIMEOUT', 25),
        'verify_ssl' => (bool) env('NSTORES_HTTP_VERIFY_SSL', true),
        'user_agent' => env('NSTORES_HTTP_USER_AGENT', 'ElnasserBackendDash/1.0 (+nstores scraper)'),
    ],

];
