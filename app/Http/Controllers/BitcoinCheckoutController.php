<?php

namespace App\Http\Controllers;

use App\Models\Country;
use App\Models\Order;
use App\Models\Product;
use App\Services\BitcoinEscrowService;
use App\Services\GlobalShippingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BitcoinCheckoutController extends Controller
{
    public function __construct(private BitcoinEscrowService $escrow, private GlobalShippingService $shipping) {}

    private function activeProductQuery()
    {
        return Product::where('status', 1)
            ->whereHas('vendor', fn ($query) => $query->where('status', 'active'));
    }

    public function index(Request $request)
    {
        $product = $this->activeProductQuery()->with(['primaryVariant', 'shippingCountries'])->findOrFail($request->integer('product_id'));
        abort_if(strtoupper((string) $product->currency) !== 'BTC', 422, 'This marketplace accepts Bitcoin only.');
        $price = $product->primaryVariant?->discount_price ?: $product->primaryVariant?->price ?: $product->price;
        abort_if((float) $price <= 0, 422, 'Product does not have a valid BTC price.');
        $countries = Country::where('enabled', true)->orderBy('name')->get(['code', 'name']);
        $product->setAttribute('checkout_price', (float) $price);
        return view('checkout.bitcoin', compact('product', 'countries'));
    }

    public function shippingOptions(Request $request)
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'country' => ['required', 'string', 'size:2'],
            'quantity' => ['required', 'integer', 'min:1', 'max:100'],
        ]);
        $product = $this->activeProductQuery()->with('primaryVariant')->findOrFail($data['product_id']);
        $price = (float) ($product->primaryVariant?->discount_price ?: $product->primaryVariant?->price ?: $product->price);
        return response()->json([
            'currency' => 'BTC',
            'options' => $this->shipping->options($product, $data['country'], round($price * (int) $data['quantity'], 8)),
        ]);
    }

    public function process(Request $request)
    {
        $customer = Auth::guard('customer')->user();
        abort_unless($customer, 401);

        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:100'],
            'shipping_rate_id' => ['required', 'integer', 'exists:vendor_shipping_rates,id'],
            'shipping_country' => ['required', 'string', 'size:2', 'exists:countries,code'],
            'shipping_full_name' => ['required', 'string', 'max:160'],
            'shipping_address_line1' => ['required', 'string', 'max:255'],
            'shipping_address_line2' => ['nullable', 'string', 'max:255'],
            'shipping_city' => ['required', 'string', 'max:120'],
            'shipping_state' => ['nullable', 'string', 'max:120'],
            'shipping_postal_code' => ['required', 'string', 'max:40'],
        ]);

        $result = DB::transaction(function () use ($data, $customer) {
            $product = $this->activeProductQuery()->with('primaryVariant')->whereKey($data['product_id'])->lockForUpdate()->firstOrFail();
            abort_if(strtoupper((string) $product->currency) !== 'BTC', 422, 'This marketplace accepts Bitcoin only.');
            $price = (float) ($product->primaryVariant?->discount_price ?: $product->primaryVariant?->price ?: $product->price);
            abort_if($price <= 0, 422, 'Product does not have a valid BTC price.');

            $quantity = (int) $data['quantity'];
            $subtotal = round($price * $quantity, 8);
            $options = $this->shipping->options($product, $data['shipping_country'], $subtotal);
            $rate = collect($options)->firstWhere('id', (int) $data['shipping_rate_id']);
            abort_if(!$rate, 422, 'The selected shipping method is not available for this destination.');

            $shippingCost = round((float) $rate['price_btc'], 8);
            $total = round($subtotal + $shippingCost, 8);
            $order = Order::create([
                'customer_id' => $customer->id,
                'product_id' => $product->id,
                'vendor_id' => $product->vendor_id,
                'quantity' => $quantity,
                'unit_price' => $price,
                'total_amount' => $total,
                'currency' => 'BTC',
                'payment_method' => 'bitcoin',
                'payment_status' => 'pending',
                'status' => 'pending',
                'shipping_rate_id' => $rate['id'],
                'shipping_country' => strtoupper($data['shipping_country']),
                'shipping_full_name' => $data['shipping_full_name'],
                'shipping_address_line1' => $data['shipping_address_line1'],
                'shipping_address_line2' => $data['shipping_address_line2'] ?? null,
                'shipping_city' => $data['shipping_city'],
                'shipping_state' => $data['shipping_state'] ?? null,
                'shipping_postal_code' => $data['shipping_postal_code'],
                'shipping_cost_btc' => $shippingCost,
                'estimated_delivery_min_days' => $rate['min_delivery_days'],
                'estimated_delivery_max_days' => $rate['max_delivery_days'],
                'shipping_method' => $rate['service_name'],
                'shipping_address' => json_encode([
                    'country' => strtoupper($data['shipping_country']),
                    'full_name' => $data['shipping_full_name'],
                    'line1' => $data['shipping_address_line1'],
                    'line2' => $data['shipping_address_line2'] ?? null,
                    'city' => $data['shipping_city'],
                    'state' => $data['shipping_state'] ?? null,
                    'postal_code' => $data['shipping_postal_code'],
                ]),
            ]);
            $escrow = $this->escrow->createForOrder($order);
            $invoice = $this->escrow->createBitcoinInvoice($escrow);
            return compact('order', 'escrow', 'invoice');
        });

        return redirect()->route('escrow.show', $result['escrow'])->with('bitcoin_invoice', $result['invoice']);
    }
}
