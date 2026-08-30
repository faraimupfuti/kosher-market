<?php

namespace App\Http\Controllers;

use App\Models\EscrowDispute;
use App\Models\EscrowTransaction;
use App\Models\Order;
use App\Services\BitcoinEscrowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Throwable;

class EscrowController extends Controller
{
    public function __construct(private BitcoinEscrowService $escrow) {}

    public function create(Request $request, Order $order)
    {
        $customer = Auth::guard('customer')->user();
        abort_unless($customer && (int) $order->customer_id === (int) $customer->id, 403);

        try {
            $escrow = $this->escrow->createForOrder($order);
            return response()->json($escrow->load(['order', 'vendor']), 201);
        } catch (Throwable $e) {
            report($e);
            return response()->json(['message' => 'Unable to create escrow for this order.'], 422);
        }
    }

    public function show(EscrowTransaction $escrow)
    {
        $customer = Auth::guard('customer')->user();
        abort_unless($customer && (int) $escrow->buyer_id === (int) $customer->id, 403);
        return response()->json($escrow->load(['order', 'vendor', 'disputes']));
    }

    public function createPayment(Request $request, EscrowTransaction $escrow)
    {
        $customer = Auth::guard('customer')->user();
        abort_unless($customer && (int) $escrow->buyer_id === (int) $customer->id, 403);

        if ($escrow->currency !== 'BTC') {
            return response()->json(['message' => 'Velstore accepts Bitcoin only.'], 422);
        }

        try {
            $invoice = $this->escrow->createBitcoinInvoice($escrow);
            return response()->json([
                'escrow_id' => $escrow->id,
                'currency' => 'BTC',
                'invoice_id' => $invoice['id'] ?? null,
                'checkout_url' => $invoice['checkoutLink'] ?? null,
                'payment_address' => data_get($invoice, 'addresses.BTC'),
            ]);
        } catch (Throwable $e) {
            report($e);
            return response()->json(['message' => 'Unable to create the Bitcoin payment.'], 502);
        }
    }

    public function confirmReceipt(EscrowTransaction $escrow)
    {
        $customer = Auth::guard('customer')->user();
        abort_unless($customer && (int) $escrow->buyer_id === (int) $customer->id, 403);

        if ($escrow->status !== 'funded') {
            return response()->json(['message' => 'Only funded escrow transactions can be completed.'], 422);
        }

        try {
            $this->escrow->release($escrow, 'Buyer confirmed receipt.');
            return response()->json(['success' => true, 'status' => 'released']);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function dispute(Request $request, EscrowTransaction $escrow)
    {
        $customer = Auth::guard('customer')->user();
        abort_unless($customer && (int) $escrow->buyer_id === (int) $customer->id, 403);

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:100'],
            'description' => ['required', 'string', 'max:5000'],
        ]);

        if (!in_array($escrow->status, ['funded', 'processing'], true)) {
            return response()->json(['message' => 'This escrow cannot be disputed.'], 422);
        }

        $dispute = EscrowDispute::create([
            'escrow_transaction_id' => $escrow->id,
            'opened_by' => $customer->id,
            'reason' => $data['reason'],
            'description' => $data['description'],
            'status' => 'open',
        ]);

        $escrow->update(['status' => 'disputed']);
        return response()->json(['success' => true, 'dispute' => $dispute], 201);
    }

    public function webhook(Request $request)
    {
        $secret = (string) config('bitcoin.webhook_secret');
        $signature = (string) $request->header('BTCPay-Sig');

        if (!$secret || !$signature || !str_starts_with($signature, 'sha256=')) {
            return response()->json(['message' => 'Invalid webhook signature.'], 401);
        }

        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);
        if (!hash_equals($expected, $signature)) {
            return response()->json(['message' => 'Invalid webhook signature.'], 401);
        }

        $payload = $request->json()->all();
        $type = $payload['type'] ?? '';
        $invoiceId = $payload['invoiceId'] ?? null;
        if (!$invoiceId || !in_array($type, ['InvoiceSettled', 'InvoicePaymentSettled'], true)) {
            return response()->json(['received' => true]);
        }

        $baseUrl = rtrim((string) config('bitcoin.btcpay_url'), '/');
        $storeId = config('bitcoin.btcpay_store_id');
        $apiKey = config('bitcoin.btcpay_api_key');
        if (!$baseUrl || !$storeId || !$apiKey) {
            return response()->json(['message' => 'Bitcoin gateway not configured.'], 500);
        }

        $invoiceResponse = Http::withToken($apiKey)->acceptJson()
            ->get($baseUrl.'/api/v1/stores/'.$storeId.'/invoices/'.$invoiceId);
        if ($invoiceResponse->failed()) {
            return response()->json(['message' => 'Unable to verify invoice.'], 502);
        }

        $invoice = $invoiceResponse->json();
        if (($invoice['status'] ?? '') !== 'Settled') {
            return response()->json(['received' => true]);
        }

        $escrowId = data_get($invoice, 'metadata.escrowId');
        $escrow = $escrowId ? EscrowTransaction::find($escrowId) : null;
        if (!$escrow) {
            return response()->json(['received' => true]);
        }

        $btcAmount = (float) ($invoice['amount'] ?? $escrow->amount);
        $this->escrow->markFunded($escrow, 'btcpay-'.$invoiceId, (int) config('bitcoin.required_confirmations', 1), $btcAmount);
        return response()->json(['received' => true]);
    }
}
