<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VendorShippingZone extends Model
{
    protected $fillable = ['vendor_id', 'name', 'enabled'];

    protected $casts = ['enabled' => 'boolean'];

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function countries()
    {
        return $this->belongsToMany(Country::class, 'vendor_shipping_zone_countries', 'shipping_zone_id', 'country_id');
    }

    public function rates()
    {
        return $this->hasMany(VendorShippingRate::class, 'shipping_zone_id');
    }
}
