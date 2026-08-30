<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EscrowLedgerEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'escrow_transaction_id', 'user_id', 'type', 'amount',
        'currency', 'reference', 'metadata',
    ];

    // Bitcoin ledger amounts require the full 8 decimal places.
    protected $casts = ['amount' => 'decimal:8', 'metadata' => 'array'];

    public function escrowTransaction() { return $this->belongsTo(EscrowTransaction::class); }
    public function user() { return $this->belongsTo(User::class); }
}
