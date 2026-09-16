<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Storage;

class SearchController extends Controller
{
    private function validatedSearch(Request $request): array
    {
        return $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:100'],
            'locale' => ['nullable', 'string', 'max:10'],
            'category_id' => ['nullable', 'integer'],
            'vendor_id' => ['nullable', 'integer'],
            'min_price' => ['nullable', 'numeric', 'min:0'],
            'max_price' => ['nullable', 'numeric', 'min:0'],
            'min_rating' => ['nullable', 'numeric', 'between:0,5'],
            'sort' => ['nullable', 'in:relevance,price_asc,price_desc,rating,popular,newest'],
        ]);
    }

    private function productQuery(array $data)
    {
        $query = trim($data['q']);
        $locale = $data['locale'] ?? App::getLocale();
        $builder = Product::query()
            ->where('status', 1)
            ->whereHas('vendor', fn ($q) => $q->where('status', 'active'))
            ->whereHas('translations', function ($q) use ($query, $locale) {
                $q->where('name', 'like', "%{$query}%")->where('language_code', $locale);
            })
            ->with(['translations' => fn ($q) => $q->where('language_code', $locale)->select('product_id', 'name', 'description'), 'thumbnail', 'vendor'])
            ->withCount('orders')
            ->withAvg('reviews', 'rating');

        if (! empty($data['category_id'])) {
            $builder->where('category_id', $data['category_id']);
        }
        if (! empty($data['vendor_id'])) {
            $builder->where('vendor_id', $data['vendor_id']);
        }
        if (isset($data['min_price'])) {
            $builder->where('price', '>=', $data['min_price']);
        }
        if (isset($data['max_price'])) {
            $builder->where('price', '<=', $data['max_price']);
        }
        if (isset($data['min_rating'])) {
            $builder->having('reviews_avg_rating', '>=', $data['min_rating']);
        }

        match ($data['sort'] ?? 'relevance') {
            'price_asc' => $builder->orderBy('price'),
            'price_desc' => $builder->orderByDesc('price'),
            'rating' => $builder->orderByDesc('reviews_avg_rating'),
            'popular' => $builder->orderByDesc('orders_count'),
            'newest' => $builder->latest(),
            default => $builder->orderByDesc('orders_count')->orderByDesc('reviews_avg_rating'),
        };

        return $builder;
    }

    public function suggestions(Request $request)
    {
        $data = $this->validatedSearch($request);
        $query = trim($data['q']);
        $locale = $data['locale'] ?? App::getLocale();
        $products = Product::query()->where('status', 1)->whereHas('vendor', fn ($q) => $q->where('status', 'active'))->whereHas('translations', fn ($q) => $q->where('name', 'like', "%{$query}%")->where('language_code', $locale))->with(['translations' => fn ($q) => $q->where('language_code', $locale)->select('product_id', 'name'), 'thumbnail'])->limit(10)->get(['id', 'slug']);

        return response()->json($products->map(fn ($product) => ['id' => $product->id, 'slug' => $product->slug, 'thumbnail' => $product->thumbnail ? Storage::url($product->thumbnail->image_url) : asset('default-thumbnail.jpg'), 'name' => $product->translations->first()->name ?? null]));
    }

    public function searchResults(Request $request)
    {
        $data = $this->validatedSearch($request);
        $products = $this->productQuery($data)->paginate(20)->withQueryString();

        return view('search-results', ['products' => $products, 'query' => trim($data['q']), 'filters' => $data]);
    }
}
