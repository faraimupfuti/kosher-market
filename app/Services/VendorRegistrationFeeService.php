<?php

namespace App\Services;

use App\Models\Vendor;
use App\Models\VendorRegistrationFee;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class VendorRegistrationFeeService
{
    public function createOrGet(Vendor $vendor): VendorRegistrationFee
    {
        $fee = VendorRegistrationFee::firstOrCreate(
            ['vendor_id' => $vendor->id],
            ['usd_amount' => config('bitcoin.vendor_registration_usd', '200.00'), 'currency' => 'BTC', 'status' => 'pending']
        );

        if ($fee->status === 'paid') return $fee;

        $baseUrl = rtrim((string) config('bitcoin.btcpay_url'), '/');
        $storeId = config('bitcoin.btcpay_store_id');
        $apiKey = config('bitcoin.btcpay_api_key');
        if (!$baseUrl || !$storeId || !$apiKey) throw new RuntimeException('BTCPay Server is not configured.');

        if ($fee->btcpay_invoice_id) {
            $existing = Http::timeout(20)->withToken($apiKey)->acceptJson()->get($baseUrl.'/api/v1/stores/'.$storeId.'/invoices/'.$fee->btcpay_invoice_id);
            if ($existing->successful()) return $this->syncInvoice($fee, $existing->json());
        }

        // The fee is denominated in USD solely to lock the commercial price at
        // $200. Settlement remains Bitcoin-only: BTCPay calculates the BTC amount.
        $response = Http::timeout(20)->withToken($apiKey)->acceptJson()->post(
            $baseUrl.'/api/v1/stores/'.$storeId.'/invoices',
            [
                'amount' => (string) config('bitcoin.vendor_registration_usd', '200.00'),
                'currency' => (string) config('bitcoin.btc_invoice_currency', 'USD'),
                'metadata' => [
                    'kosherMarketType' => 'vendor_registration_fee',
                    'vendorId' => (string) $vendor->id,
                    'usdAmount' => (string) config('bitcoin.vendor_registration_usd', '200.00'),
                ],
            ]
        );
        if ($response->failed()) throw new RuntimeException('BTCPay vendor registration invoice creation failed.');
        return $this->syncInvoice($fee, $response->json());
    }

    private function syncInvoice(VendorRegistrationFee $fee, array $invoice): VendorRegistrationFee
    {
        $fee->update([
            'btcpay_invoice_id' => $invoice['id'] ?? $fee->btcpay_invoice_id,
            'bitcoin_payment_address' => data_get($invoice, 'addresses.BTC') ?: $fee->bitcoin_payment_address,
            'btc_amount' => data_get($invoice, 'paymentMethods.BTC.amount') ?: data_get($invoice, 'amount') ?: $fee->btc_amount,
            'status' => ($invoice['status'] ?? '') === 'Settled' ? 'paid' : $fee->status,
            'paid_at' => ($invoice['status'] ?? '') === 'Settled' ? ($fee->paid_at ?: now()) : $fee->paid_at,
        ]);
        return $fee->fresh();
    }

    public function markPaid(VendorRegistrationFee $fee, string $txid, int $confirmations, string|int|float $btcAmount): VendorRegistrationFee
    {
        if ($confirmations < (int) config('bitcoin.required_confirmations', 1)) return $fee;
        return DB::transaction(function () use ($fee, $txid, $confirmations, $btcAmount) {
            $locked = VendorRegistrationFee::whereKey($fee->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === 'paid') return $locked;
            $locked->update([
                'status' => 'paid',
                'bitcoin_txid' => $txid,
                'bitcoin_confirmations' => $confirmations,
                'btc_amount' => number_format((float) $btcAmount, 8, '.', ''),
                'payment_detected_at' => $locked->payment_detected_at ?: now(),
                'paid_at' => now(),
            ]);
            $locked->vendor()->update(['status' => 'active']);
            return $locked;
        });
    }
}
