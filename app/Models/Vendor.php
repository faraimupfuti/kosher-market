<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Validation\ValidationException;

class Vendor extends Authenticatable
{
    use Notifiable;

    protected $guard = 'vendor';

    protected $fillable = ['name', 'pseudonym', 'email', 'password', 'phone', 'status', 'profile_image', 'bitcoin_payout_address', 'bitcoin_payout_address_verified_at', 'banned_at', 'ban_reason', 'verification_status', 'trust_score', 'verified_at', 'response_rate', 'dispute_rate', 'refund_rate', 'fulfillment_rate'];

    protected $hidden = ['password', 'email', 'phone', 'bitcoin_payout_address'];

    protected $casts = [
        'password' => 'hashed',
        'bitcoin_payout_address_verified_at' => 'datetime',
        'pseudonym_changed_at' => 'datetime',
        'banned_at' => 'datetime',
        'verified_at' => 'datetime',
        'trust_score' => 'integer',
        'response_rate' => 'decimal:2',
        'dispute_rate' => 'decimal:2',
        'refund_rate' => 'decimal:2',
        'fulfillment_rate' => 'decimal:2',
    ];

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
            if ($vendor->exists && $vendor->isDirty('pseudonym') && $vendor->getOriginal('pseudonym') && $vendor->pseudonym_changed_at?->gt(now()->subDays(30))) {
                throw ValidationException::withMessages(['pseudonym' => 'For marketplace safety, a vendor pseudonym can only be changed once every 30 days.']);
            }
            if ($vendor->exists && $vendor->isDirty('pseudonym')) $vendor->pseudonym_changed_at = now();
            $vendor->pseudonym = $normalized;
        });
    }

    public function products() { return $this->hasMany(Product::class); }
    public function orders() { return $this->hasMany(Order::class); }
    public function approvedReviews() { return $this->hasManyThrough(ProductReview::class, Product::class, 'vendor_id', 'product_id', 'id', 'id')->where('product_reviews.is_approved', true); }
    public function escrowTransactions() { return $this->hasMany(EscrowTransaction::class, 'vendor_id'); }
    public function isSellingEnabled(): bool { return $this->status === 'active'; }
    public function trustBadge(): string
    {
        if ($this->verification_status === 'verified' && $this->trust_score >= 85) return 'Verified Vendor';
        if ($this->trust_score >= 75) return 'Established Vendor';
        if ($this->trust_score >= 60) return 'Developing Vendor';
        return 'New Vendor';
    }
}
