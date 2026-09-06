<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Dispute extends Model
{
    protected $fillable = ['order_id','conversation_id','customer_id','vendor_id','reason','description','status','priority','sla_due_at','resolution','resolution_note','resolved_by','resolved_at'];
    protected $casts = ['sla_due_at' => 'datetime', 'resolved_at' => 'datetime'];
    public function order() { return $this->belongsTo(Order::class); }
    public function conversation() { return $this->belongsTo(Conversation::class); }
    public function customer() { return $this->belongsTo(Customer::class); }
    public function vendor() { return $this->belongsTo(Vendor::class); }
    public function evidence() { return $this->hasMany(DisputeEvidence::class); }
    public function resolver() { return $this->belongsTo(User::class, 'resolved_by'); }
}
