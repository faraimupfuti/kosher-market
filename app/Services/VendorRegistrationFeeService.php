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
    public function createOrGet(Vendor $vendor):VendorRegistrationFee
    {
        $fee=VendorRegistrationFee::firstOrCreate(['vendor_id'=>$vendor->id],['usd_amount'=>config('monero.vendor_registration_usd','200.00'),'currency'=>'XMR','status'=>'pending']);
        if($fee->status==='paid')return $fee;
        $base=rtrim((string)config('monero.shkeeper_url'),'/');$key=(string)config('monero.shkeeper_api_key');$callback=(string)config('monero.callback_url');
        if(!$base||!$key||!$callback)throw new RuntimeException('SHKeeper is not configured.');
        $response=Http::timeout(20)->withHeaders(['X-Shkeeper-Api-Key'=>$key])->acceptJson()->post($base.'/api/v1/XMR/payment_request',['external_id'=>'vendor-fee-'.$fee->id,'fiat'=>config('monero.fiat_currency','USD'),'amount'=>number_format((float)$fee->usd_amount,2,'.',''),'callback_url'=>$callback]);
        if($response->failed()||($response->json('status')??'success')==='error')throw new RuntimeException('SHKeeper XMR vendor registration invoice creation failed.');
        $invoice=$response->json();$fee->update(['shkeeper_invoice_id'=>(string)($invoice['id']??''),'checkout_url'=>$invoice['checkoutLink']??null,'xmr_payment_address'=>$invoice['wallet']??null,'xmr_amount'=>$invoice['amount']??null]);return $fee->fresh();
    }

    public function markPaid(VendorRegistrationFee $fee,string $txid,int $confirmations,string|int|float $xmrAmount):VendorRegistrationFee
    {
        if($confirmations<(int)config('monero.required_confirmations',10))return $fee;
        return DB::transaction(function()use($fee,$txid,$confirmations,$xmrAmount){
            $locked=VendorRegistrationFee::whereKey($fee->id)->lockForUpdate()->firstOrFail();if($locked->status==='paid')return $locked;
            $received=MoneroAmount::normalize($xmrAmount);$expected=(string)($locked->xmr_amount?:'0');
            if($expected!==''&&MoneroAmount::cmp(MoneroAmount::toAtomic($received),MoneroAmount::toAtomic($expected))<0)throw new RuntimeException('Monero payment does not cover the vendor onboarding invoice.');
            $locked->update(['status'=>'paid','xmr_txid'=>$txid,'xmr_confirmations'=>$confirmations,'xmr_amount'=>$received,'payment_detected_at'=>$locked->payment_detected_at?:now(),'paid_at'=>now()]);
            PlatformRevenueEntry::firstOrCreate(['reference'=>'VENDOR-ONBOARDING-'.$locked->id],['type'=>'vendor_onboarding','vendor_id'=>$locked->vendor_id,'amount_btc'=>$received,'amount_satoshis'=>0,'status'=>'earned','description'=>'One-time Kosher Market Vendor Verification & Onboarding Fee.','earned_at'=>now()]);
            $locked->vendor()->update(['status'=>'active']);return $locked;
        });
    }
}
