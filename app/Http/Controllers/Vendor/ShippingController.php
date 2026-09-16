<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\VendorShippingRate;
use App\Models\VendorShippingZone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ShippingController extends Controller
{
    public function index()
    {
        $vendor = Auth::guard('vendor')->user();
        $countries = Country::where('enabled', true)->orderBy('name')->get(['id', 'code', 'name']);
        $zones = VendorShippingZone::with(['countries', 'rates'])->where('vendor_id', $vendor->id)->orderBy('name')->get();

        return view('vendor.shipping.index', compact('countries', 'zones'));
    }

    public function storeZone(Request $request)
    {
        $vendor = Auth::guard('vendor')->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'countries' => ['required', 'array', 'min:1'],
            'countries.*' => ['required', 'integer', 'exists:countries,id'],
        ]);
        DB::transaction(function () use ($vendor, $data) {
            $zone = VendorShippingZone::create(['vendor_id' => $vendor->id, 'name' => $data['name'], 'enabled' => true]);
            $zone->countries()->sync(array_map('intval', $data['countries']));
        });

        return back()->with('success', 'Shipping zone created.');
    }

    public function storeRate(Request $request)
    {
        $vendor = Auth::guard('vendor')->user();
        $data = $request->validate([
            'shipping_zone_id' => ['required', 'integer', 'exists:vendor_shipping_zones,id'],
            'service_name' => ['required', 'string', 'max:100'],
            'price_btc' => ['required', 'numeric', 'min:0', 'max:21000000'],
            'free_shipping_threshold_btc' => ['nullable', 'numeric', 'min:0', 'max:21000000'],
            'min_delivery_days' => ['required', 'integer', 'min:0', 'max:365'],
            'max_delivery_days' => ['required', 'integer', 'gte:min_delivery_days', 'max:365'],
            'tracking_url_template' => ['nullable', 'url', 'max:500'],
        ]);
        $zone = VendorShippingZone::whereKey($data['shipping_zone_id'])->where('vendor_id', $vendor->id)->firstOrFail();
        VendorShippingRate::create($data + ['enabled' => true]);

        return back()->with('success', 'Shipping rate created.');
    }

    public function destroyZone(VendorShippingZone $zone)
    {
        abort_unless($zone->vendor_id === Auth::guard('vendor')->id(), 403);
        $zone->delete();

        return back()->with('success', 'Shipping zone deleted.');
    }

    public function destroyRate(VendorShippingRate $rate)
    {
        abort_unless($rate->zone()->where('vendor_id', Auth::guard('vendor')->id())->exists(), 403);
        $rate->delete();

        return back()->with('success', 'Shipping rate deleted.');
    }
}
