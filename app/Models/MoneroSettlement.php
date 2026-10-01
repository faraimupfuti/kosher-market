<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MoneroSettlement extends Model
{
    protected $table='bitcoin_settlements';
    use HasFactory;
    protected $fillable=['escrow_transaction_id','vendor_id','type','amount','currency','xmr_amount','destination_address','status','shkeeper_payout_id','xmr_txid','error_message','submitted_at','approved_at','completed_at'];
    protected $casts=['amount'=>'decimal:12','xmr_amount'=>'decimal:12','submitted_at'=>'datetime','approved_at'=>'datetime','completed_at'=>'datetime'];
    public function escrow(){return $this->belongsTo(EscrowTransaction::class,'escrow_transaction_id');}
    public function vendor(){return $this->belongsTo(Vendor::class);}
}
