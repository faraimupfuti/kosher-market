<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\ProductReview;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReviewController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'review' => ['nullable', 'string', 'max:500'],
        ]);

        $customerId = Auth::guard('customer')->id();
        abort_unless($customerId, 401);

        if (ProductReview::where('product_id', $data['product_id'])->where('customer_id', $customerId)->exists()) {
            return back()->with('error', __('store.product_detail.review_already_submitted'));
        }

        $verifiedPurchase = Order::where('customer_id', $customerId)
            ->where('product_id', $data['product_id'])
            ->where('status', 'completed')
            ->exists();

        if (!$verifiedPurchase) {
            return back()->with('error', 'You can review this product only after completing a purchase.');
        }

        ProductReview::create([
            'customer_id' => $customerId,
            'product_id' => $data['product_id'],
            'rating' => $data['rating'],
            'review' => $data['review'] ?? null,
            'is_approved' => false,
        ]);

        return back()->with('success', 'Review submitted for moderation.');
    }
}
