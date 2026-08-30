<?php

namespace App\Console;

use App\Console\Commands\DataImport;
use App\Console\Commands\Velstore;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        Velstore::class,
        DataImport::class,
        Commands\ReleaseEligibleEscrow::class,
    ];

    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('escrow:release-eligible')->hourly()->withoutOverlapping();
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
        require base_path('routes/console.php');
    }
}
