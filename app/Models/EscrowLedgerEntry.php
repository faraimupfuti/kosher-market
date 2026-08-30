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

    protected $casts = ['amount' => 'decimal:2', 'metadata' => 'array'];

    public function escrowTransaction() { return $this->belongsTo(EscrowTransaction::class); }
    public function user() { return $this->belongsTo(User::class); }
}
