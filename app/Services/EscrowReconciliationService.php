<?php

namespace App\Services;

use App\Models\EscrowTransaction;
use App\Models\PlatformRevenueEntry;
use Illuminate\Support\Collection;

class EscrowReconciliationService
{
    public function audit(): array
    {
        $escrows = EscrowTransaction::with(['ledgerEntries', 'settlements'])->get();
        $issues = new Collection;

        foreach ($escrows as $escrow) {
            $hasFunding = $escrow->ledgerEntries->contains('type', 'escrow_funded');
            $hasSellerDue = $escrow->ledgerEntries->contains('type', 'seller_payout_due');
            $hasRefundDue = $escrow->ledgerEntries->contains('type', 'buyer_refund_due');
            $hasSellerSettlement = $escrow->settlements->contains('type', 'seller_payout');
            $hasRefundSettlement = $escrow->settlements->contains('type', 'buyer_refund');

            if (in_array($escrow->status, ['funded', 'disputed', 'released', 'paid', 'refund_pending', 'refunded'], true) && ! $hasFunding) {
                $issues->push($this->issue($escrow, 'missing_funding_ledger'));
            }
            if (in_array($escrow->status, ['released', 'paid'], true) && (! $hasSellerDue || ! $hasSellerSettlement)) {
                $issues->push($this->issue($escrow, 'missing_seller_settlement'));
            }
            if (in_array($escrow->status, ['refund_pending', 'refunded'], true) && (! $hasRefundDue || ! $hasRefundSettlement)) {
                $issues->push($this->issue($escrow, 'missing_refund_settlement'));
            }
            if (in_array($escrow->status, ['released', 'paid'], true)) {
                $revenue = PlatformRevenueEntry::where('escrow_transaction_id', $escrow->id)->where('status', 'earned')->first();
                if (! $revenue) {
                    $issues->push($this->issue($escrow, 'missing_platform_revenue'));
                }
            }
        }

        return [
            'escrows_checked' => $escrows->count(),
            'issues' => $issues->values()->all(),
            'issue_count' => $issues->count(),
            'healthy' => $issues->isEmpty(),
        ];
    }

    private function issue(EscrowTransaction $escrow, string $code): array
    {
        return ['escrow_id' => $escrow->id, 'order_id' => $escrow->order_id, 'status' => $escrow->status, 'code' => $code];
    }
}
