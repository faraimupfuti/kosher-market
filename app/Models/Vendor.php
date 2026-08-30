<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Vendor extends Authenticatable
{
    use Notifiable;

    protected $guard = 'vendor';

    protected $fillable = ['name', 'email', 'password', 'phone', 'status', 'profile_image', 'bitcoin_payout_address', 'bitcoin_payout_address_verified_at'];

    protected $hidden = ['password'];

    protected $casts = ['password' => 'hashed', 'bitcoin_payout_address_verified_at' => 'datetime'];

    public function escrowTransactions()
    {
        return $this->hasMany(EscrowTransaction::class, 'vendor_id');
    }
}
