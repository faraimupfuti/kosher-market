<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EscrowTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id', 'payment_id', 'buyer_id', 'seller_id', 'amount',
        'platform_fee', 'seller_amount', 'currency', 'status',
        'funded_at', 'release_due_at', 'released_at', 'refunded_at',
        'release_note', 'refund_note',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'platform_fee' => 'decimal:2',
        'seller_amount' => 'decimal:2',
        'funded_at' => 'datetime',
        'release_due_at' => 'datetime',
        'released_at' => 'datetime',
        'refunded_at' => 'datetime',
    ];

    public function order() { return $this->belongsTo(Order::class); }
    public function payment() { return $this->belongsTo(Payment::class); }
    public function buyer() { return $this->belongsTo(User::class, 'buyer_id'); }
    public function seller() { return $this->belongsTo(User::class, 'seller_id'); }
    public function ledgerEntries() { return $this->hasMany(EscrowLedgerEntry::class); }
    public function disputes() { return $this->hasMany(EscrowDispute::class); }
}
