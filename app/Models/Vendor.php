<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Validation\ValidationException;

class Vendor extends Authenticatable
{
    use Notifiable;

    protected $guard = 'vendor';

    protected $fillable = ['name', 'pseudonym', 'email', 'password', 'phone', 'status', 'profile_image', 'bitcoin_payout_address', 'bitcoin_payout_address_verified_at'];

    protected $hidden = ['password', 'email', 'phone', 'bitcoin_payout_address'];

    protected $casts = ['password' => 'hashed', 'bitcoin_payout_address_verified_at' => 'datetime'];

    protected static function booted(): void
    {
        static::saving(function (Vendor $vendor) {
            if ($vendor->pseudonym === null) return;
            $normalized = strtolower(trim($vendor->pseudonym));
            if (!preg_match('/^[a-z0-9][a-z0-9_.-]{2,39}$/', $normalized)) {
                throw ValidationException::withMessages(['pseudonym' => 'Pseudonyms must be 3–40 characters and use letters, numbers, dots, underscores or hyphens.']);
            }
            $reserved = ['admin','administrator','support','security','help','official','staff','system','koshermarket','kosher-market','bitcoin','btc','satoshi','escrow','payments','payment','moderator'];
            if (in_array($normalized, $reserved, true)) {
                throw ValidationException::withMessages(['pseudonym' => 'This pseudonym is reserved and cannot be used.']);
            }
            $vendor->pseudonym = $normalized;
        });
    }

    public function escrowTransactions()
    {
        return $this->hasMany(EscrowTransaction::class, 'vendor_id');
    }
}
