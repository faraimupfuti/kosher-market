<?php

namespace App\Http\Controllers;

use App\Models\Banner;
use App\Models\Category;
use App\Models\Product;
use App\Models\Vendor;

class StoreController extends Controller
{
    public function index()
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

        $completedStatuses = ['completed', 'delivered', 'released'];
        $performance = fn () => Vendor::where('status', 'active')
            ->withCount('approvedReviews')
            ->withAvg('approvedReviews', 'rating')
            ->withCount([
                'orders as completed_orders_count' => fn ($query) => $query->whereIn('status', $completedStatuses),
            ])
            ->withSum([
                'orders as completed_sales' => fn ($query) => $query->whereIn('status', $completedStatuses),
            ], 'total_price');

        $bestSellingVendors = $performance()
            ->orderByDesc('completed_sales')
            ->orderByDesc('completed_orders_count')
            ->orderByDesc('id')
            ->take(6)
            ->get();

        $bestRatedVendors = $performance()
            ->having('approved_reviews_count', '>=', 3)
            ->orderByDesc('approved_reviews_avg_rating')
            ->orderByDesc('approved_reviews_count')
            ->orderByDesc('id')
            ->take(6)
            ->get();

        $worstSellingVendors = $performance()
            ->orderBy('completed_sales')
            ->orderBy('completed_orders_count')
            ->orderBy('id')
            ->take(6)
            ->get();

        return view('themes.xylo.home', compact(
            'banners',
            'categories',
            'products',
            'bestSellingVendors',
            'bestRatedVendors',
            'worstSellingVendors'
        ));
    }
}
