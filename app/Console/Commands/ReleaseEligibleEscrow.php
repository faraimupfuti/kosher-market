<?php

namespace App\Console\Commands;

use App\Models\EscrowTransaction;
use App\Services\BitcoinEscrowService;
use Illuminate\Console\Command;
use Throwable;

class ReleaseEligibleEscrow extends Command
{
    protected $signature = 'escrow:release-eligible {--dry-run : Show eligible escrow without changing status}';
    protected $description = 'Release funded Bitcoin escrow transactions whose holding period has expired';

    public function handle(BitcoinEscrowService $service): int
    {
        if (!config('escrow.auto_release', true) && !$this->option('dry-run')) {
            $this->warn('Automatic escrow release is disabled by ESCROW_AUTO_RELEASE.');
            return self::SUCCESS;
        }

        $query = EscrowTransaction::where('status', 'funded')
            ->whereNotNull('release_due_at')
            ->where('release_due_at', '<=', now());

        $count = 0;
        $query->chunkById(100, function ($escrows) use ($service, &$count) {
            foreach ($escrows as $escrow) {
                if ($this->option('dry-run')) {
                    $this->line("Eligible escrow #{$escrow->id}: {$escrow->seller_amount} BTC");
                    $count++;
                    continue;
                }

                try {
                    $service->release($escrow, 'Automatic release after escrow holding period.');
                    $this->info("Released escrow #{$escrow->id}; Bitcoin payout queued.");
                    $count++;
                } catch (Throwable $e) {
                    $this->error("Escrow #{$escrow->id}: {$e->getMessage()}");
                }
            }
        });

        $this->info("Processed {$count} eligible escrow transaction(s).");
        return self::SUCCESS;
    }
}
