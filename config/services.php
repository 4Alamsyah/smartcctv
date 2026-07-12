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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // Cognitive reasoning: CRM text -> structured JSON, daily executive summaries.
    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
        'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com'),
        'model' => env('GEMINI_MODEL', 'gemini-2.5-flash'),
    ],

    // Ultra-low-latency fallback ANPR OCR (called by the Python edge worker,
    // documented here since Laravel also reads GROQ_* for the /docs config page).
    'groq' => [
        'key' => env('GROQ_API_KEY'),
        'base_url' => env('GROQ_BASE_URL', 'https://api.groq.com/openai/v1'),
        'model' => env('GROQ_VISION_MODEL', 'llama-3.2-90b-vision-preview'),
    ],

    // Flask microservice (traffic-monitor-service/) serving the Traffic
    // Monitor dashboard, reverse-proxied by TrafficMonitorProxyController.
    // Must stay bound to 127.0.0.1 on the Flask side -- it has no auth of
    // its own, this proxy is the only intended caller.
    'traffic_monitor' => [
        'url' => env('TRAFFIC_MONITOR_SERVICE_URL', 'http://127.0.0.1:5001'),
    ],

];
