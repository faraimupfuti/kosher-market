<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $table = 'orders';

    protected $fillable = ['order_date', 'status', 'total_amount', 'shipping_address', 'billing_address', 'payment_method', 'payment_status', 'shipping_method', 'tracking_number', 'product_id', 'customer_id', 'vendor_id', 'quantity', 'unit_price', 'discount_amount', 'coupon_code', 'currency', 'created_at', 'updated_at', 'shipping_rate_id', 'shipping_country', 'shipping_full_name', 'shipping_address_line1', 'shipping_address_line2', 'shipping_city', 'shipping_state', 'shipping_postal_code', 'shipping_cost_btc', 'estimated_delivery_min_days', 'estimated_delivery_max_days'];

    protected $casts = ['total_amount' => 'decimal:8', 'unit_price' => 'decimal:8', 'discount_amount' => 'decimal:8', 'shipping_cost_btc' => 'decimal:8'];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function details()
    {
        return $this->hasMany(OrderDetail::class, 'order_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function escrow()
    {
        return $this->hasOne(EscrowTransaction::class);
    }

    public function shippingRate()
    {
        return $this->belongsTo(VendorShippingRate::class, 'shipping_rate_id');
    }

    public function shippingCountry()
    {
        return $this->belongsTo(Country::class, 'shipping_country', 'code');
    }

    public function trackingEvents()
    {
        return $this->hasMany(OrderTrackingEvent::class, 'order_id');
    }

    public function disputes()
    {
        return $this->hasMany(Dispute::class, 'order_id');
    }

    public function getTrackingUrlAttribute(): ?string
    {
        if (! $this->tracking_number || ! $this->shippingRate?->tracking_url_template) {
            return null;
        }

        return str_replace('{tracking_number}', rawurlencode($this->tracking_number), $this->shippingRate->tracking_url_template);
    }
}
