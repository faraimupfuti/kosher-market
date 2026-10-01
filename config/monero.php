<?php

return [
    'currency' => 'XMR',
    'shkeeper_url' => rtrim((string) env('SHKEEPER_URL'), '/'),
    'shkeeper_api_key' => env('SHKEEPER_API_KEY'),
    'shkeeper_username' => env('SHKEEPER_USERNAME'),
    'shkeeper_password' => env('SHKEEPER_PASSWORD'),
    'callback_url' => env('SHKEEPER_CALLBACK_URL'),
    'fiat_currency' => env('SHKEEPER_FIAT', 'USD'),
    'required_confirmations' => max(1, (int) env('MONERO_REQUIRED_CONFIRMATIONS', 10)),
    'webhook_tolerance_seconds' => max(30, (int) env('SHKEEPER_WEBHOOK_TOLERANCE_SECONDS', 300)),
    'payout_priority' => (string) env('SHKEEPER_XMR_PAYOUT_PRIORITY', '2'),
    'vendor_registration_usd' => env('VENDOR_REGISTRATION_FEE_USD', '200.00'),
    'platform_fee_percent' => env('XMR_PLATFORM_FEE_PERCENT', '3.00'),
    'auto_payouts' => false,
];
