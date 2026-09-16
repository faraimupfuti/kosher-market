<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function show($slug)
    {
        $product = Product::with([
            'attributeValues.attribute', 'attributeValues.translations', 'translations', 'reviews',
            'primaryVariant', 'variants.attributeValues', 'images', 'category.translation', 'category.parent.translation',
        ])->withAvg('reviews', 'rating')->withCount('reviews')
            ->where('slug', $slug)->where('status', 1)
            ->whereHas('vendor', fn ($query) => $query->where('status', 'active'))->firstOrFail();

        $primaryVariant = $product->variants()->where('is_primary', true)->first();
        $inStock = $primaryVariant && $primaryVariant->stock > 0;
        $variantMap = $product->variants->map(fn ($variant) => [
            'id' => $variant->id,
            'attributes' => $variant->attributeValues->pluck('id')->sort()->values()->toArray(),
        ]);
        $breadcrumbs = [];
        $category = $product->category;
        while ($category) {
            $breadcrumbs[] = $category;
            $category = $category->parent;
        }
        $breadcrumbs = array_reverse($breadcrumbs);

        return view('themes.xylo.product-detail', compact('product', 'inStock', 'variantMap', 'breadcrumbs'));
    }

    public function messageVendor(Request $request, $slug)
    {
        abort_unless($request->user('customer'), 401);
        $product = Product::where('slug', $slug)->where('status', 1)
            ->whereHas('vendor', fn ($q) => $q->where('status', 'active'))->firstOrFail();

        return redirect()->route('chat.start', ['vendor' => $product->vendor_id, 'product_id' => $product->id]);
    }

    public function getVariantPrice(Request $request)
    {
        $variant = ProductVariant::with('product')->where('id', $request->input('variant_id'))->where('product_id', $request->input('product_id'))->first();
        if (! $variant) {
            return response()->json(['success' => false]);
        }

        return response()->json([
            'success' => true,
            'price' => number_format($variant->converted_price, 2),
            'stock' => $variant->stock > 0 ? __('store.product_detail.in_stock') : 'OUT OF STOCK',
            'is_out_of_stock' => $variant->stock <= 0,
            'currency_symbol' => activeCurrency()->symbol,
        ]);
    }
}
