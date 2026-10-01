<?php

namespace App\Services;

use App\Models\EscrowTransaction;
use RuntimeException;

/**
 * Retained only for backwards-compatible container wiring. Marketplace payouts
 * are intentionally manual and this service must never submit a payout.
 */
class AutomaticBitcoinPayoutService
{
    public function __construct(private BitcoinEscrowService $escrow) {}

    public function forReleasedEscrow(EscrowTransaction $escrow): never
    {
        throw new RuntimeException('Automatic Bitcoin payouts are disabled. Submit the settlement manually from the administrator settlement interface.');
    }
}
