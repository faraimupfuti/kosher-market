<?php

namespace App\Services;

use App\Models\PlatformRevenueEntry;
use App\Models\Vendor;
use App\Models\VendorRegistrationFee;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class VendorRegistrationFeeService
{
    public function createOrGet(Vendor $vendor): VendorRegistrationFee
    {
        $fee=VendorRegistrationFee::firstOrCreate(['vendor_id'=>$vendor->id],['usd_amount'=>config('bitcoin.vendor_registration_usd','200.00'),'currency'=>'BTC','status'=>'pending']);
        if($fee->status==='paid')return $fee;
        $baseUrl=rtrim((string)config('bitcoin.shkeeper_url'),'/'); $apiKey=(string)config('bitcoin.shkeeper_api_key'); $callback=(string)config('bitcoin.callback_url'); if(!$baseUrl||!$apiKey||!$callback)throw new RuntimeException('SHKeeper is not configured.');
        $response=Http::timeout(20)->withHeaders(['X-Shkeeper-Api-Key'=>$apiKey])->acceptJson()->post($baseUrl.'/api/v1/BTC/payment_request',['external_id'=>'vendor-fee-'.$fee->id,'fiat'=>config('bitcoin.fiat_currency','USD'),'amount'=>number_format((float)$fee->usd_amount,2,'.',''),'callback_url'=>$callback]);
        if($response->failed()||($response->json('status')??'success')==='error')throw new RuntimeException('SHKeeper vendor registration invoice creation failed: '.($response->json('message')??$response->body()));
        $invoice=$response->json(); $fee->update(['shkeeper_invoice_id'=>(string)($invoice['id']??''),'checkout_url'=>$invoice['checkoutLink']??null,'bitcoin_payment_address'=>$invoice['wallet']??null,'btc_amount'=>$invoice['amount']??$fee->btc_amount]); return $fee->fresh();
    }

    public function markPaid(VendorRegistrationFee $fee,string $txid,int $confirmations,string|int|float $btcAmount): VendorRegistrationFee
    {
        if($confirmations<(int)config('bitcoin.required_confirmations',1))return $fee;
        return DB::transaction(function()use($fee,$txid,$confirmations,$btcAmount){$locked=VendorRegistrationFee::whereKey($fee->id)->lockForUpdate()->firstOrFail(); if($locked->status==='paid')return $locked; $receivedSatoshis=BitcoinAmount::toSatoshis((string)$btcAmount); if($locked->btc_amount!==null&&$receivedSatoshis<BitcoinAmount::toSatoshis((string)$locked->btc_amount))throw new RuntimeException('Bitcoin payment does not cover the vendor onboarding invoice.'); $normalized=BitcoinAmount::fromSatoshis($receivedSatoshis); $locked->update(['status'=>'paid','bitcoin_txid'=>$txid,'bitcoin_confirmations'=>$confirmations,'btc_amount'=>$normalized,'payment_detected_at'=>$locked->payment_detected_at?:now(),'paid_at'=>now()]); $locked->vendor()->update(['status'=>'active']); PlatformRevenueEntry::firstOrCreate(['reference'=>'VENDOR-ONBOARDING-'.$locked->id],['type'=>'vendor_onboarding','vendor_id'=>$locked->vendor_id,'amount_btc'=>$normalized,'amount_satoshis'=>$receivedSatoshis,'status'=>'earned','description'=>'One-time Kosher Market Vendor Verification & Onboarding Fee.','earned_at'=>now()]); return $locked;});
    }
}
