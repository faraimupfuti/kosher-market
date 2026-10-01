<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShkeeperWebhookEvent extends Model
{
    protected $fillable = [
        'event_fingerprint', 'external_id', 'status', 'txid', 'processed_at', 'processing_error',
    ];

    protected $casts = ['processed_at' => 'datetime'];
}
