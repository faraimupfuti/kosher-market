<?php

namespace App\Services;

use App\Models\EscrowTransaction;
use App\Models\EscrowLedgerEntry;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class BitcoinPayoutService
{
    /**
     * Queue a payout without ever storing a private key in Velstore.
     * An external wallet/BTCPay payout worker must execute entries of type seller_payout_pending.
     */
    public function queueSellerPayout(EscrowTransaction $escrow, string $destination): EscrowLedgerEntry
    {
        if ($escrow->status !== 'released') throw new RuntimeException('Escrow must be released before payout.');
        if (!preg_match('/^(bc1|[13])[a-zA-HJ-NP-Z0-9]{20,}$/', trim($destination))) {
            throw new RuntimeException('Invalid Bitcoin payout address.');
        }

        return DB::transaction(function () use ($escrow, $destination) {
            $existing = $escrow->ledgerEntries()->where('type', 'seller_payout_pending')->first();
            if ($existing) return $existing;

            return EscrowLedgerEntry::create([
                'escrow_transaction_id' => $escrow->id,
                'user_id' => $escrow->vendor_id,
                'type' => 'seller_payout_pending',
                'amount' => $escrow->seller_amount,
                'currency' => 'BTC',
                'reference' => 'PAYOUT-'.$escrow->id,
                'metadata' => ['destination' => trim($destination)],
            ]);
        });
    }
}
