<?php

return [
    'currency' => 'BTC',
    'shkeeper_url' => rtrim((string) env('SHKEEPER_URL'), '/'),
    'shkeeper_api_key' => env('SHKEEPER_API_KEY'),
    'shkeeper_username' => env('SHKEEPER_USERNAME'),
    'shkeeper_password' => env('SHKEEPER_PASSWORD'),
    'callback_url' => env('SHKEEPER_CALLBACK_URL'),
    'fiat_currency' => env('SHKEEPER_FIAT', 'USD'),
    'required_confirmations' => (int) env('BITCOIN_REQUIRED_CONFIRMATIONS', 1),
    'webhook_tolerance_seconds' => (int) env('SHKEEPER_WEBHOOK_TOLERANCE_SECONDS', 300),
    'payout_fee' => env('SHKEEPER_BTC_PAYOUT_FEE', '5'),
    'vendor_registration_usd' => '200.00',
    'platform_fee_percent' => env('BITCOIN_PLATFORM_FEE_PERCENT', '3.00'),
    'admin_btc_address' => env('KOSHER_MARKET_ADMIN_BTC_ADDRESS'),
    'auto_payouts' => false,
];
