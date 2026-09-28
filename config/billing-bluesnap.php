<?php

declare(strict_types=1);

return [
    'driver' => 'bluesnap',
    'username' => env('BLUESNAP_USERNAME'),
    'password' => env('BLUESNAP_PASSWORD'),
    'merchant_id' => env('BLUESNAP_MERCHANT_ID'),
    'environment' => env('BLUESNAP_ENVIRONMENT', 'sandbox'),
    'checkout_host' => env('BLUESNAP_CHECKOUT_HOST'),
    'api_version' => env('BLUESNAP_API_VERSION', '3.0'),
    'currency' => env('BLUESNAP_CURRENCY', 'USD'),
    'webhook' => [
        'secret' => env('BLUESNAP_WEBHOOK_SECRET'),
        'timestamp_tolerance' => (int) env('BLUESNAP_WEBHOOK_TIMESTAMP_TOLERANCE', 300),
        'verify_signature' => (bool) env('BLUESNAP_WEBHOOK_VERIFY_SIGNATURE', true),
        'verify_ip' => (bool) env('BLUESNAP_WEBHOOK_VERIFY_IP', false),
        'allowed_ips' => [],
    ],
];
