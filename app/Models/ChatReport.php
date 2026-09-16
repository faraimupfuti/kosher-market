<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatReport extends Model
{
    protected $fillable = ['conversation_id', 'reporter_customer_id', 'reporter_vendor_id', 'reason', 'details', 'status', 'resolution_note'];

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'reporter_customer_id');
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class, 'reporter_vendor_id');
    }
}
