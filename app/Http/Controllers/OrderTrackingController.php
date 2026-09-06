<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderTrackingEvent;
use Illuminate\Http\Request;

class OrderTrackingController extends Controller
{
    public function show(Request $request, Order $order)
    {
        $this->authorizeOrder($request, $order);
        return view('orders.tracking', ['order' => $order->load(['trackingEvents' => fn ($q) => $q->latest('occurred_at')])]);
    }

    public function store(Request $request, Order $order)
    {
        $vendor = $request->user('vendor');
        abort_unless($vendor && (int) $order->vendor_id === (int) $vendor->id, 403);
        $data = $request->validate(['status' => 'required|string|max:50','location' => 'nullable|string|max:255','note' => 'nullable|string|max:2000','occurred_at' => 'nullable|date']);
        OrderTrackingEvent::create(array_merge($data, ['order_id' => $order->id, 'occurred_at' => $data['occurred_at'] ?? now()]));
        $order->update(['status' => $data['status']]);
        return back()->with('success', 'Tracking update added.');
    }

    private function authorizeOrder(Request $request, Order $order): void
    {
        $customer = $request->user('customer');
        $vendor = $request->user('vendor');
        abort_unless(($customer && (int) $order->customer_id === (int) $customer->id) || ($vendor && (int) $order->vendor_id === (int) $vendor->id), 403);
    }
}
