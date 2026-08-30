<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Services\BitcoinEscrowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Throwable;

class BitcoinCheckoutController extends Controller
{
    public function __construct(private BitcoinEscrowService $escrow) {}

    public function index(Request $request)
    {
        $product = Product::findOrFail($request->integer('product_id'));
        return view('checkout.bitcoin', compact('product'));
    }

    public function process(Request $request)
    {
        $customer = Auth::guard('customer')->user();
        abort_unless($customer, 401);

        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        $result = DB::transaction(function () use ($data, $customer) {
            $product = Product::whereKey($data['product_id'])->lockForUpdate()->firstOrFail();
            abort_if(!$product->status, 422, 'Product is unavailable.');
            $price = (float) $product->sale_price;
            abort_if($price <= 0, 422, 'Product does not have a valid BTC price.');
            abort_if(strtoupper((string) ($product->currency ?? '')) !== 'BTC', 422, 'This marketplace accepts Bitcoin only.');

            $quantity = (int) $data['quantity'];
            $total = round($price * $quantity, 8);
            $order = Order::create([
                'customer_id' => $customer->id,
                'product_id' => $product->id,
                'vendor_id' => $product->vendor_id,
                'quantity' => $quantity,
                'unit_price' => $price,
                'total_price' => $total,
                'currency' => 'BTC',
                'payment_method' => 'bitcoin',
                'payment_status' => 'pending',
                'status' => 'pending',
            ]);
            $escrow = $this->escrow->createForOrder($order);
            $invoice = $this->escrow->createBitcoinInvoice($escrow);
            return compact('order', 'escrow', 'invoice');
        });

        return redirect()->route('escrow.show', $result['escrow'])->with('bitcoin_invoice', $result['invoice']);
    }
}
