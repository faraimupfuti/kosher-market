<?php

namespace App\Http\Controllers;

use App\Models\Banner;
use App\Models\Category;
use App\Models\Product;
use App\Services\VendorPerformanceService;

class StoreController extends Controller
{
    public function index(VendorPerformanceService $vendorPerformance)
    {
        $banners = Banner::where('status', 1)
            ->with('translation')
            ->orderBy('id', 'desc')
            ->take(3)
            ->get();

        $categories = Category::where('status', 1)
            ->with('translation')
            ->orderBy('id', 'desc')
            ->take(10)
            ->get();

        $products = Product::where('status', 1)
            ->whereHas('vendor', fn ($query) => $query->where('status', 'active'))
            ->with(['translation', 'thumbnail', 'primaryVariant'])
            ->withCount('reviews')
            ->orderBy('id', 'desc')
            ->take(10)
            ->get();

        $rankings = $vendorPerformance->rankings(6);

        return view('themes.xylo.home', [
            'banners' => $banners,
            'categories' => $categories,
            'products' => $products,
            'bestSellingVendors' => $rankings['bestSelling'],
            'bestRatedVendors' => $rankings['bestRated'],
            'worstSellingVendors' => $rankings['worstSelling'],
            'topPerformers' => $rankings['topPerformers'],
        ]);
    }
}
