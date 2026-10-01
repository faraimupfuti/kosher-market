<?php

namespace App\Console\Commands;

use App\Models\BitcoinSettlement;
use App\Services\BitcoinEscrowService;
use Illuminate\Console\Command;
use Throwable;

class SyncBitcoinSettlements extends Command
{
    protected $signature = 'bitcoin:settlements-sync {--dry-run : Do not call SHKeeper}';
    protected $description = 'Manually submit pending Bitcoin settlements to SHKeeper and synchronize payout states';

    public function handle(BitcoinEscrowService $service): int
    {
        $count=0;
        BitcoinSettlement::whereIn('status',['pending','failed','in_progress'])->orderBy('id')->chunkById(50,function($settlements)use($service,&$count){foreach($settlements as $settlement){try{if($this->option('dry-run')){$this->line("Settlement #{$settlement->id}: {$settlement->status}");continue;}if(!$settlement->shkeeper_payout_id){if(!$settlement->destination_address){$this->warn("Settlement #{$settlement->id}: destination missing");continue;}$service->submitSettlement($settlement);}else{$service->syncSettlement($settlement);}$count++;}catch(Throwable $e){$this->error("Settlement #{$settlement->id}: {$e->getMessage()}");}}});
        $this->info("Processed {$count} settlement(s)."); return self::SUCCESS;
    }
}
