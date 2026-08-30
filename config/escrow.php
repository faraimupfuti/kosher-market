<?php

return [
    'platform_fee_percent' => (float) env('ESCROW_PLATFORM_FEE_PERCENT', 2.5),
    'hold_days' => (int) env('ESCROW_HOLD_DAYS', 3),
];
