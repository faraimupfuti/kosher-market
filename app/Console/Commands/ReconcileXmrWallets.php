<?php

namespace App\Console\Commands;

use App\Models\VendorWallet;
use Illuminate\Console\Command;

class ReconcileXmrWallets extends Command
{
    protected $signature='wallet:reconcile-xmr {--vendor= : Reconcile one vendor ID}';
    protected $description='Manually reconcile vendor XMR wallet balances against the immutable wallet ledger.';

    public function handle(): int
    {
        $query=VendorWallet::query()->where('crypto','XMR');
        if($this->option('vendor'))$query->where('vendor_id',(int)$this->option('vendor'));
        $errors=0;
        $query->chunkById(100,function($wallets)use(&$errors){
            foreach($wallets as $wallet){
                $credits='0';$holds='0';$reversals='0';$completed='0';
                foreach($wallet->transactions()->where('status','!=','void')->cursor() as $tx){
                    $amount=(string)($tx->xmr_atomic_amount??$tx->metadata['atomic_amount']??'0');
                    if(!preg_match('/^\d+$/',$amount))continue;
                    match($tx->type){
                        'deposit','escrow_credit'=> $credits=bcadd($credits,$amount,0),
                        'withdrawal_hold'=> $holds=bcadd($holds,$amount,0),
                        'withdrawal_reversal'=> $reversals=bcadd($reversals,$amount,0),
                        'withdrawal_complete'=> $completed=bcadd($completed,$amount,0),
                        default=>null,
                    };
                }
                $expectedAvailable=bcsub(bcadd($credits,$reversals,0),$holds,0);
                $expectedLocked=bcsub($holds,bcadd($completed,$reversals,0),0);
                $actualAvailable=(string)$wallet->xmr_atomic_available;$actualLocked=(string)$wallet->xmr_atomic_locked;
                if($expectedAvailable!==$actualAvailable||$expectedLocked!==$actualLocked){
                    $errors++;
                    $this->error("Wallet {$wallet->id} vendor {$wallet->vendor_id} mismatch: available expected {$expectedAvailable}, actual {$actualAvailable}; locked expected {$expectedLocked}, actual {$actualLocked}");
                }else{
                    $this->line("Wallet {$wallet->id} vendor {$wallet->vendor_id}: OK");
                }
            }
        });
        return $errors===0?self::SUCCESS:self::FAILURE;
    }
}
