<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProductShippingController extends Controller
{
    public function index(Product $product)
    {
        abort_unless((int) $product->vendor_id === (int) Auth::guard('vendor')->id(), 403);
        $countries = Country::where('enabled', true)->orderBy('name')->get(['id', 'code', 'name']);
        $selected = $product->shippingCountries()->pluck('countries.id')->all();

        return view('vendor.shipping.product', compact('product', 'countries', 'selected'));
    }

    public function update(Request $request, Product $product)
    {
        abort_unless((int) $product->vendor_id === (int) Auth::guard('vendor')->id(), 403);
        $data = $request->validate(['countries' => ['nullable', 'array'], 'countries.*' => ['integer', 'exists:countries,id']]);
        $product->shippingCountries()->sync(array_map('intval', $data['countries'] ?? []));

        return back()->with('success', 'Product shipping destinations updated.');
    }
}
