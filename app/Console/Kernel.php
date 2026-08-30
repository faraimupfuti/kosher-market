<?php

namespace App\Console;

use App\Console\Commands\DataImport;
use App\Console\Commands\Velstore;
use App\Console\Commands\ReconcileBitcoinPayouts;
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

    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('escrow:release-eligible')->hourly()->withoutOverlapping();
        $schedule->command('bitcoin:settlements-sync')->everyFiveMinutes()->withoutOverlapping();
        $schedule->command('bitcoin:reconcile-payouts --limit=25')->everyFiveMinutes()->withoutOverlapping();
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
        require base_path('routes/console.php');
    }
}
