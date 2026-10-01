<?php

namespace App\Services;

use App\Models\BitcoinSettlement;
use App\Models\EscrowLedgerEntry;
use App\Models\EscrowTransaction;
use App\Models\Order;
use App\Models\PlatformRevenueEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class BitcoinEscrowService
{
    private const MAX_XMR_ATOMIC_UNITS = 9_000_000_000_000_000_000;
    public function __construct(private VendorWalletService $wallets) {}
    private function xmrToAtomic(string|int|float $amount): int { $units = MoneroAmount::toAtomicUnits($amount); if ($units > self::MAX_XMR_ATOMIC_UNITS) throw new RuntimeException('Invalid XMR amount.'); return $units; }
    private function atomicToXmr(int $units): string { return MoneroAmount::fromAtomicUnits($units); }
    private function normalizeXmrAmount(string|int|float $amount): string { return $this->atomicToXmr($this->xmrToAtomic($amount)); }
    private function feeAtomic(int $units): int { return MoneroAmount::percentOf($units, (string) config('bitcoin.platform_fee_percent', '3.00')); }

    public function createForOrder(Order $order): EscrowTransaction
    {
        $order->loadMissing(['product', 'customer']); $vendorId = $order->product?->vendor_id; $buyerId = $order->customer?->id;
        if (!$vendorId || !$buyerId) throw new RuntimeException('The order must have a customer and vendor.');
        if (strtoupper((string) ($order->currency ?? '')) !== 'XMR') throw new RuntimeException('Kosher Market accepts Monero only.');
        $atomic = $this->xmrToAtomic($order->total_amount); if ($atomic <= 0) throw new RuntimeException('Invalid XMR escrow amount.');
        $fee = $this->feeAtomic($atomic);
        return DB::transaction(fn () => EscrowTransaction::firstOrCreate(['order_id' => $order->id], ['payment_id' => null, 'buyer_id' => $buyerId, 'vendor_id' => $vendorId, 'amount' => $this->atomicToXmr($atomic), 'platform_fee' => $this->atomicToXmr($fee), 'seller_amount' => $this->atomicToXmr($atomic - $fee), 'currency' => 'XMR', 'status' => 'pending']));
    }

    public function createBitcoinInvoice(EscrowTransaction $escrow): array
    {
        if ($escrow->currency !== 'XMR') throw new RuntimeException('Monero is the only supported marketplace currency.');
        $baseUrl = rtrim((string) config('bitcoin.shkeeper_url'), '/'); $apiKey = (string) config('bitcoin.shkeeper_api_key'); $callbackUrl = (string) config('bitcoin.callback_url');
        if (!$baseUrl || !$apiKey || !$callbackUrl) throw new RuntimeException('SHKeeper is not configured.');
        $quote = Http::timeout(20)->withHeaders(['X-Shkeeper-Api-Key' => $apiKey])->acceptJson()->post($baseUrl . '/api/v1/XMR/quote', ['fiat' => config('bitcoin.fiat_currency', 'USD'), 'amount' => '1.00']);
        if ($quote->failed() || !$quote->json('crypto_amount')) throw new RuntimeException('Unable to obtain the current SHKeeper XMR quote.');
        $xmrPerFiat = (float) $quote->json('crypto_amount'); if ($xmrPerFiat <= 0) throw new RuntimeException('SHKeeper returned an invalid XMR quote.');
        $fiatAmount = number_format((float) $escrow->amount / $xmrPerFiat, 8, '.', '');
        $response = Http::timeout(20)->withHeaders(['X-Shkeeper-Api-Key' => $apiKey])->acceptJson()->post($baseUrl . '/api/v1/XMR/payment_request', ['external_id' => (string) $escrow->id, 'fiat' => config('bitcoin.fiat_currency', 'USD'), 'amount' => $fiatAmount, 'callback_url' => $callbackUrl]);
        if ($response->failed() || ($response->json('status') ?? 'success') === 'error') throw new RuntimeException('SHKeeper XMR invoice creation failed: ' . ($response->json('message') ?? $response->body()));
        $invoice = $response->json(); $invoiceXmr = (string) ($invoice['amount'] ?? '');
        if ($invoiceXmr !== '' && $this->xmrToAtomic($invoiceXmr) !== $this->xmrToAtomic((string) $escrow->amount)) throw new RuntimeException('SHKeeper XMR invoice amount does not match the escrow amount.');
        $escrow->update(['shkeeper_invoice_id' => (string) ($invoice['id'] ?? $escrow->id), 'bitcoin_payment_address' => $invoice['wallet'] ?? null]);
        return $invoice;
    }

    public function markFunded(EscrowTransaction $escrow, string $txid, int $confirmations, string|int|float $xmrAmount): EscrowTransaction
    {
        if ($confirmations < (int) config('bitcoin.required_confirmations', 1)) return $escrow;
        if ($this->xmrToAtomic($xmrAmount) < $this->xmrToAtomic((string) $escrow->amount)) throw new RuntimeException('Monero payment is below the escrow amount.');
        return DB::transaction(function () use ($escrow, $txid, $confirmations, $xmrAmount) {
            $escrow = EscrowTransaction::whereKey($escrow->id)->lockForUpdate()->firstOrFail(); if (in_array($escrow->status, ['released','paid','refund_pending','refunded','cancelled'], true)) return $escrow;
            if ($escrow->bitcoin_txid && !hash_equals($escrow->bitcoin_txid, $txid)) throw new RuntimeException('Escrow already has a different transaction.');
            $amount = $this->normalizeXmrAmount($xmrAmount); $escrow->transitionTo('funded');
            $escrow->update(['status'=>'funded','bitcoin_txid'=>$txid,'bitcoin_amount'=>$amount,'bitcoin_confirmations'=>$confirmations,'payment_detected_at'=>$escrow->payment_detected_at ?: now(),'payment_confirmed_at'=>now(),'funded_at'=>$escrow->funded_at ?: now(),'release_due_at'=>$escrow->release_due_at ?: now()->addDays((int) config('escrow.hold_days',3))]);
            if (!$escrow->ledgerEntries()->where('reference',$txid)->exists()) EscrowLedgerEntry::create(['escrow_transaction_id'=>$escrow->id,'type'=>'escrow_funded','amount'=>$amount,'currency'=>'XMR','reference'=>$txid,'metadata'=>['confirmations'=>$confirmations,'gateway'=>'shkeeper','crypto'=>'XMR']]);
            return $escrow;
        });
    }

    public function release(EscrowTransaction $escrow, ?string $note = null): EscrowTransaction
    {
        return DB::transaction(function () use ($escrow,$note) {
            $escrow=EscrowTransaction::with('vendor','order')->whereKey($escrow->id)->lockForUpdate()->firstOrFail(); if(!$escrow->canTransitionTo('released')) throw new RuntimeException('Only funded or dispute-eligible escrow can be released.');
            $fee=$this->xmrToAtomic($escrow->platform_fee); $escrow->transitionTo('released'); $escrow->update(['status'=>'released','released_at'=>now(),'release_note'=>$note]);
            if(!$escrow->ledgerEntries()->where('type','seller_payout_due')->exists()) EscrowLedgerEntry::create(['escrow_transaction_id'=>$escrow->id,'type'=>'seller_payout_due','amount'=>$escrow->seller_amount,'currency'=>'XMR','reference'=>'ESCROW-'.$escrow->id]);
            PlatformRevenueEntry::firstOrCreate(['reference'=>'SALE-COMMISSION-ESCROW-'.$escrow->id],['type'=>'sale_commission','vendor_id'=>$escrow->vendor_id,'order_id'=>$escrow->order_id,'escrow_transaction_id'=>$escrow->id,'amount_btc'=>$this->atomicToXmr($fee),'amount_satoshis'=>$fee,'status'=>'earned','description'=>'3% Kosher Market commission earned when XMR escrow was released.','earned_at'=>now()]);
            $this->wallets->creditFromEscrow($escrow); return $escrow;
        });
    }

    public function refund(EscrowTransaction $escrow, ?string $note = null): EscrowTransaction
    {
        return DB::transaction(function () use ($escrow,$note) { $escrow=EscrowTransaction::whereKey($escrow->id)->lockForUpdate()->firstOrFail(); if(!$escrow->canTransitionTo('refund_pending')) throw new RuntimeException('Only funded or disputed escrow can be refunded.'); $escrow->transitionTo('refund_pending'); $escrow->update(['status'=>'refund_pending','refund_note'=>$note]); if(!$escrow->ledgerEntries()->where('type','buyer_refund_due')->exists()) EscrowLedgerEntry::create(['escrow_transaction_id'=>$escrow->id,'type'=>'buyer_refund_due','amount'=>$escrow->bitcoin_amount ?? $escrow->amount,'currency'=>'XMR','reference'=>'REFUND-'.$escrow->id]); BitcoinSettlement::firstOrCreate(['escrow_transaction_id'=>$escrow->id,'type'=>'buyer_refund'],['vendor_id'=>null,'amount'=>$escrow->bitcoin_amount ?? $escrow->amount,'currency'=>'XMR','status'=>'needs_destination']); return $escrow; });
    }

    public function submitSettlement(BitcoinSettlement $settlement): BitcoinSettlement
    {
        if($settlement->status==='completed') return $settlement; if(!$settlement->destination_address) throw new RuntimeException('Settlement destination is missing.'); if(!preg_match('/^4[0-9AB][1-9A-HJ-NP-Za-km-z]{90,110}$/',$settlement->destination_address)) throw new RuntimeException('Only valid Monero addresses are accepted.');
        $baseUrl=rtrim((string)config('bitcoin.shkeeper_url'),'/'); $username=(string)config('bitcoin.shkeeper_username'); $password=(string)config('bitcoin.shkeeper_password'); if(!$baseUrl||!$username||!$password) throw new RuntimeException('SHKeeper payout credentials are not configured.');
        $response=Http::timeout(20)->withBasicAuth($username,$password)->acceptJson()->post($baseUrl.'/api/v1/XMR/payout',['amount'=>$this->normalizeXmrAmount($settlement->amount),'destination'=>$settlement->destination_address,'fee'=>config('bitcoin.payout_fee','2'),'external_id'=>'settlement-'.$settlement->id]);
        if($response->failed()||($response->json('status')??'success')==='error'){ $settlement->update(['status'=>'failed','error_message'=>$response->body()]); if($settlement->type==='vendor_withdrawal') $this->wallets->finalizeWithdrawal($settlement,false); throw new RuntimeException('SHKeeper XMR payout request failed.'); }
        $taskId=$response->json('task_id'); if(!$taskId) throw new RuntimeException('SHKeeper did not return an XMR payout task ID.'); $settlement->update(['status'=>'in_progress','shkeeper_payout_id'=>$taskId,'submitted_at'=>now(),'error_message'=>null]); return $settlement->fresh();
    }
    public function approveSettlement(BitcoinSettlement $settlement): BitcoinSettlement { return $this->syncSettlement($settlement); }
    public function syncSettlement(BitcoinSettlement $settlement): BitcoinSettlement
    {
        if(!$settlement->shkeeper_payout_id) return $settlement; $baseUrl=rtrim((string)config('bitcoin.shkeeper_url'),'/'); $username=(string)config('bitcoin.shkeeper_username'); $password=(string)config('bitcoin.shkeeper_password'); $response=Http::timeout(20)->withBasicAuth($username,$password)->acceptJson()->get($baseUrl.'/api/v1/XMR/task/'.$settlement->shkeeper_payout_id); if($response->failed()) throw new RuntimeException('Unable to retrieve SHKeeper XMR payout status.');
        $payout=$response->json(); $status=strtoupper((string)($payout['status']??'PENDING')); $mapped=match($status){'SUCCESS'=>'completed','FAILURE','FAIL'=>'failed',default=>'in_progress'}; $txid=data_get($payout,'result.0.txids.0')??data_get($payout,'result.0.txid'); $settlement->update(['status'=>$mapped,'bitcoin_txid'=>$txid?:$settlement->bitcoin_txid,'completed_at'=>$mapped==='completed'?($settlement->completed_at?:now()):$settlement->completed_at,'error_message'=>$mapped==='failed'?'SHKeeper XMR payout failed.':null]);
        if($settlement->type==='vendor_withdrawal'&&in_array($mapped,['completed','failed'],true)) $this->wallets->finalizeWithdrawal($settlement,$mapped==='completed');
        if($mapped==='completed'){ $escrow=$settlement->escrow()->first(); if($escrow&&$settlement->type!=='vendor_withdrawal'&&$escrow->canTransitionTo('paid')) $escrow->update(['status'=>'paid']); if($escrow&&$settlement->type!=='seller_payout'&&$settlement->type!=='vendor_withdrawal'&&$escrow->canTransitionTo('refunded')) $escrow->update(['status'=>'refunded','refunded_at'=>$escrow->refunded_at?:now()]); }
        return $settlement->fresh();
    }
}
