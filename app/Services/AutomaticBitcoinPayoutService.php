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
        if (! config('bitcoin.auto_payouts', true)) {
            throw new RuntimeException('Automatic Bitcoin payouts are disabled.');
        }

        $vendor = $escrow->order?->vendor;
        if (! $vendor) {
            throw new RuntimeException('Seller payout cannot proceed: vendor was not found.');
        }

        if (! $vendor->bitcoin_payout_address || ! $vendor->bitcoin_payout_address_verified_at) {
            throw new RuntimeException('Seller payout cannot proceed until the vendor has a verified Bitcoin payout address.');
        }

        $settlement = BitcoinSettlement::where('escrow_transaction_id', $escrow->id)
            ->where('type', 'seller_payout')
            ->firstOrFail();

        if ($settlement->status === 'completed') {
            return $settlement;
        }

        // Never trust a stale destination stored on a settlement if the vendor has
        // subsequently changed their payout address. A changed address must be
        // re-verified before another automatic payout can be submitted.
        if ($settlement->destination_address !== $vendor->bitcoin_payout_address) {
            throw new RuntimeException('Seller payout address changed or no longer matches the verified vendor address. Payout paused pending verification.');
        }

        $settlement = $settlement->fresh();
        if (! $settlement->btcpay_payout_id) {
            $settlement = $this->escrow->submitSettlement($settlement);
        }

        if ($settlement->status === 'awaiting_approval') {
            $settlement = $this->escrow->approveSettlement($settlement);
        }

        return $settlement->fresh();
    }
}
