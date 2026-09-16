<?php

namespace App\Console\Commands;

use App\Models\BitcoinSettlement;
use App\Services\BitcoinEscrowService;
use Illuminate\Console\Command;
use Throwable;

class ReconcileBitcoinPayouts extends Command
{
    protected $signature = 'bitcoin:reconcile-payouts {--limit=25 : Maximum settlements to process}';

    protected $description = 'Retry and reconcile pending Bitcoin seller payouts and refunds with BTCPay Server.';

    public function handle(BitcoinEscrowService $escrow): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $settlements = BitcoinSettlement::query()
            ->whereIn('status', ['pending', 'failed', 'awaiting_approval', 'awaiting_payment', 'in_progress'])
            ->whereIn('type', ['seller_payout', 'buyer_refund'])
            ->oldest('updated_at')
            ->limit($limit)
            ->get();

        foreach ($settlements as $settlement) {
            try {
                if ($settlement->type === 'buyer_refund' && ! $settlement->destination_address) {
                    $this->warn("#{$settlement->id}: refund destination is missing; skipped.");

                    continue;
                }
                $settlement = $escrow->syncSettlement($settlement);
                if ($settlement->status === 'awaiting_approval') {
                    $settlement = $escrow->approveSettlement($settlement);
                }
                $this->info("#{$settlement->id}: {$settlement->status}");
            } catch (Throwable $e) {
                report($e);
                $settlement->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
                $this->error("#{$settlement->id}: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
