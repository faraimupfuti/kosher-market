<?php

namespace App\Services;

use App\Models\BitcoinSettlement;
use App\Models\EscrowTransaction;
use RuntimeException;

class AutomaticBitcoinPayoutService
{
    public function __construct(private BitcoinEscrowService $escrow) {}

    /**
     * Submit and approve the seller payout after an escrow has been released.
     * BTCPay remains the wallet/custody layer; Kosher Market never stores private keys.
     */
    public function forReleasedEscrow(EscrowTransaction $escrow): BitcoinSettlement
    {
        if (!config('bitcoin.auto_payouts', true)) {
            throw new RuntimeException('Automatic Bitcoin payouts are disabled.');
        }

        $settlement = BitcoinSettlement::where('escrow_transaction_id', $escrow->id)
            ->where('type', 'seller_payout')
            ->firstOrFail();

        if ($settlement->status === 'completed') return $settlement;

        $settlement = $settlement->fresh();
        if (!$settlement->btcpay_payout_id) {
            $settlement = $this->escrow->submitSettlement($settlement);
        }

        if ($settlement->status === 'awaiting_approval') {
            $settlement = $this->escrow->approveSettlement($settlement);
        }

        return $settlement->fresh();
    }
}
