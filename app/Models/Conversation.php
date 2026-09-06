<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    protected $fillable = ['customer_id', 'vendor_id', 'product_id', 'order_id', 'last_message_at', 'blocked_by_customer_at', 'blocked_by_vendor_at'];
    protected $casts = ['last_message_at' => 'datetime', 'blocked_by_customer_at' => 'datetime', 'blocked_by_vendor_at' => 'datetime'];

    public function customer() { return $this->belongsTo(Customer::class); }
    public function vendor() { return $this->belongsTo(Vendor::class); }
    public function product() { return $this->belongsTo(Product::class); }
    public function order() { return $this->belongsTo(Order::class); }
    public function messages() { return $this->hasMany(Message::class); }

    public function unreadMessagesFor(string $guard): int
    {
        return $this->messages()->whereNull('read_at')
            ->when($guard === 'customer', fn ($q) => $q->whereNotNull('sender_vendor_id'))
            ->when($guard === 'vendor', fn ($q) => $q->whereNotNull('sender_customer_id'))
            ->count();
    }

    public function isBlockedFor(string $guard): bool
    {
        return $guard === 'customer' ? $this->blocked_by_customer_at !== null : $this->blocked_by_vendor_at !== null;
    }
}
