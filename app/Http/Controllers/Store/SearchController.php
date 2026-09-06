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
        ]);
    }

    public function suggestions(Request $request)
    {
        $data = $this->validatedSearch($request);
        $query = trim($data['q']);
        $locale = $data['locale'] ?? App::getLocale();

        $products = Product::query()
            ->where('status', 1)
            ->whereHas('vendor', fn ($q) => $q->where('status', 'active'))
            ->whereHas('translations', function ($q) use ($query, $locale) {
                $q->where('name', 'like', "%{$query}%")
                    ->where('language_code', $locale);
            })
            ->with([
                'translations' => function ($q) use ($locale) {
                    $q->where('language_code', $locale)->select('product_id', 'name');
                },
                'thumbnail',
            ])
            ->limit(10)
            ->get(['id', 'slug']);

        return response()->json($products->map(function ($product) {
            return [
                'id' => $product->id,
                'slug' => $product->slug,
                'thumbnail' => $product->thumbnail
                    ? Storage::url($product->thumbnail->image_url)
                    : asset('default-thumbnail.jpg'),
                'name' => $product->translations->first()->name ?? null,
            ];
        }));
    }

    public function searchResults(Request $request)
    {
        $data = $this->validatedSearch($request);
        $query = trim($data['q']);
        $locale = $data['locale'] ?? App::getLocale();

        $products = Product::query()
            ->where('status', 1)
            ->whereHas('vendor', fn ($q) => $q->where('status', 'active'))
            ->whereHas('translations', function ($q) use ($query, $locale) {
                $q->where('name', 'like', "%{$query}%")
                    ->where('language_code', $locale);
            })
            ->with([
                'translations' => function ($q) use ($locale) {
                    $q->where('language_code', $locale)->select('product_id', 'name', 'description');
                },
                'thumbnail',
            ])
            ->paginate(10)
            ->withQueryString();

        return view('search-results', compact('products', 'query'));
    }
}
