<?php

return [
    'currency' => 'BTC',
    'btcpay_url' => env('BTCPAY_URL'),
    'btcpay_store_id' => env('BTCPAY_STORE_ID'),
    'btcpay_api_key' => env('BTCPAY_API_KEY'),
    'webhook_secret' => env('BTCPAY_WEBHOOK_SECRET'),
    'required_confirmations' => (int) env('BITCOIN_REQUIRED_CONFIRMATIONS', 1),
    'vendor_registration_usd' => '200.00',
    'platform_fee_percent' => env('BITCOIN_PLATFORM_FEE_PERCENT', '3.00'),
    'admin_btc_address' => env('KOSHER_MARKET_ADMIN_BTC_ADDRESS'),
    'btc_invoice_currency' => env('BTCPAY_INVOICE_CURRENCY', 'BTC'),
    'auto_payouts' => filter_var(env('BITCOIN_AUTO_PAYOUTS', false), FILTER_VALIDATE_BOOL),
];
