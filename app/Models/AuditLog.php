<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $fillable = ['actor_type', 'actor_id', 'action', 'route', 'method', 'ip_address', 'user_agent', 'metadata'];

    protected $casts = ['metadata' => 'array'];
}
