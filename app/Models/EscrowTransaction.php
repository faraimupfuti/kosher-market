<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EscrowTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id', 'payment_id', 'buyer_id', 'vendor_id', 'amount',
        'platform_fee', 'seller_amount', 'currency', 'status',
        'funded_at', 'release_due_at', 'released_at', 'refunded_at',
        'release_note', 'refund_note', 'btcpay_invoice_id',
        'bitcoin_payment_address', 'bitcoin_txid', 'bitcoin_amount',
        'bitcoin_confirmations', 'payment_detected_at', 'payment_confirmed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:8', 'platform_fee' => 'decimal:8', 'seller_amount' => 'decimal:8',
        'bitcoin_amount' => 'decimal:8', 'bitcoin_confirmations' => 'integer',
        'funded_at' => 'datetime', 'release_due_at' => 'datetime', 'released_at' => 'datetime',
        'refunded_at' => 'datetime', 'payment_detected_at' => 'datetime', 'payment_confirmed_at' => 'datetime',
    ];

    public function order() { return $this->belongsTo(Order::class); }
    public function payment() { return $this->belongsTo(Payment::class); }
    public function buyer() { return $this->belongsTo(Customer::class, 'buyer_id'); }
    public function vendor() { return $this->belongsTo(Vendor::class, 'vendor_id'); }
    public function ledgerEntries() { return $this->hasMany(EscrowLedgerEntry::class); }
    public function disputes() { return $this->hasMany(EscrowDispute::class); }
}
