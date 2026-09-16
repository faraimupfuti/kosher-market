<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VendorShippingRate extends Model
{
    protected $fillable = [
        'shipping_zone_id', 'service_name', 'price_btc', 'free_shipping_threshold_btc',
        'min_delivery_days', 'max_delivery_days', 'tracking_url_template', 'enabled',
    ];

    protected $casts = [
        'price_btc' => 'decimal:8',
        'free_shipping_threshold_btc' => 'decimal:8',
        'enabled' => 'boolean',
    ];

    public function zone()
    {
        return $this->belongsTo(VendorShippingZone::class, 'shipping_zone_id');
    }
}
