<?php

namespace App\Services;

use App\Models\MoneroSettlement;
use App\Models\EscrowLedgerEntry;
use App\Models\EscrowTransaction;
use App\Models\Order;
use App\Models\PlatformRevenueEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class MoneroEscrowService
{
    public function createForOrder(Order $order): EscrowTransaction
    {
        $order->loadMissing(['product','customer']);
        $vendorId=$order->product?->vendor_id; $buyerId=$order->customer?->id;
        if(!$vendorId||!$buyerId) throw new RuntimeException('The order must have a customer and vendor.');
        if(strtoupper((string)$order->currency)!=='XMR') throw new RuntimeException('Kosher Market accepts Monero only.');
        $amount=MoneroAmount::normalize((string)($order->xmr_total_amount ?: $order->total_amount));
        $atomic=MoneroAmount::toAtomic($amount);
        if(MoneroAmount::cmp($atomic,'0')<=0) throw new RuntimeException('Invalid XMR escrow amount.');
        $feeAtomic=MoneroAmount::percentOf($atomic,(string)config('monero.platform_fee_percent','3.00'));
        $fee=MoneroAmount::fromAtomic($feeAtomic); $seller=MoneroAmount::fromAtomic(MoneroAmount::sub($atomic,$feeAtomic));
        return DB::transaction(fn()=>EscrowTransaction::firstOrCreate(['order_id'=>$order->id],[
            'payment_id'=>null,'buyer_id'=>$buyerId,'vendor_id'=>$vendorId,'amount'=>$amount,'platform_fee'=>$fee,'seller_amount'=>$seller,
            'currency'=>'XMR','status'=>'pending','xmr_amount'=>$amount,'xmr_platform_fee'=>$fee,'xmr_seller_amount'=>$seller
        ]));
    }

    public function createMoneroInvoice(EscrowTransaction $escrow): array
    {
        if($escrow->currency!=='XMR') throw new RuntimeException('Only Monero invoices are supported.');
        $base=rtrim((string)config('monero.shkeeper_url'),'/'); $key=(string)config('monero.shkeeper_api_key'); $callback=(string)config('monero.callback_url');
        if(!$base||!$key||!$callback) throw new RuntimeException('SHKeeper is not configured.');
        $xmrAmount=(string)($escrow->xmr_amount ?: $escrow->amount);
        $quote=Http::timeout(20)->withHeaders(['X-Shkeeper-Api-Key'=>$key])->acceptJson()->post($base.'/api/v1/XMR/quote',['fiat'=>config('monero.fiat_currency','USD'),'amount'=>'1.00']);
        if($quote->failed()||!$quote->json('crypto_amount')) throw new RuntimeException('Unable to obtain the current SHKeeper XMR quote.');
        $xmrPerFiat=(string)$quote->json('crypto_amount');
        $fiatAmount=bcdiv($xmrAmount,$xmrPerFiat,12);
        $response=Http::timeout(20)->withHeaders(['X-Shkeeper-Api-Key'=>$key])->acceptJson()->post($base.'/api/v1/XMR/payment_request',[
            'external_id'=>(string)$escrow->id,'fiat'=>config('monero.fiat_currency','USD'),'amount'=>$fiatAmount,'callback_url'=>$callback
        ]);
        if($response->failed()||($response->json('status')??'success')==='error') throw new RuntimeException('SHKeeper XMR invoice creation failed: '.($response->json('message')??$response->body()));
        $invoice=$response->json(); $invoiceAmount=(string)($invoice['amount']??'');
        if($invoiceAmount!==''&&abs((int)bcsub(MoneroAmount::toAtomic($invoiceAmount),MoneroAmount::toAtomic($xmrAmount),0))>1) throw new RuntimeException('SHKeeper XMR invoice amount does not match the escrow amount.');
        $escrow->update(['shkeeper_invoice_id'=>(string)($invoice['id']??$escrow->id),'xmr_payment_address'=>$invoice['wallet']??null,'xmr_amount'=>$invoiceAmount?:$xmrAmount]);
        return $invoice;
    }

    public function markFunded(EscrowTransaction $escrow,string $txid,int $confirmations,string|int|float $amount): EscrowTransaction
    {
        if($confirmations<(int)config('monero.required_confirmations',10)) return $escrow;
        $received=MoneroAmount::toAtomic($amount); $required=MoneroAmount::toAtomic((string)($escrow->xmr_amount?:$escrow->amount));
        if(MoneroAmount::cmp($received,$required)<0) throw new RuntimeException('Monero payment is below the escrow amount.');
        return DB::transaction(function()use($escrow,$txid,$confirmations,$amount){
            $locked=EscrowTransaction::whereKey($escrow->id)->lockForUpdate()->firstOrFail();
            if(in_array($locked->status,['released','paid','refund_pending','refunded','cancelled'],true)) return $locked;
            if($locked->xmr_txid&&!hash_equals($locked->xmr_txid,$txid)) throw new RuntimeException('Escrow already has a different Monero transaction.');
            $normalized=MoneroAmount::normalize((string)$amount); $locked->transitionTo('funded');
            $locked->update(['status'=>'funded','xmr_txid'=>$txid,'xmr_amount'=>$normalized,'xmr_confirmations'=>$confirmations,'payment_detected_at'=>$locked->payment_detected_at?:now(),'payment_confirmed_at'=>now(),'funded_at'=>$locked->funded_at?:now(),'release_due_at'=>$locked->release_due_at?:now()->addDays((int)config('escrow.hold_days',3))]);
            if(!$locked->ledgerEntries()->where('reference',$txid)->exists()) EscrowLedgerEntry::create(['escrow_transaction_id'=>$locked->id,'type'=>'escrow_funded','amount'=>$normalized,'currency'=>'XMR','reference'=>$txid,'metadata'=>['confirmations'=>$confirmations,'gateway'=>'shkeeper','crypto'=>'XMR']]);
            return $locked;
        });
    }

    public function release(EscrowTransaction $escrow,?string $note=null): EscrowTransaction
    {
        return DB::transaction(function()use($escrow,$note){
            $escrow=EscrowTransaction::with('vendor','order')->whereKey($escrow->id)->lockForUpdate()->firstOrFail();
            if(!$escrow->canTransitionTo('released')) throw new RuntimeException('Only funded or dispute-eligible escrow can be released.');
            $fee=(string)($escrow->xmr_platform_fee?:$escrow->platform_fee); $seller=(string)($escrow->xmr_seller_amount?:$escrow->seller_amount);
            $escrow->transitionTo('released'); $escrow->update(['status'=>'released','released_at'=>now(),'release_note'=>$note]);
            if(!$escrow->ledgerEntries()->where('type','seller_payout_due')->exists()) EscrowLedgerEntry::create(['escrow_transaction_id'=>$escrow->id,'type'=>'seller_payout_due','amount'=>$seller,'currency'=>'XMR','reference'=>'XMR-ESCROW-'.$escrow->id]);
            PlatformRevenueEntry::firstOrCreate(['reference'=>'XMR-SALE-COMMISSION-'.$escrow->id],['type'=>'sale_commission','vendor_id'=>$escrow->vendor_id,'order_id'=>$escrow->order_id,'escrow_transaction_id'=>$escrow->id,'amount_btc'=>null,'amount_satoshis'=>0,'amount_xmr'=>$fee,'status'=>'earned','description'=>'Kosher Market commission earned when XMR escrow was released.','earned_at'=>now()]);
            app(VendorWalletService::class)->creditFromEscrow($escrow); return $escrow;
        });
    }

    public function refund(EscrowTransaction $escrow,?string $note=null): EscrowTransaction
    {
        return DB::transaction(function()use($escrow,$note){
            $escrow=EscrowTransaction::whereKey($escrow->id)->lockForUpdate()->firstOrFail();
            if(!$escrow->canTransitionTo('refund_pending')) throw new RuntimeException('Only funded or disputed escrow can be refunded.');
            $escrow->transitionTo('refund_pending'); $escrow->update(['status'=>'refund_pending','refund_note'=>$note]);
            if(!$escrow->ledgerEntries()->where('type','buyer_refund_due')->exists()) EscrowLedgerEntry::create(['escrow_transaction_id'=>$escrow->id,'type'=>'buyer_refund_due','amount'=>$escrow->xmr_amount?:$escrow->amount,'currency'=>'XMR','reference'=>'XMR-REFUND-'.$escrow->id]);
            BitcoinSettlement::firstOrCreate(['escrow_transaction_id'=>$escrow->id,'type'=>'buyer_refund'],['vendor_id'=>null,'amount'=>$escrow->xmr_amount?:$escrow->amount,'currency'=>'XMR','status'=>'needs_destination','xmr_amount'=>$escrow->xmr_amount?:$escrow->amount]);
            return $escrow;
        });
    }

    public function submitSettlement(BitcoinSettlement $settlement): BitcoinSettlement
    {
        if(in_array($settlement->status,['completed','in_progress'],true)||$settlement->shkeeper_payout_id)return $settlement;
        if(!$settlement->destination_address) throw new RuntimeException('Settlement destination is missing.');
        if(!preg_match('/^(?:4|8)[1-9A-HJ-NP-Za-km-z]{94}$/',$settlement->destination_address)) throw new RuntimeException('Only valid Monero primary addresses are accepted.');
        $base=rtrim((string)config('monero.shkeeper_url'),'/'); $user=(string)config('monero.shkeeper_username'); $pass=(string)config('monero.shkeeper_password');
        if(!$base||!$user||!$pass) throw new RuntimeException('SHKeeper payout credentials are not configured.');
        $amount=(string)($settlement->xmr_amount?:$settlement->amount);
        $response=Http::timeout(20)->withBasicAuth($user,$pass)->acceptJson()->post($base.'/api/v1/XMR/payout',['amount'=>$amount,'destination'=>$settlement->destination_address,'fee'=>config('monero.payout_priority','2'),'external_id'=>'settlement-'.$settlement->id]);
        if($response->failed()||($response->json('status')??'success')==='error'){ $settlement->update(['status'=>'failed','error_message'=>$response->body()]); if($settlement->type==='vendor_withdrawal') app(VendorWalletService::class)->finalizeWithdrawal($settlement,false); throw new RuntimeException('SHKeeper XMR payout request failed.'); }
        $taskId=$response->json('task_id'); if(!$taskId) throw new RuntimeException('SHKeeper did not return a payout task ID.');
        $settlement->update(['status'=>'in_progress','shkeeper_payout_id'=>$taskId,'submitted_at'=>now(),'error_message'=>null]); return $settlement->fresh();
    }

    public function syncSettlement(BitcoinSettlement $settlement): BitcoinSettlement
    {
        if(!$settlement->shkeeper_payout_id) return $settlement;
        $base=rtrim((string)config('monero.shkeeper_url'),'/'); $key=(string)config('monero.shkeeper_api_key');
        $response=Http::timeout(20)->withHeaders(['X-Shkeeper-Api-Key'=>$key])->acceptJson()->get($base.'/api/v1/XMR/payout/status',['external_id'=>'settlement-'.$settlement->id]);
        if($response->failed()) throw new RuntimeException('Unable to retrieve SHKeeper XMR payout status.');
        $payout=$response->json(); $status=strtoupper((string)($payout['status']??'IN_PROGRESS')); $mapped=match($status){'SUCCESS'=>'completed','FAIL','FAILURE'=>'failed',default=>'in_progress'};
        $txid=(string)($payout['txid']??''); $settlement->update(['status'=>$mapped,'xmr_txid'=>$txid?:$settlement->xmr_txid,'completed_at'=>$mapped==='completed'?($settlement->completed_at?:now()):$settlement->completed_at,'error_message'=>$mapped==='failed'?'SHKeeper XMR payout failed.':null]);
        if($settlement->type==='vendor_withdrawal'&&in_array($mapped,['completed','failed'],true)) app(VendorWalletService::class)->finalizeWithdrawal($settlement,$mapped==='completed');
        return $settlement->fresh();
    }

    public function approveSettlement(BitcoinSettlement $settlement): BitcoinSettlement { return $this->syncSettlement($settlement); }
}
