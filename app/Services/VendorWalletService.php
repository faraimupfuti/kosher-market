<?php

namespace App\Services;

use App\Models\BitcoinSettlement;
use App\Models\EscrowTransaction;
use App\Models\Vendor;
use App\Models\VendorWallet;
use App\Models\VendorWalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class VendorWalletService
{
    public function walletFor(Vendor $vendor): VendorWallet
    {
        return VendorWallet::firstOrCreate(
            ['vendor_id' => $vendor->id],
            ['crypto' => 'BTC', 'available_satoshis' => 0, 'locked_satoshis' => 0]
        );
    }

    public function allocateDepositAddress(Vendor $vendor): VendorWallet
    {
        $wallet = $this->walletFor($vendor);
        if ($wallet->deposit_address) return $wallet;

        $baseUrl = rtrim((string) config('bitcoin.shkeeper_url'), '/');
        $apiKey = (string) config('bitcoin.shkeeper_api_key');
        if (!$baseUrl || !$apiKey) throw new RuntimeException('SHKeeper is not configured.');

        $response = Http::timeout(20)
            ->withHeaders(['X-Shkeeper-Api-Key' => $apiKey])
            ->acceptJson()
            ->get($baseUrl . '/api/v1/BTC/addresses');

        if ($response->failed()) throw new RuntimeException('Unable to retrieve SHKeeper BTC addresses.');
        $addresses = $response->json('addresses', []);
        if (!is_array($addresses) || !$addresses) throw new RuntimeException('SHKeeper has no BTC addresses available.');

        foreach ($addresses as $address) {
            if (!is_string($address) || $address === '') continue;
            $claimed = VendorWallet::where('deposit_address', $address)->exists();
            if ($claimed) continue;

            $txResponse = Http::timeout(15)
                ->withHeaders(['X-Shkeeper-Api-Key' => $apiKey])
                ->acceptJson()
                ->get($baseUrl . '/api/v1/transactions/BTC/' . urlencode($address));
            if ($txResponse->failed()) continue;
            $transactions = $txResponse->json('transactions', []);
            if (is_array($transactions) && count($transactions) > 0) continue;

            $wallet->update(['deposit_address' => $address]);
            return $wallet->fresh();
        }

        throw new RuntimeException('No unused SHKeeper BTC address is currently available.');
    }

    public function creditFromEscrow(EscrowTransaction $escrow): VendorWalletTransaction
    {
        return DB::transaction(function () use ($escrow) {
            $wallet = VendorWallet::where('vendor_id', $escrow->vendor_id)->lockForUpdate()->first();
            if (!$wallet) {
                $wallet = VendorWallet::create(['vendor_id' => $escrow->vendor_id, 'crypto' => 'BTC', 'available_satoshis' => 0, 'locked_satoshis' => 0]);
                $wallet = VendorWallet::whereKey($wallet->id)->lockForUpdate()->firstOrFail();
            }

            $reference = 'ESCROW-CREDIT-' . $escrow->id;
            $existing = VendorWalletTransaction::where('reference', $reference)->first();
            if ($existing) return $existing;

            $amount = BitcoinAmount::toSatoshis((string) $escrow->seller_amount);
            $wallet->available_satoshis += $amount;
            $wallet->save();

            return VendorWalletTransaction::create([
                'vendor_wallet_id' => $wallet->id,
                'type' => 'escrow_credit',
                'amount_satoshis' => $amount,
                'balance_after_satoshis' => $wallet->available_satoshis,
                'reference' => $reference,
                'status' => 'posted',
                'escrow_transaction_id' => $escrow->id,
                'metadata' => ['source' => 'manual_escrow_release'],
            ]);
        });
    }

    public function requestWithdrawal(Vendor $vendor, string $amountBtc): BitcoinSettlement
    {
        $amount = BitcoinAmount::toSatoshis($amountBtc);
        if ($amount <= 0) throw new RuntimeException('Withdrawal amount must be greater than zero.');

        return DB::transaction(function () use ($vendor, $amount) {
            $wallet = $this->walletFor($vendor);
            $wallet = VendorWallet::whereKey($wallet->id)->lockForUpdate()->firstOrFail();
            if ($wallet->available_satoshis < $amount) throw new RuntimeException('Insufficient available wallet balance.');

            $destination = trim((string) $vendor->bitcoin_payout_address);
            if (!preg_match('/^(bc1[ac-hj-np-z02-9]{11,87}|[13][a-km-zA-HJ-NP-Z1-9]{25,34})$/', $destination)) {
                throw new RuntimeException('Set a valid Bitcoin withdrawal address first.');
            }
            if (!$vendor->bitcoin_payout_address_verified_at) throw new RuntimeException('Your Bitcoin withdrawal address must be verified first.');

            $settlement = BitcoinSettlement::create([
                'escrow_transaction_id' => null,
                'vendor_id' => $vendor->id,
                'type' => 'vendor_withdrawal',
                'amount' => BitcoinAmount::fromSatoshis($amount),
                'currency' => 'BTC',
                'destination_address' => $destination,
                'status' => 'pending',
            ]);

            $wallet->available_satoshis -= $amount;
            $wallet->locked_satoshis += $amount;
            $wallet->save();

            VendorWalletTransaction::create([
                'vendor_wallet_id' => $wallet->id,
                'type' => 'withdrawal_hold',
                'amount_satoshis' => $amount,
                'balance_after_satoshis' => $wallet->available_satoshis,
                'reference' => 'WITHDRAWAL-HOLD-' . $settlement->id,
                'status' => 'pending',
                'bitcoin_settlement_id' => $settlement->id,
                'metadata' => ['destination' => $destination],
            ]);

            return $settlement;
        });
    }

    public function finalizeWithdrawal(BitcoinSettlement $settlement, bool $success): void
    {
        DB::transaction(function () use ($settlement, $success) {
            $wallet = VendorWallet::where('vendor_id', $settlement->vendor_id)->lockForUpdate()->firstOrFail();
            $amount = BitcoinAmount::toSatoshis((string) $settlement->amount);
            $reference = 'WITHDRAWAL-' . $settlement->id . '-' . ($success ? 'COMPLETE' : 'RELEASE');
            if (VendorWalletTransaction::where('reference', $reference)->exists()) return;

            $wallet->locked_satoshis = max(0, $wallet->locked_satoshis - $amount);
            if (!$success) $wallet->available_satoshis += $amount;
            $wallet->save();

            VendorWalletTransaction::create([
                'vendor_wallet_id' => $wallet->id,
                'type' => $success ? 'withdrawal_complete' : 'withdrawal_reversal',
                'amount_satoshis' => $amount,
                'balance_after_satoshis' => $wallet->available_satoshis,
                'reference' => $reference,
                'status' => $success ? 'posted' : 'reversed',
                'bitcoin_settlement_id' => $settlement->id,
                'txid' => $settlement->bitcoin_txid,
            ]);
        });
    }
}
