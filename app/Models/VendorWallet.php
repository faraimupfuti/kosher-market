<?php

namespace App\Models;

use App\Services\MoneroAmount;
use Illuminate\Database\Eloquent\Model;

class VendorWallet extends Model
{
    protected $fillable=['vendor_id','crypto','deposit_address','available_satoshis','locked_satoshis','xmr_deposit_address','xmr_atomic_available','xmr_atomic_locked'];
    protected $casts=['available_satoshis'=>'integer','locked_satoshis'=>'integer','xmr_atomic_available'=>'string','xmr_atomic_locked'=>'string'];
    public function vendor(){return $this->belongsTo(Vendor::class);}
    public function transactions(){return $this->hasMany(VendorWalletTransaction::class);}
    public function availableXmr():string{return MoneroAmount::fromAtomic((string)$this->xmr_atomic_available);}
    public function lockedXmr():string{return MoneroAmount::fromAtomic((string)$this->xmr_atomic_locked);}
}
