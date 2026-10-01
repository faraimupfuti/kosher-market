<?php

namespace App\Services;

use App\Models\Product;
use App\Models\VendorShippingRate;
use Illuminate\Validation\ValidationException;

class GlobalShippingService
{
    public function options(Product $product,string $countryCode,string $cartSubtotalXmr='0'):array
    {
        $countryCode=strtoupper(trim($countryCode));
        if($product->shippingCountries()->exists()&&!$product->shippingCountries()->where('code',$countryCode)->exists())throw ValidationException::withMessages(['shipping_country'=>'This product is not available for shipping to the selected country.']);
        $rates=VendorShippingRate::query()->where('enabled',true)->whereHas('zone',function($query)use($product,$countryCode){$query->where('vendor_id',$product->vendor_id)->where('enabled',true)->whereHas('countries',fn($q)=>$q->where('code',$countryCode));})->get();
        return $rates->map(function(VendorShippingRate $rate)use($cartSubtotalXmr){
            $price=(string)($rate->price_xmr??'0'); $threshold=$rate->free_shipping_threshold_xmr;
            $free=$threshold!==null&&bccomp($cartSubtotalXmr,(string)$threshold,12)>=0;
            return ['id'=>$rate->id,'service_name'=>$rate->service_name,'price_xmr'=>$free?'0.000000000000':MoneroAmount::normalize($price),'min_delivery_days'=>$rate->min_delivery_days,'max_delivery_days'=>$rate->max_delivery_days,'tracking_url_template'=>$rate->tracking_url_template];
        })->values()->all();
    }
}
