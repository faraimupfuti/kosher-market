<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\Vendor;
use App\Services\VendorTrustService;

class VendorProfileController extends Controller
{
    public function show(string $pseudonym, VendorTrustService $trust)
    {
        $vendor = Vendor::where('pseudonym', $pseudonym)->where('status', 'active')->firstOrFail();
        $trustScore = $trust->refresh($vendor);
        $trustBadge = $trust->badge($vendor->fresh());
        $productIds = Product::where('vendor_id', $vendor->id)->pluck('id');
        $reviews = ProductReview::approved()->whereIn('product_id', $productIds);
        $completedOrders = Order::where('vendor_id', $vendor->id)->whereIn('status', ['completed', 'delivered', 'released'])->count();
        $rating = round((float) ($reviews->avg('rating') ?: 0), 2);
        $reviewCount = $reviews->count();
        $products = Product::where('vendor_id', $vendor->id)->where('status', 1)->latest()->paginate(24);

        return view('vendors.public-profile', compact('vendor', 'completedOrders', 'rating', 'reviewCount', 'products', 'trustScore', 'trustBadge'));
    }
}
