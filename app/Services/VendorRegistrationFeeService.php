<?php

namespace App\Services;

use App\Models\PlatformRevenueEntry;
use App\Models\Vendor;
use App\Models\VendorRegistrationFee;
use App\Services\BitcoinAmount;
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

        [$baseUrl, $storeId, $apiKey] = $this->btcpayConfig();
        if ($fee->btcpay_invoice_id) {
            $existing = Http::timeout(20)->withToken($apiKey)->acceptJson()->get($baseUrl.'/api/v1/stores/'.$storeId.'/invoices/'.$fee->btcpay_invoice_id);
            if ($existing->successful()) return $this->syncInvoice($fee, $existing->json());
        }

        // USD is only the fixed commercial denomination. The vendor pays BTC.
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
        $status = $invoice['status'] ?? '';
        $fee->update([
            'btcpay_invoice_id' => $invoice['id'] ?? $fee->btcpay_invoice_id,
            'checkout_url' => $invoice['checkoutLink'] ?? $fee->checkout_url,
            'bitcoin_payment_address' => data_get($invoice, 'addresses.BTC') ?: $fee->bitcoin_payment_address,
            'btc_amount' => data_get($invoice, 'paymentMethods.BTC.amount') ?: $fee->btc_amount,
            'status' => $status === 'Settled' ? 'paid' : $fee->status,
            'paid_at' => $status === 'Settled' ? ($fee->paid_at ?: now()) : $fee->paid_at,
        ]);
        return $fee->fresh();
    }

    public function markPaid(VendorRegistrationFee $fee, string $txid, int $confirmations, string|int|float $btcAmount): VendorRegistrationFee
    {
        if ($confirmations < (int) config('bitcoin.required_confirmations', 1)) return $fee;
        return DB::transaction(function () use ($fee, $txid, $confirmations, $btcAmount) {
            $locked = VendorRegistrationFee::whereKey($fee->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === 'paid') return $locked;

            $receivedSatoshis = BitcoinAmount::toSatoshis((string) $btcAmount);
            if ($locked->btc_amount !== null && $receivedSatoshis !== BitcoinAmount::toSatoshis((string) $locked->btc_amount)) {
                throw new RuntimeException('Bitcoin payment amount does not exactly match the vendor onboarding invoice.');
            }

            $normalized = BitcoinAmount::fromSatoshis($receivedSatoshis);
            $locked->update([
                'status' => 'paid', 'bitcoin_txid' => $txid,
                'bitcoin_confirmations' => $confirmations,
                'btc_amount' => $normalized,
                'payment_detected_at' => $locked->payment_detected_at ?: now(), 'paid_at' => now(),
            ]);
            $locked->vendor()->update(['status' => 'active']);

            $satoshis = $receivedSatoshis;
            PlatformRevenueEntry::firstOrCreate(
                ['reference' => 'VENDOR-ONBOARDING-'.$locked->id],
                [
                    'type' => 'vendor_onboarding',
                    'vendor_id' => $locked->vendor_id,
                    'amount_btc' => $normalized,
                    'amount_satoshis' => $satoshis,
                    'status' => 'earned',
                    'description' => 'One-time Kosher Market Vendor Verification & Onboarding Fee.',
                    'earned_at' => now(),
                ]
            );

            return $locked;
        });
    }

    private function btcpayConfig(): array
    {
        $baseUrl = rtrim((string) config('bitcoin.btcpay_url'), '/');
        $storeId = config('bitcoin.btcpay_store_id'); $apiKey = config('bitcoin.btcpay_api_key');
        if (!$baseUrl || !$storeId || !$apiKey) throw new RuntimeException('BTCPay Server is not configured.');
        return [$baseUrl, $storeId, $apiKey];
    }
}
