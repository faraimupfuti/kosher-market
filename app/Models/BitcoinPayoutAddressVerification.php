<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BitcoinPayoutAddressVerification extends Model
{
    protected $fillable = [
        'vendor_id', 'address', 'token_hash', 'expires_at', 'verified_at', 'invalidated_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'verified_at' => 'datetime',
        'invalidated_at' => 'datetime',
    ];

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }
}
