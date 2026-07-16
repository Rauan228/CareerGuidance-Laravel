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

    'openai' => [
        'key' => env('OPENAI_API_KEY'),
    ],

    // Ollama на VPS — через отдельный nginx-прокси с Basic Auth (порт 11435)
    'ollama' => [
        'url' => env('OLLAMA_URL', 'http://178.88.115.213:11435'),
        'model' => env('OLLAMA_MODEL', 'qwen2.5:7b-instruct'),
        'user' => env('OLLAMA_USER', 'torap'),
        'password' => env('OLLAMA_PASSWORD'),
        // сколько секунд ждём генерацию (сервер общий и медленный — ждём долго)
        'timeout' => (int) env('OLLAMA_TIMEOUT', 1740),
    ],

];
