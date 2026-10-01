<?php

namespace App\Services;

use App\Models\MoneroSettlement;
use App\Models\EscrowTransaction;
use App\Models\Vendor;
use App\Models\VendorWallet;
use App\Models\VendorWalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class VendorWalletService
{
    public function walletFor(Vendor $vendor): VendorWallet
    {
        return DB::transaction(function() use ($vendor) {
            return VendorWallet::firstOrCreate(
                ['vendor_id'=>$vendor->id],
                ['crypto'=>'XMR','available_satoshis'=>0,'locked_satoshis'=>0,'xmr_atomic_available'=>'0','xmr_atomic_locked'=>'0']
            );
        });
    }

    public function allocateDepositAddress(Vendor $vendor): VendorWallet
    {
        $wallet=$this->walletFor($vendor);
        if($wallet->xmr_deposit_address) return $wallet;
        $baseUrl=rtrim((string)config('monero.shkeeper_url'),'/');
        $apiKey=(string)config('monero.shkeeper_api_key');
        if(!$baseUrl||!$apiKey) throw new RuntimeException('SHKeeper is not configured.');
        $response=Http::timeout(20)->withHeaders(['X-Shkeeper-Api-Key'=>$apiKey])->acceptJson()->get($baseUrl.'/api/v1/XMR/addresses');
        if($response->failed()) throw new RuntimeException('Unable to retrieve SHKeeper XMR addresses.');
        $addresses=$response->json('addresses',[]);
        if(!is_array($addresses)||!$addresses) throw new RuntimeException('SHKeeper has no XMR addresses available.');
        foreach($addresses as $address){
            if(!is_string($address)||$address==='') continue;
            if(VendorWallet::where('xmr_deposit_address',$address)->exists()) continue;
            $txResponse=Http::timeout(15)->withHeaders(['X-Shkeeper-Api-Key'=>$apiKey])->acceptJson()->get($baseUrl.'/api/v1/transactions/XMR/'.urlencode($address));
            if($txResponse->failed()) continue;
            $transactions=$txResponse->json('transactions',[]);
            if(is_array($transactions)&&count($transactions)>0) continue;
            $wallet->update(['xmr_deposit_address'=>$address,'crypto'=>'XMR']);
            return $wallet->fresh();
        }
        throw new RuntimeException('No unused SHKeeper XMR address is currently available.');
    }

    public function syncDeposits(Vendor $vendor): int
    {
        $wallet=$this->walletFor($vendor);
        if(!$wallet->xmr_deposit_address) throw new RuntimeException('Generate an XMR deposit address first.');
        $baseUrl=rtrim((string)config('monero.shkeeper_url'),'/');
        $apiKey=(string)config('monero.shkeeper_api_key');
        $response=Http::timeout(20)->withHeaders(['X-Shkeeper-Api-Key'=>$apiKey])->acceptJson()->get($baseUrl.'/api/v1/transactions/XMR/'.urlencode($wallet->xmr_deposit_address));
        if($response->failed()) throw new RuntimeException('Unable to retrieve wallet transactions from SHKeeper.');
        $transactions=$response->json('transactions',[]);
        if(!is_array($transactions)) return 0;
        $posted=0;
        foreach($transactions as $tx){
            $txid=(string)($tx['txid']??'');
            $status=strtoupper((string)($tx['status']??''));
            $amount=(string)($tx['amount']??$tx['amount_crypto']??'');
            if(!$txid||!$amount||$status!=='CONFIRMED') continue;
            $atomic=MoneroAmount::toAtomic($amount);
            if(MoneroAmount::cmp($atomic,'0')<=0) continue;
            $created=DB::transaction(function()use($wallet,$txid,$atomic){
                $locked=VendorWallet::whereKey($wallet->id)->lockForUpdate()->firstOrFail();
                $reference='XMR-DEPOSIT-'.$txid;
                if(VendorWalletTransaction::where('reference',$reference)->exists()) return false;
                $new=MoneroAmount::add((string)$locked->xmr_atomic_available,$atomic);
                $locked->update(['crypto'=>'XMR','xmr_atomic_available'=>$new]);
                VendorWalletTransaction::create(['vendor_wallet_id'=>$locked->id,'type'=>'deposit','amount_satoshis'=>0,'balance_after_satoshis'=>0,'reference'=>$reference,'status'=>'posted','txid'=>$txid,'xmr_atomic_amount'=>$atomic,'xmr_atomic_balance_after'=>$new,'metadata'=>['gateway'=>'shkeeper','crypto'=>'XMR','atomic_amount'=>$atomic,'deposit_address'=>$locked->xmr_deposit_address]]);
                return true;
            });
            if($created)$posted++;
        }
        return $posted;
    }

    public function creditFromEscrow(EscrowTransaction $escrow): VendorWalletTransaction
    {
        return DB::transaction(function()use($escrow){
            $wallet=VendorWallet::where('vendor_id',$escrow->vendor_id)->lockForUpdate()->first();
            if(!$wallet)$wallet=VendorWallet::create(['vendor_id'=>$escrow->vendor_id,'crypto'=>'XMR','available_satoshis'=>0,'locked_satoshis'=>0,'xmr_atomic_available'=>'0','xmr_atomic_locked'=>'0']);
            $wallet=VendorWallet::whereKey($wallet->id)->lockForUpdate()->firstOrFail();
            $reference='XMR-ESCROW-CREDIT-'.$escrow->id;
            $existing=VendorWalletTransaction::where('reference',$reference)->first();
            if($existing)return $existing;
            $amount=MoneroAmount::toAtomic((string)$escrow->seller_amount);
            $new=MoneroAmount::add((string)$wallet->xmr_atomic_available,$amount);
            $wallet->update(['crypto'=>'XMR','xmr_atomic_available'=>$new]);
            return VendorWalletTransaction::create(['vendor_wallet_id'=>$wallet->id,'type'=>'escrow_credit','amount_satoshis'=>0,'balance_after_satoshis'=>0,'reference'=>$reference,'status'=>'posted','escrow_transaction_id'=>$escrow->id,'xmr_atomic_amount'=>$amount,'xmr_atomic_balance_after'=>$new,'metadata'=>['source'=>'manual_escrow_release','crypto'=>'XMR','atomic_amount'=>$amount]]);
        });
    }

    public function requestWithdrawal(Vendor $vendor,string $amountXmr): MoneroSettlement
    {
        $amount=MoneroAmount::toAtomic($amountXmr);
        if(MoneroAmount::cmp($amount,'0')<=0) throw new RuntimeException('Withdrawal amount must be greater than zero.');
        return DB::transaction(function()use($vendor,$amount){
            $wallet=VendorWallet::where('vendor_id',$vendor->id)->lockForUpdate()->first();
            if(!$wallet||MoneroAmount::cmp((string)$wallet->xmr_atomic_available,$amount)<0) throw new RuntimeException('Insufficient available XMR wallet balance.');
            $destination=trim((string)$vendor->xmr_payout_address);
            if(!preg_match('/^(?:4|8)[1-9A-HJ-NP-Za-km-z]{94}$/',$destination)) throw new RuntimeException('Set a valid Monero withdrawal address first.');
            if(!$vendor->xmr_payout_address_verified_at) throw new RuntimeException('Your Monero withdrawal address must be verified first.');
            $settlement=MoneroSettlement::create(['escrow_transaction_id'=>null,'vendor_id'=>$vendor->id,'type'=>'vendor_withdrawal','amount'=>MoneroAmount::fromAtomic($amount),'currency'=>'XMR','destination_address'=>$destination,'status'=>'pending']);
            $available=MoneroAmount::sub((string)$wallet->xmr_atomic_available,$amount);
            $locked=MoneroAmount::add((string)$wallet->xmr_atomic_locked,$amount);
            $wallet->update(['crypto'=>'XMR','xmr_atomic_available'=>$available,'xmr_atomic_locked'=>$locked]);
            VendorWalletTransaction::create(['vendor_wallet_id'=>$wallet->id,'type'=>'withdrawal_hold','amount_satoshis'=>0,'balance_after_satoshis'=>0,'reference'=>'XMR-WITHDRAWAL-HOLD-'.$settlement->id,'status'=>'pending','bitcoin_settlement_id'=>$settlement->id,'xmr_atomic_amount'=>$amount,'xmr_atomic_balance_after'=>$available,'metadata'=>['destination'=>$destination,'crypto'=>'XMR','atomic_amount'=>$amount]]);
            return $settlement;
        });
    }

    public function finalizeWithdrawal(MoneroSettlement $settlement,bool $success): void
    {
        DB::transaction(function()use($settlement,$success){
            $wallet=VendorWallet::where('vendor_id',$settlement->vendor_id)->lockForUpdate()->firstOrFail();
            $amount=MoneroAmount::toAtomic((string)$settlement->amount);
            $reference='XMR-WITHDRAWAL-'.$settlement->id.'-'.($success?'COMPLETE':'RELEASE');
            if(VendorWalletTransaction::where('reference',$reference)->exists())return;
            $locked=MoneroAmount::sub((string)$wallet->xmr_atomic_locked,$amount);
            $available=(string)$wallet->xmr_atomic_available;
            if(!$success)$available=MoneroAmount::add($available,$amount);
            $wallet->update(['xmr_atomic_available'=>$available,'xmr_atomic_locked'=>$locked]);
            VendorWalletTransaction::create(['vendor_wallet_id'=>$wallet->id,'type'=>$success?'withdrawal_complete':'withdrawal_reversal','amount_satoshis'=>0,'balance_after_satoshis'=>0,'reference'=>$reference,'status'=>$success?'posted':'reversed','bitcoin_settlement_id'=>$settlement->id,'txid'=>$settlement->xmr_txid,'xmr_atomic_amount'=>$amount,'xmr_atomic_balance_after'=>$available,'metadata'=>['crypto'=>'XMR','atomic_amount'=>$amount]]);
        });
    }
}
