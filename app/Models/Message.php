<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    protected $fillable = [
        'conversation_id', 'sender_customer_id', 'sender_vendor_id', 'body',
        'read_at', 'attachment_path', 'attachment_name', 'attachment_mime', 'attachment_size',
    ];

    protected $casts = ['read_at' => 'datetime', 'attachment_size' => 'integer'];

    public function conversation() { return $this->belongsTo(Conversation::class); }
    public function customer() { return $this->belongsTo(Customer::class, 'sender_customer_id'); }
    public function vendor() { return $this->belongsTo(Vendor::class, 'sender_vendor_id'); }
}
