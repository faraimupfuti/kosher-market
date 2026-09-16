<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EscrowDispute extends Model
{
    use HasFactory;

    protected $fillable = [
        'escrow_transaction_id', 'opened_by', 'reason', 'description',
        'status', 'resolved_by', 'resolution_note', 'resolved_at',
    ];

    protected $casts = ['resolved_at' => 'datetime'];

    public function escrowTransaction()
    {
        return $this->belongsTo(EscrowTransaction::class);
    }

    public function opener()
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function resolver()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
