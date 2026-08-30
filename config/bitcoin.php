<?php

return [
    'currency' => 'BTC',
    'btcpay_url' => env('BTCPAY_URL'),
    'btcpay_store_id' => env('BTCPAY_STORE_ID'),
    'btcpay_api_key' => env('BTCPAY_API_KEY'),
    'webhook_secret' => env('BTCPAY_WEBHOOK_SECRET'),
    'required_confirmations' => (int) env('BITCOIN_REQUIRED_CONFIRMATIONS', 1),
];
