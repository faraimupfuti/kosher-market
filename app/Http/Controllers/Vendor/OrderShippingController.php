<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OrderShippingController extends Controller
{
    public function update(Request $request, Order $order)
    {
        abort_unless((int) $order->vendor_id === (int) Auth::guard('vendor')->id(), 403);
        $data = $request->validate([
            'tracking_number' => ['required', 'string', 'max:191'],
            'shipping_method' => ['nullable', 'string', 'max:120'],
        ]);
        $order->update([
            'tracking_number' => $data['tracking_number'],
            'shipping_method' => $data['shipping_method'] ?: $order->shipping_method,
            'status' => in_array($order->status, ['pending', 'processing', 'funded'], true) ? 'shipped' : $order->status,
        ]);

        return back()->with('success', 'Tracking information saved and the order marked as shipped.');
    }
}
