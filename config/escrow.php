<?php

return [
    // Keep this as a decimal string so fee calculations can be performed in satoshis.
    'platform_fee_percent' => env('ESCROW_PLATFORM_FEE_PERCENT', '2.5'),
    'hold_days' => (int) env('ESCROW_HOLD_DAYS', 3),
    'auto_release' => filter_var(env('ESCROW_AUTO_RELEASE', true), FILTER_VALIDATE_BOOLEAN),
];
