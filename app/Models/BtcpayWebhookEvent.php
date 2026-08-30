<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BtcpayWebhookEvent extends Model
{
    protected $fillable = [
        'delivery_id', 'event_fingerprint', 'event_type', 'invoice_id', 'payout_id',
        'processed_at', 'processing_error',
    ];

    protected $casts = ['processed_at' => 'datetime'];
}
