<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformRevenueEntry extends Model
{
    protected $fillable = [
        'type', 'vendor_id', 'order_id', 'escrow_transaction_id',
        'amount_btc', 'amount_satoshis', 'reference', 'status',
        'description', 'earned_at', 'reversed_at',
    ];

    protected $casts = [
        'amount_btc' => 'decimal:8',
        'amount_satoshis' => 'integer',
        'earned_at' => 'datetime',
        'reversed_at' => 'datetime',
    ];

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function escrowTransaction()
    {
        return $this->belongsTo(EscrowTransaction::class);
    }
}
