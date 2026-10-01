<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VendorRegistrationFee extends Model
{
    use HasFactory;
    protected $fillable=['vendor_id','currency','usd_amount','btc_amount','btc_satoshis','xmr_amount','xmr_satoshis_legacy','xmr_txid','xmr_payment_address','xmr_confirmations','status','shkeeper_invoice_id','checkout_url','bitcoin_txid','bitcoin_payment_address','bitcoin_confirmations','payment_detected_at','paid_at','error_message'];
    protected $casts=['usd_amount'=>'decimal:2','btc_amount'=>'decimal:12','xmr_amount'=>'decimal:12','xmr_confirmations'=>'integer','payment_detected_at'=>'datetime','paid_at'=>'datetime'];
    public function vendor(){return $this->belongsTo(Vendor::class);}
}
