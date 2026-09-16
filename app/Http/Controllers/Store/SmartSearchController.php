<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class SmartSearchController extends Controller
{
    public function interpret(Request $request)
    {
        $text = trim($request->input('q', ''));
        abort_if(mb_strlen($text) < 2 || mb_strlen($text) > 200, 422, 'Enter a search of 2–200 characters.');
        $filters = ['q' => $text];
        if (preg_match('/(?:under|below|less than)\s+([0-9]+(?:\.[0-9]+)?)\s*(?:btc)?/i', $text, $m)) {
            $filters['max_price'] = $m[1];
        }
        if (preg_match('/(?:over|above|more than)\s+([0-9]+(?:\.[0-9]+)?)\s*(?:btc)?/i', $text, $m)) {
            $filters['min_price'] = $m[1];
        }
        if (preg_match('/(?:rating|rated)\s*(?:of|at least)?\s*([0-5](?:\.[0-9])?)/i', $text, $m)) {
            $filters['min_rating'] = $m[1];
        }
        if (stripos($text, 'cheapest') !== false || stripos($text, 'cheap') !== false) {
            $filters['sort'] = 'price_asc';
        } elseif (stripos($text, 'best rated') !== false || stripos($text, 'highest rated') !== false) {
            $filters['sort'] = 'rating';
        } elseif (stripos($text, 'popular') !== false || stripos($text, 'best selling') !== false) {
            $filters['sort'] = 'popular';
        } else {
            $filters['sort'] = 'relevance';
        }

        return response()->json(['message' => 'Search interpreted', 'filters' => $filters, 'url' => route('search.results', $filters)]);
    }
}
