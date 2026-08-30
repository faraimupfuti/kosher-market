<?php

namespace App\Services;

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
        if (!$vendorId || !$buyerId) {
            throw new RuntimeException('The order must have a customer and a vendor before escrow can be created.');
        }

        // Bitcoin-only: order totals must already be denominated in BTC.
        if (strtoupper((string) ($order->currency ?? 'BTC')) !== 'BTC') {
            throw new RuntimeException('Velstore is Bitcoin-only. The order currency must be BTC.');
        }

        $amount = (float) $order->total_price;
        if ($amount <= 0) {
            throw new RuntimeException('Escrow amount must be greater than zero.');
        }

        $feeRate = (float) config('escrow.platform_fee_percent', 2.5);
        $fee = round($amount * ($feeRate / 100), 8);
        $sellerAmount = round($amount - $fee, 8);

        return DB::transaction(function () use ($order, $buyerId, $vendorId, $amount, $fee, $sellerAmount) {
            return EscrowTransaction::firstOrCreate(
                ['order_id' => $order->id],
                [
                    'payment_id' => null,
                    'buyer_id' => $buyerId,
                    'vendor_id' => $vendorId,
                    'amount' => $amount,
                    'platform_fee' => $fee,
                    'seller_amount' => $sellerAmount,
                    'currency' => 'BTC',
                    'status' => 'pending',
                ]
            );
        });
    }

    public function createBitcoinInvoice(EscrowTransaction $escrow): array
    {
        $baseUrl = rtrim((string) config('bitcoin.btcpay_url'), '/');
        $storeId = config('bitcoin.btcpay_store_id');
        $apiKey = config('bitcoin.btcpay_api_key');
        if (!$baseUrl || !$storeId || !$apiKey) {
            throw new RuntimeException('BTCPay Server is not configured.');
        }

        $response = Http::withToken($apiKey)->acceptJson()->post(
            $baseUrl.'/api/v1/stores/'.$storeId.'/invoices',
            [
                'amount' => number_format((float) $escrow->amount, 8, '.', ''),
                'currency' => 'BTC',
                'metadata' => [
                    'orderId' => (string) $escrow->order_id,
                    'escrowId' => (string) $escrow->id,
                ],
            ]
        );

        if ($response->failed()) {
            throw new RuntimeException('BTCPay invoice creation failed: '.$response->body());
        }

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

        return DB::transaction(function () use ($escrow, $txid, $confirmations, $btcAmount) {
            $escrow->refresh();
            if (in_array($escrow->status, ['released', 'refunded', 'cancelled'], true)) return $escrow;
            if ($btcAmount + 0.00000001 < (float) $escrow->amount) {
                throw new RuntimeException('Bitcoin payment amount is below the escrow amount.');
            }

            $escrow->update([
                'status' => 'funded',
                'bitcoin_txid' => $txid,
                'bitcoin_amount' => $btcAmount,
                'bitcoin_confirmations' => $confirmations,
                'payment_detected_at' => now(),
                'payment_confirmed_at' => now(),
                'funded_at' => now(),
                'release_due_at' => now()->addDays((int) config('escrow.hold_days', 3)),
            ]);

            EscrowLedgerEntry::create([
                'escrow_transaction_id' => $escrow->id,
                'type' => 'escrow_funded',
                'amount' => $btcAmount,
                'currency' => 'BTC',
                'reference' => $txid,
                'metadata' => ['confirmations' => $confirmations],
            ]);
            return $escrow;
        });
    }

    public function release(EscrowTransaction $escrow, ?string $note = null): EscrowTransaction
    {
        return DB::transaction(function () use ($escrow, $note) {
            $escrow->refresh();
            if ($escrow->status !== 'funded') throw new RuntimeException('Only funded escrow transactions can be released.');
            $escrow->update(['status' => 'released', 'released_at' => now(), 'release_note' => $note]);
            EscrowLedgerEntry::create([
                'escrow_transaction_id' => $escrow->id,
                'type' => 'seller_payout_due',
                'amount' => $escrow->seller_amount,
                'currency' => 'BTC',
                'reference' => 'ESCROW-'.$escrow->id,
            ]);
            return $escrow;
        });
    }

    public function refund(EscrowTransaction $escrow, ?string $note = null): EscrowTransaction
    {
        return DB::transaction(function () use ($escrow, $note) {
            $escrow->refresh();
            if (!in_array($escrow->status, ['funded', 'disputed'], true)) throw new RuntimeException('Only funded or disputed escrow transactions can be refunded.');
            $escrow->update(['status' => 'refunded', 'refunded_at' => now(), 'refund_note' => $note]);
            EscrowLedgerEntry::create([
                'escrow_transaction_id' => $escrow->id,
                'type' => 'buyer_refund_due',
                'amount' => $escrow->bitcoin_amount ?? $escrow->amount,
                'currency' => 'BTC',
                'reference' => 'REFUND-'.$escrow->id,
            ]);
            return $escrow;
        });
    }
}
