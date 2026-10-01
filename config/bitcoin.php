<?php

// Legacy config key retained for backwards compatibility with existing services.
// Kosher Market V4 is Monero-first: XMR is the only marketplace currency.
return [
    'currency' => 'XMR',
    'shkeeper_url' => rtrim((string) env('SHKEEPER_URL'), '/'),
    'shkeeper_api_key' => env('SHKEEPER_API_KEY'),
    'shkeeper_username' => env('SHKEEPER_USERNAME'),
    'shkeeper_password' => env('SHKEEPER_PASSWORD'),
    'callback_url' => env('SHKEEPER_CALLBACK_URL'),
    'fiat_currency' => env('SHKEEPER_FIAT', 'USD'),
    'required_confirmations' => (int) env('MONERO_REQUIRED_CONFIRMATIONS', 1),
    'webhook_tolerance_seconds' => (int) env('SHKEEPER_WEBHOOK_TOLERANCE_SECONDS', 300),
    'payout_fee' => env('SHKEEPER_XMR_PAYOUT_PRIORITY', '2'),
    'vendor_registration_usd' => '200.00',
    'platform_fee_percent' => env('MONERO_PLATFORM_FEE_PERCENT', '3.00'),
    'admin_xmr_address' => env('KOSHER_MARKET_ADMIN_XMR_ADDRESS'),
    'auto_payouts' => false,
];
