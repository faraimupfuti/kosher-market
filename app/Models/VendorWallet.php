<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VendorWallet extends Model
{
    protected $fillable = [
        'vendor_id', 'crypto', 'deposit_address', 'available_satoshis', 'locked_satoshis',
    ];

    protected $casts = [
        'available_satoshis' => 'integer',
        'locked_satoshis' => 'integer',
    ];

    public function vendor() { return $this->belongsTo(Vendor::class); }
    public function transactions() { return $this->hasMany(VendorWalletTransaction::class); }

    public function availableBtc(): string
    {
        return BitcoinAmount::fromSatoshis($this->available_satoshis);
    }

    public function lockedBtc(): string
    {
        return BitcoinAmount::fromSatoshis($this->locked_satoshis);
    }
}
