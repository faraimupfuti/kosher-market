<?php

namespace App\Http\Controllers;

use App\Models\BitcoinSettlement;
use App\Models\BtcpayWebhookEvent;
use App\Models\EscrowDispute;
use App\Models\EscrowTransaction;
use App\Models\Order;
use App\Models\VendorRegistrationFee;
use App\Services\AutomaticBitcoinPayoutService;
use App\Services\BitcoinAmount;
use App\Services\BitcoinEscrowService;
use App\Services\VendorRegistrationFeeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

class EscrowController extends Controller
{
    public function __construct(private BitcoinEscrowService $escrow, private VendorRegistrationFeeService $vendorFees) {}

    public function create(Request $request, Order $order)
    {
        $customer = Auth::guard('customer')->user();
        abort_unless($customer && (int) $order->customer_id === (int) $customer->id, 403);
        try {
            return response()->json($this->escrow->createForOrder($order)->load(['order', 'vendor']), 201);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'Unable to create escrow for this order.'], 422);
        }
    }

    public function show(EscrowTransaction $escrow)
    {
        $customer = Auth::guard('customer')->user();
        abort_unless($customer && (int) $escrow->buyer_id === (int) $customer->id, 403);

        return view('escrow.show', ['escrow' => $escrow->load(['order', 'vendor', 'disputes', 'settlements'])]);
    }

    public function createPayment(Request $request, EscrowTransaction $escrow)
    {
        $customer = Auth::guard('customer')->user();
        abort_unless($customer && (int) $escrow->buyer_id === (int) $customer->id, 403);
        if ($escrow->status === 'funded') {
            return response()->json(['message' => 'Payment already confirmed.', 'status' => 'funded']);
        }
        try {
            $invoice = $this->escrow->createBitcoinInvoice($escrow);

            return response()->json(['escrow_id' => $escrow->id, 'currency' => 'BTC', 'invoice_id' => $invoice['id'] ?? null, 'checkout_url' => $invoice['checkoutLink'] ?? null, 'payment_address' => data_get($invoice, 'addresses.BTC')]);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'Unable to create the Bitcoin payment.'], 502);
        }
    }

    public function confirmReceipt(EscrowTransaction $escrow, AutomaticBitcoinPayoutService $payouts)
    {
        $customer = Auth::guard('customer')->user();
        abort_unless($customer && (int) $escrow->buyer_id === (int) $customer->id, 403);
        try {
            $released = $this->escrow->release($escrow, 'Buyer confirmed receipt.');
            $settlement = $payouts->forReleasedEscrow($released->fresh());

            return response()->json(['success' => true, 'status' => 'released', 'settlement' => $settlement]);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function dispute(Request $request, EscrowTransaction $escrow)
    {
        $customer = Auth::guard('customer')->user();
        abort_unless($customer && (int) $escrow->buyer_id === (int) $customer->id, 403);
        $data = $request->validate(['reason' => ['required', 'string', 'max:100'], 'description' => ['required', 'string', 'max:5000']]);
        if (! in_array($escrow->status, ['funded', 'processing'], true)) {
            return response()->json(['message' => 'This escrow cannot be disputed.'], 422);
        }
        $dispute = DB::transaction(function () use ($escrow, $customer, $data) {
            $locked = EscrowTransaction::whereKey($escrow->id)->lockForUpdate()->firstOrFail();
            if (! in_array($locked->status, ['funded', 'processing'], true)) {
                abort(422, 'This escrow cannot be disputed.');
            }

            $dispute = EscrowDispute::create(['escrow_transaction_id' => $locked->id, 'opened_by' => $customer->id, 'reason' => $data['reason'], 'description' => $data['description'], 'status' => 'open']);
            $locked->transitionTo('disputed');
            $locked->save();

            return $dispute;
        });

        return response()->json(['success' => true, 'dispute' => $dispute], 201);
    }

    public function webhook(Request $request)
    {
        $rawBody = $request->getContent();
        $secret = (string) config('bitcoin.webhook_secret');
        $signature = (string) $request->header('BTCPay-Sig');
        if (! $secret || ! $signature || ! str_starts_with($signature, 'sha256=')) {
            return response()->json(['message' => 'Invalid webhook signature.'], 401);
        }
        $expected = 'sha256='.hash_hmac('sha256', $rawBody, $secret);
        if (! hash_equals($expected, $signature)) {
            return response()->json(['message' => 'Invalid webhook signature.'], 401);
        }

        $payload = $request->json()->all();
        $type = (string) ($payload['type'] ?? '');
        $deliveryId = $payload['deliveryId'] ?? null;
        $fingerprint = hash('sha256', $rawBody);
        $invoiceId = $payload['invoiceId'] ?? null;
        $payoutId = $payload['payoutId'] ?? null;
        if (! $type) {
            return response()->json(['message' => 'Webhook event type is required.'], 422);
        }

        try {
            $event = BtcpayWebhookEvent::firstOrCreate(
                ['event_fingerprint' => $fingerprint],
                ['delivery_id' => $deliveryId, 'event_type' => $type, 'invoice_id' => $invoiceId, 'payout_id' => $payoutId]
            );
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'Unable to record webhook event.'], 500);
        }
        if ($event->processed_at) {
            return response()->json(['received' => true, 'duplicate' => true]);
        }

        try {
            if (in_array($type, ['PayoutApproved', 'PayoutUpdated', 'PayoutSettled'], true)) {
                if ($payoutId) {
                    $settlement = BitcoinSettlement::where('btcpay_payout_id', $payoutId)->first();
                    if ($settlement) {
                        $this->escrow->syncSettlement($settlement);
                    }
                }
                $event->update(['processed_at' => now(), 'processing_error' => null]);

                return response()->json(['received' => true]);
            }

            if (! $invoiceId || ! in_array($type, ['InvoiceSettled', 'InvoicePaymentSettled'], true)) {
                $event->update(['processed_at' => now()]);

                return response()->json(['received' => true]);
            }

            [$baseUrl, $storeId, $apiKey] = [rtrim((string) config('bitcoin.btcpay_url'), '/'), config('bitcoin.btcpay_store_id'), config('bitcoin.btcpay_api_key')];
            if (! $baseUrl || ! $storeId || ! $apiKey) {
                throw new \RuntimeException('Bitcoin gateway not configured.');
            }
            $response = Http::timeout(20)->withToken($apiKey)->acceptJson()->get($baseUrl.'/api/v1/stores/'.$storeId.'/invoices/'.$invoiceId);
            if ($response->failed()) {
                throw new \RuntimeException('Unable to verify invoice.');
            }
            $invoice = $response->json();
            if (($invoice['id'] ?? $invoiceId) !== $invoiceId) {
                throw new \RuntimeException('BTCPay returned a different invoice identity.');
            }
            if (($invoice['status'] ?? '') !== 'Settled') {
                $event->update(['processed_at' => now()]);

                return response()->json(['received' => true]);
            }

            $metadata = $invoice['metadata'] ?? [];
            if (($metadata['kosherMarketType'] ?? null) === 'vendor_registration_fee') {
                $vendorId = (int) ($metadata['vendorId'] ?? 0);
                $fee = $vendorId ? VendorRegistrationFee::where('vendor_id', $vendorId)->where('btcpay_invoice_id', $invoiceId)->first() : null;
                if (! $fee) {
                    throw new \RuntimeException('Vendor registration invoice is not bound to an existing fee record.');
                }
                $payment = data_get($invoice, 'payments.0', []);
                $txid = (string) ($payment['transactionId'] ?? $payment['id'] ?? ('btcpay-'.$invoiceId));
                $confirmations = (int) ($payment['additionalStatus']['currentConfirmations'] ?? $payment['additionalStatus']['confirmations'] ?? config('bitcoin.required_confirmations', 1));
                $btcAmount = data_get($payment, 'value') ?? data_get($invoice, 'paymentMethods.BTC.amount') ?? $fee->btc_amount;
                $this->vendorFees->markPaid($fee, $txid, $confirmations, $btcAmount);
                $event->update(['processed_at' => now(), 'processing_error' => null]);

                return response()->json(['received' => true]);
            }

            $escrowId = (int) data_get($metadata, 'escrowId', 0);
            $escrow = $escrowId ? EscrowTransaction::whereKey($escrowId)->first() : null;
            if (! $escrow) {
                throw new \RuntimeException('BTCPay invoice is not bound to an existing escrow.');
            }
            if (! $escrow->btcpay_invoice_id || ! hash_equals((string) $escrow->btcpay_invoice_id, (string) $invoiceId)) {
                throw new \RuntimeException('BTCPay invoice does not match the escrow invoice binding.');
            }
            if (strtoupper((string) ($invoice['currency'] ?? '')) !== 'BTC') {
                throw new \RuntimeException('Escrow invoice currency is not BTC.');
            }
            if ($escrow->currency !== 'BTC') {
                throw new \RuntimeException('Escrow currency is not BTC.');
            }

            $invoiceAmount = (string) ($invoice['amount'] ?? data_get($invoice, 'paymentMethods.BTC.amount', ''));
            if ($invoiceAmount === '') {
                throw new \RuntimeException('BTCPay invoice amount is missing.');
            }
            if (BitcoinAmount::toSatoshis($invoiceAmount) !== BitcoinAmount::toSatoshis((string) $escrow->amount)) {
                throw new \RuntimeException('BTCPay invoice amount does not match escrow amount.');
            }

            $payment = data_get($invoice, 'payments.0', []);
            $txid = (string) ($payment['transactionId'] ?? $payment['id'] ?? '');
            if ($txid === '') {
                throw new \RuntimeException('Settled BTCPay invoice has no transaction identifier.');
            }
            $confirmations = (int) ($payment['additionalStatus']['currentConfirmations'] ?? $payment['additionalStatus']['confirmations'] ?? 0);
            $btcAmount = data_get($payment, 'value') ?? $invoiceAmount;
            $this->escrow->markFunded($escrow, $txid, $confirmations, $btcAmount);

            $event->update(['processed_at' => now(), 'processing_error' => null]);

            return response()->json(['received' => true]);
        } catch (Throwable $e) {
            $event->update(['processing_error' => substr($e->getMessage(), 0, 5000)]);
            report($e);

            return response()->json(['message' => 'Webhook processing failed; BTCPay may retry delivery.'], 502);
        }
    }
}
