<?php

return [
    // Kosher Market charges 3% of every completed sale. Keep this as a decimal
    // string so fee calculations can be performed exactly in satoshis.
    'platform_fee_percent' => env('ESCROW_PLATFORM_FEE_PERCENT', '3.00'),
    'hold_days' => (int) env('ESCROW_HOLD_DAYS', 3),
    // Automatic release must be explicitly enabled after the operational
    // payment, dispute and recovery runbook has been tested.
    'auto_release' => filter_var(env('ESCROW_AUTO_RELEASE', false), FILTER_VALIDATE_BOOLEAN),
];
