<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BitcoinSettlement extends Model
{
    use HasFactory;

    protected $fillable = [
        'escrow_transaction_id', 'vendor_id', 'type', 'amount', 'currency',
        'destination_address', 'status', 'btcpay_payout_id', 'bitcoin_txid',
        'error_message', 'submitted_at', 'approved_at', 'completed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:8',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function escrow()
    {
        return $this->belongsTo(EscrowTransaction::class, 'escrow_transaction_id');
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }
}
