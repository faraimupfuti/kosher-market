<?php

namespace App\Console;

use App\Console\Commands\DataImport;
use App\Console\Commands\ReconcileBitcoinPayouts;
use App\Console\Commands\Velstore;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        Velstore::class,
        DataImport::class,
        Commands\ReleaseEligibleEscrow::class,
        Commands\SyncBitcoinSettlements::class,
        ReconcileBitcoinPayouts::class,
    ];

    /**
     * No marketplace or settlement actions are scheduled automatically.
     *
     * Operational commands remain registered so an administrator can invoke
     * them explicitly after reviewing the relevant orders/settlements.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Intentionally empty: Kosher Market is manually operated.
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
        require base_path('routes/console.php');
    }
}
