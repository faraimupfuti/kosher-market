<?php

namespace App\Services;

use App\Models\BitcoinSettlement;
use App\Models\EscrowLedgerEntry;
use App\Models\EscrowTransaction;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class BitcoinEscrowService
{
    public function createForOrder(Order $order): EscrowTransaction
    {
        $order->loadMissing(['product', 'customer']);
        $vendorId = $order->product?->vendor_id;
        $buyerId = $order->customer?->id;
        if (!$vendorId || !$buyerId) throw new RuntimeException('The order must have a customer and vendor.');
        if (strtoupper((string) ($order->currency ?? '')) !== 'BTC') throw new RuntimeException('Velstore accepts Bitcoin only.');

        $amount = (float) $order->total_price;
        if ($amount <= 0 || $amount > 21000000) throw new RuntimeException('Invalid BTC escrow amount.');
        $feeRate = max(0, min(100, (float) config('escrow.platform_fee_percent', 2.5)));
        $fee = round($amount * ($feeRate / 100), 8);
        $sellerAmount = round($amount - $fee, 8);

        return DB::transaction(fn () => EscrowTransaction::firstOrCreate(['order_id' => $order->id], [
            'payment_id' => null, 'buyer_id' => $buyerId, 'vendor_id' => $vendorId,
            'amount' => $amount, 'platform_fee' => $fee, 'seller_amount' => $sellerAmount,
            'currency' => 'BTC', 'status' => 'pending',
        ]));
    }

    public function createBitcoinInvoice(EscrowTransaction $escrow): array
    {
        if ($escrow->currency !== 'BTC') throw new RuntimeException('Bitcoin is the only supported currency.');
        $baseUrl = rtrim((string) config('bitcoin.btcpay_url'), '/');
        $storeId = config('bitcoin.btcpay_store_id');
        $apiKey = config('bitcoin.btcpay_api_key');
        if (!$baseUrl || !$storeId || !$apiKey) throw new RuntimeException('BTCPay Server is not configured.');

        if ($escrow->btcpay_invoice_id) {
            $existing = Http::timeout(20)->withToken($apiKey)->acceptJson()->get($baseUrl.'/api/v1/stores/'.$storeId.'/invoices/'.$escrow->btcpay_invoice_id);
            if ($existing->successful()) return $existing->json();
        }

        $response = Http::timeout(20)->withToken($apiKey)->acceptJson()->post($baseUrl.'/api/v1/stores/'.$storeId.'/invoices', [
            'amount' => number_format((float) $escrow->amount, 8, '.', ''),
            'currency' => 'BTC',
            'metadata' => ['orderId' => (string) $escrow->order_id, 'escrowId' => (string) $escrow->id],
        ]);
        if ($response->failed()) throw new RuntimeException('BTCPay invoice creation failed.');

        $invoice = $response->json();
        $escrow->update([
            'btcpay_invoice_id' => $invoice['id'] ?? null,
            'bitcoin_payment_address' => data_get($invoice, 'addresses.BTC'),
        ]);
        return $invoice;
    }

    public function markFunded(EscrowTransaction $escrow, string $txid, int $confirmations, float $btcAmount): EscrowTransaction
    {
        if ($confirmations < (int) config('bitcoin.required_confirmations', 1)) return $escrow;
        if ($btcAmount + 0.00000001 < (float) $escrow->amount) throw new RuntimeException('Bitcoin payment is below the escrow amount.');

        return DB::transaction(function () use ($escrow, $txid, $confirmations, $btcAmount) {
            $escrow = EscrowTransaction::whereKey($escrow->id)->lockForUpdate()->firstOrFail();
            if (in_array($escrow->status, ['released', 'refunded', 'cancelled'], true)) return $escrow;
            if ($escrow->bitcoin_txid && !hash_equals($escrow->bitcoin_txid, $txid)) throw new RuntimeException('Escrow already has a different transaction.');

            $escrow->update([
                'status' => 'funded', 'bitcoin_txid' => $txid, 'bitcoin_amount' => $btcAmount,
                'bitcoin_confirmations' => $confirmations,
                'payment_detected_at' => $escrow->payment_detected_at ?: now(),
                'payment_confirmed_at' => now(), 'funded_at' => $escrow->funded_at ?: now(),
                'release_due_at' => $escrow->release_due_at ?: now()->addDays((int) config('escrow.hold_days', 3)),
            ]);

            if (!$escrow->ledgerEntries()->where('reference', $txid)->exists()) {
                EscrowLedgerEntry::create([
                    'escrow_transaction_id' => $escrow->id, 'type' => 'escrow_funded',
                    'amount' => $btcAmount, 'currency' => 'BTC', 'reference' => $txid,
                    'metadata' => ['confirmations' => $confirmations],
                ]);
            }
            return $escrow;
        });
    }

    public function release(EscrowTransaction $escrow, ?string $note = null): EscrowTransaction
    {
        return DB::transaction(function () use ($escrow, $note) {
            $escrow = EscrowTransaction::with('vendor')->whereKey($escrow->id)->lockForUpdate()->firstOrFail();
            if ($escrow->status !== 'funded') throw new RuntimeException('Only funded escrow can be released.');
            if (!$escrow->vendor?->bitcoin_payout_address) throw new RuntimeException('Vendor has not configured a Bitcoin payout address.');
            if (!$escrow->vendor?->bitcoin_payout_address_verified_at) throw new RuntimeException('Vendor Bitcoin payout address must be verified before release.');

            $escrow->update(['status' => 'released', 'released_at' => now(), 'release_note' => $note]);
            if (!$escrow->ledgerEntries()->where('type', 'seller_payout_due')->exists()) {
                EscrowLedgerEntry::create(['escrow_transaction_id' => $escrow->id, 'type' => 'seller_payout_due', 'amount' => $escrow->seller_amount, 'currency' => 'BTC', 'reference' => 'ESCROW-'.$escrow->id]);
            }
            BitcoinSettlement::firstOrCreate(['escrow_transaction_id' => $escrow->id, 'type' => 'seller_payout'], [
                'vendor_id' => $escrow->vendor_id, 'amount' => $escrow->seller_amount, 'currency' => 'BTC',
                'destination_address' => $escrow->vendor->bitcoin_payout_address, 'status' => 'pending',
            ]);
            return $escrow;
        });
    }

    public function refund(EscrowTransaction $escrow, ?string $note = null): EscrowTransaction
    {
        return DB::transaction(function () use ($escrow, $note) {
            $escrow = EscrowTransaction::whereKey($escrow->id)->lockForUpdate()->firstOrFail();
            if (!in_array($escrow->status, ['funded', 'disputed'], true)) throw new RuntimeException('Only funded or disputed escrow can be refunded.');
            $escrow->update(['status' => 'refunded', 'refunded_at' => now(), 'refund_note' => $note]);
            if (!$escrow->ledgerEntries()->where('type', 'buyer_refund_due')->exists()) {
                EscrowLedgerEntry::create(['escrow_transaction_id' => $escrow->id, 'type' => 'buyer_refund_due', 'amount' => $escrow->bitcoin_amount ?? $escrow->amount, 'currency' => 'BTC', 'reference' => 'REFUND-'.$escrow->id]);
            }
            BitcoinSettlement::firstOrCreate(['escrow_transaction_id' => $escrow->id, 'type' => 'buyer_refund'], [
                'vendor_id' => null, 'amount' => $escrow->bitcoin_amount ?? $escrow->amount, 'currency' => 'BTC', 'status' => 'needs_destination',
            ]);
            return $escrow;
        });
    }

    private function normalizePayoutState(?string $state): string
    {
        return match (strtolower(str_replace(['-', ' '], '_', (string) $state))) {
            'awaitingapproval' => 'awaiting_approval',
            'awaiting_approval' => 'awaiting_approval',
            'awaitingpayment' => 'awaiting_payment',
            'awaiting_payment' => 'awaiting_payment',
            'inprogress' => 'in_progress',
            'in_progress' => 'in_progress',
            'completed' => 'completed',
            'cancelled', 'canceled' => 'cancelled',
            default => 'pending',
        };
    }

    public function submitSettlement(BitcoinSettlement $settlement): BitcoinSettlement
    {
        if ($settlement->status === 'completed') return $settlement;
        if (!$settlement->destination_address) throw new RuntimeException('Settlement destination is missing.');
        if (!preg_match('/^(bc1[ac-hj-np-z02-9]{11,87}|[13][a-km-zA-HJ-NP-Z1-9]{25,34})$/', $settlement->destination_address)) throw new RuntimeException('Only valid Bitcoin mainnet addresses are accepted.');

        $baseUrl = rtrim((string) config('bitcoin.btcpay_url'), '/');
        $storeId = config('bitcoin.btcpay_store_id');
        $apiKey = config('bitcoin.btcpay_api_key');
        if (!$baseUrl || !$storeId || !$apiKey) throw new RuntimeException('BTCPay Server is not configured.');

        $response = Http::timeout(20)->withToken($apiKey)->acceptJson()->post($baseUrl.'/api/v1/stores/'.$storeId.'/payouts', [
            'destination' => $settlement->destination_address,
            'amount' => number_format((float) $settlement->amount, 8, '.', ''),
            'payoutMethodId' => 'BTC-CHAIN',
            'approved' => false,
            'metadata' => ['velstoreSettlementId' => (string) $settlement->id, 'escrowId' => (string) $settlement->escrow_transaction_id, 'type' => $settlement->type],
        ]);
        if ($response->failed()) {
            $settlement->update(['status' => 'failed', 'error_message' => $response->body()]);
            throw new RuntimeException('BTCPay payout request failed.');
        }

        $payout = $response->json();
        $settlement->update([
            'status' => $this->normalizePayoutState($payout['state'] ?? null),
            'btcpay_payout_id' => $payout['id'] ?? null,
            'submitted_at' => now(),
            'error_message' => null,
        ]);
        return $settlement->fresh();
    }

    public function syncSettlement(BitcoinSettlement $settlement): BitcoinSettlement
    {
        if (!$settlement->btcpay_payout_id) return $this->submitSettlement($settlement);
        $baseUrl = rtrim((string) config('bitcoin.btcpay_url'), '/'); $apiKey = config('bitcoin.btcpay_api_key');
        if (!$baseUrl || !$apiKey) throw new RuntimeException('BTCPay Server is not configured.');
        $response = Http::timeout(20)->withToken($apiKey)->acceptJson()->get($baseUrl.'/api/v1/payouts/'.$settlement->btcpay_payout_id);
        if ($response->failed()) throw new RuntimeException('Unable to retrieve BTCPay payout status.');
        $payout = $response->json(); $state = $this->normalizePayoutState($payout['state'] ?? null);
        $proof = $payout['paymentProof'] ?? [];
        $txid = $proof['id'] ?? $proof['transactionId'] ?? null;
        $settlement->update(['status' => $state, 'bitcoin_txid' => $txid ?: $settlement->bitcoin_txid, 'completed_at' => $state === 'completed' ? ($settlement->completed_at ?: now()) : $settlement->completed_at]);
        return $settlement->fresh();
    }
}
