<?php

namespace App\Services;

use App\Models\Product;
use App\Models\VendorShippingRate;
use Illuminate\Validation\ValidationException;

class GlobalShippingService
{
    /**
     * Return active shipping options for a product and destination country.
     * Products with no explicit country restrictions remain globally shippable,
     * while products with configured countries are restricted to those countries.
     */
    public function options(Product $product, string $countryCode, float $cartSubtotalBtc = 0): array
    {
        $countryCode = strtoupper(trim($countryCode));

        if ($product->shippingCountries()->exists() && !$product->shippingCountries()->where('code', $countryCode)->exists()) {
            throw ValidationException::withMessages([
                'shipping_country' => 'This product is not available for shipping to the selected country.',
            ]);
        }

        $rates = VendorShippingRate::query()
            ->where('enabled', true)
            ->whereHas('zone', function ($query) use ($product, $countryCode) {
                $query->where('vendor_id', $product->vendor_id)
                    ->where('enabled', true)
                    ->whereHas('countries', fn ($q) => $q->where('code', $countryCode));
            })
            ->get();

        return $rates->map(function (VendorShippingRate $rate) use ($cartSubtotalBtc) {
            $free = $rate->free_shipping_threshold_btc !== null
                && $cartSubtotalBtc >= (float) $rate->free_shipping_threshold_btc;

            return [
                'id' => $rate->id,
                'service_name' => $rate->service_name,
                'price_btc' => $free ? 0.0 : (float) $rate->price_btc,
                'min_delivery_days' => $rate->min_delivery_days,
                'max_delivery_days' => $rate->max_delivery_days,
                'tracking_url_template' => $rate->tracking_url_template,
            ];
        })->values()->all();
    }
}
