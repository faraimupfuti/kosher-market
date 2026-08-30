<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $table = 'orders';

    protected $fillable = [
        'order_date', 'status', 'total_price', 'shipping_address', 'billing_address',
        'payment_method', 'payment_status', 'shipping_method', 'tracking_number',
        'product_id', 'customer_id', 'vendor_id', 'quantity', 'unit_price',
        'discount_amount', 'coupon_code', 'currency', 'created_at', 'updated_at',
    ];

    protected $casts = ['total_price' => 'decimal:8', 'unit_price' => 'decimal:8', 'discount_amount' => 'decimal:8'];

    public function product() { return $this->belongsTo(Product::class); }
    public function details() { return $this->hasMany(OrderDetail::class, 'order_id'); }
    public function customer() { return $this->belongsTo(Customer::class); }
    public function vendor() { return $this->belongsTo(Vendor::class); }
    public function escrow() { return $this->hasOne(EscrowTransaction::class); }
}
