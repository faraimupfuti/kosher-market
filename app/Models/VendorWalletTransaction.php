<?php

namespace App\Models;

use App\Services\MoneroAmount;
use Illuminate\Database\Eloquent\Model;

class VendorWalletTransaction extends Model
{
    protected $fillable=['vendor_wallet_id','type','amount_satoshis','balance_after_satoshis','reference','status','escrow_transaction_id','bitcoin_settlement_id','txid','metadata'];
    protected $casts=['amount_satoshis'=>'integer','balance_after_satoshis'=>'integer','metadata'=>'array'];
    public function wallet(){return $this->belongsTo(VendorWallet::class,'vendor_wallet_id');}
    public function escrow(){return $this->belongsTo(EscrowTransaction::class,'escrow_transaction_id');}
    public function settlement(){return $this->belongsTo(BitcoinSettlement::class,'bitcoin_settlement_id');}
    public function amountXmr():string{return MoneroAmount::fromAtomic((string)($this->metadata['atomic_amount']??'0'));}
}
