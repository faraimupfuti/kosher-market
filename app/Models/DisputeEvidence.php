<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DisputeEvidence extends Model
{
    protected $table = 'dispute_evidence';
    protected $fillable = ['dispute_id','customer_id','vendor_id','path','original_name','mime','size','note'];
    public function dispute() { return $this->belongsTo(Dispute::class); }
}
