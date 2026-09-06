<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SavedSearch extends Model
{
    protected $fillable = ['customer_id','name','query','filters','notify_enabled'];
    protected $casts = ['filters' => 'array','notify_enabled' => 'boolean'];
    public function customer(){return $this->belongsTo(Customer::class);}
}
