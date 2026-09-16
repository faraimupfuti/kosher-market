<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\SavedSearch;
use Illuminate\Http\Request;

class SavedSearchController extends Controller
{
    public function index(Request $request)
    {
        return view('saved-searches.index', ['savedSearches' => $request->user('customer')->savedSearches()->latest()->get()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:100', 'query' => 'nullable|string|max:100', 'filters' => 'nullable|array', 'notify_enabled' => 'nullable|boolean']);
        $data['customer_id'] = $request->user('customer')->id;
        $saved = SavedSearch::create($data);

        return response()->json(['id' => $saved->id, 'message' => 'Search saved.']);
    }

    public function destroy(Request $request, SavedSearch $savedSearch)
    {
        abort_unless((int) $savedSearch->customer_id === (int) $request->user('customer')->id, 403);
        $savedSearch->delete();

        return back()->with('success', 'Saved search deleted.');
    }
}
