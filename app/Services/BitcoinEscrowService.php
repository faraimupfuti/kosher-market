<?php

namespace App\Services;

use App\Models\BitcoinSettlement;
use App\Models\EscrowLedgerEntry;
use App\Models\EscrowTransaction;
use App\Models\Order;
use App\Models\PlatformRevenueEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class BitcoinEscrowService
{
    private const MAX_BTC_SATOSHIS = 2100000000000000;

    public function __construct(private VendorWalletService $wallets) {}

    private function btcToSatoshis(string|int|float $amount): int
    {
        $value = is_float($amount) || is_int($amount) ? number_format((float) $amount, 8, '.', '') : trim($amount);
        $satoshis = BitcoinAmount::toSatoshis($value);
        if ($satoshis > self::MAX_BTC_SATOSHIS) throw new RuntimeException('Invalid BTC amount.');
        return $satoshis;
    }

    private function satoshisToBtc(int $satoshis): string { return BitcoinAmount::fromSatoshis($satoshis); }
    private function normalizeBtcAmount(string|int|float $amount): string { return $this->satoshisToBtc($this->btcToSatoshis($amount)); }
    private function feeSatoshis(int $satoshis): int { return BitcoinAmount::percentOf($satoshis, (string) config('escrow.platform_fee_percent', '3.00')); }

    public function createForOrder(Order $order): EscrowTransaction
    {
        $order->loadMissing(['product', 'customer']);
        $vendorId = $order->product?->vendor_id;
        $buyerId = $order->customer?->id;
        if (!$vendorId || !$buyerId) throw new RuntimeException('The order must have a customer and vendor.');
        if (strtoupper((string) ($order->currency ?? '')) !== 'BTC') throw new RuntimeException('Kosher Market accepts Bitcoin only.');
        $satoshis = $this->btcToSatoshis($order->total_amount);
        if ($satoshis <= 0) throw new RuntimeException('Invalid BTC escrow amount.');
        $feeSatoshis = $this->feeSatoshis($satoshis);
        return DB::transaction(fn () => EscrowTransaction::firstOrCreate(
            ['order_id' => $order->id],
            ['payment_id' => null, 'buyer_id' => $buyerId, 'vendor_id' => $vendorId, 'amount' => $this->satoshisToBtc($satoshis), 'platform_fee' => $this->satoshisToBtc($feeSatoshis), 'seller_amount' => $this->satoshisToBtc($satoshis - $feeSatoshis), 'currency' => 'BTC', 'status' => 'pending']
        ));
    }

    public function createBitcoinInvoice(EscrowTransaction $escrow): array
    {
        if ($escrow->currency !== 'BTC') throw new RuntimeException('Bitcoin is the only supported currency.');
        $baseUrl = rtrim((string) config('bitcoin.shkeeper_url'), '/');
        $apiKey = (string) config('bitcoin.shkeeper_api_key');
        $callbackUrl = (string) config('bitcoin.callback_url');
        if (!$baseUrl || !$apiKey || !$callbackUrl) throw new RuntimeException('SHKeeper is not configured.');

        $quote = Http::timeout(20)->withHeaders(['X-Shkeeper-Api-Key' => $apiKey])->acceptJson()->post($baseUrl . '/api/v1/BTC/quote', ['fiat' => config('bitcoin.fiat_currency', 'USD'), 'amount' => '1.00']);
        if ($quote->failed() || !$quote->json('crypto_amount')) throw new RuntimeException('Unable to obtain the current SHKeeper BTC quote.');
        $btcPerFiat = (float) $quote->json('crypto_amount');
        if ($btcPerFiat <= 0) throw new RuntimeException('SHKeeper returned an invalid BTC quote.');
        $fiatAmount = number_format((float) $escrow->amount / $btcPerFiat, 8, '.', '');

        $response = Http::timeout(20)->withHeaders(['X-Shkeeper-Api-Key' => $apiKey])->acceptJson()->post($baseUrl . '/api/v1/BTC/payment_request', ['external_id' => (string) $escrow->id, 'fiat' => config('bitcoin.fiat_currency', 'USD'), 'amount' => $fiatAmount, 'callback_url' => $callbackUrl]);
        if ($response->failed() || ($response->json('status') ?? 'success') === 'error') throw new RuntimeException('SHKeeper invoice creation failed: ' . ($response->json('message') ?? $response->body()));
        $invoice = $response->json();
        $invoiceBtc = (string) ($invoice['amount'] ?? '');
        if ($invoiceBtc !== '' && $this->btcToSatoshis($invoiceBtc) !== $this->btcToSatoshis((string) $escrow->amount)) throw new RuntimeException('SHKeeper invoice amount does not match the escrow amount.');
        $escrow->update(['shkeeper_invoice_id' => (string) ($invoice['id'] ?? $escrow->id), 'bitcoin_payment_address' => $invoice['wallet'] ?? null]);
        return $invoice;
    }

    public function markFunded(EscrowTransaction $escrow, string $txid, int $confirmations, string|int|float $btcAmount): EscrowTransaction
    {
        if ($confirmations < (int) config('bitcoin.required_confirmations', 1)) return $escrow;
        if ($this->btcToSatoshis($btcAmount) < $this->btcToSatoshis((string) $escrow->amount)) throw new RuntimeException('Bitcoin payment is below the escrow amount.');
        return DB::transaction(function () use ($escrow, $txid, $confirmations, $btcAmount) {
            $escrow = EscrowTransaction::whereKey($escrow->id)->lockForUpdate()->firstOrFail();
            if (in_array($escrow->status, ['released', 'paid', 'refund_pending', 'refunded', 'cancelled'], true)) return $escrow;
            if ($escrow->bitcoin_txid && !hash_equals($escrow->bitcoin_txid, $txid)) throw new RuntimeException('Escrow already has a different transaction.');
            $amount = $this->normalizeBtcAmount($btcAmount);
            $escrow->transitionTo('funded');
            $escrow->update(['status' => 'funded', 'bitcoin_txid' => $txid, 'bitcoin_amount' => $amount, 'bitcoin_confirmations' => $confirmations, 'payment_detected_at' => $escrow->payment_detected_at ?: now(), 'payment_confirmed_at' => now(), 'funded_at' => $escrow->funded_at ?: now(), 'release_due_at' => $escrow->release_due_at ?: now()->addDays((int) config('escrow.hold_days', 3))]);
            if (!$escrow->ledgerEntries()->where('reference', $txid)->exists()) EscrowLedgerEntry::create(['escrow_transaction_id' => $escrow->id, 'type' => 'escrow_funded', 'amount' => $amount, 'currency' => 'BTC', 'reference' => $txid, 'metadata' => ['confirmations' => $confirmations, 'gateway' => 'shkeeper']]);
            return $escrow;
        });
    }

    public function release(EscrowTransaction $escrow, ?string $note = null): EscrowTransaction
    {
        return DB::transaction(function () use ($escrow, $note) {
            $escrow = EscrowTransaction::with('vendor', 'order')->whereKey($escrow->id)->lockForUpdate()->firstOrFail();
            if (!$escrow->canTransitionTo('released')) throw new RuntimeException('Only funded or dispute-eligible escrow can be released.');

            $feeSatoshis = $this->btcToSatoshis($escrow->platform_fee);
            $escrow->transitionTo('released');
            $escrow->update(['status' => 'released', 'released_at' => now(), 'release_note' => $note]);
            if (!$escrow->ledgerEntries()->where('type', 'seller_payout_due')->exists()) {
                EscrowLedgerEntry::create(['escrow_transaction_id' => $escrow->id, 'type' => 'seller_payout_due', 'amount' => $escrow->seller_amount, 'currency' => 'BTC', 'reference' => 'ESCROW-' . $escrow->id]);
            }
            PlatformRevenueEntry::firstOrCreate(['reference' => 'SALE-COMMISSION-ESCROW-' . $escrow->id], ['type' => 'sale_commission', 'vendor_id' => $escrow->vendor_id, 'order_id' => $escrow->order_id, 'escrow_transaction_id' => $escrow->id, 'amount_btc' => $this->satoshisToBtc($feeSatoshis), 'amount_satoshis' => $feeSatoshis, 'status' => 'earned', 'description' => '3% Kosher Market commission earned when escrow was released.', 'earned_at' => now()]);

            // v3: released proceeds become spendable in the vendor's internal wallet.
            // No external payout is created at escrow release.
            $this->wallets->creditFromEscrow($escrow);
            return $escrow;
        });
    }

    public function refund(EscrowTransaction $escrow, ?string $note = null): EscrowTransaction
    {
        return DB::transaction(function () use ($escrow, $note) {
            $escrow = EscrowTransaction::whereKey($escrow->id)->lockForUpdate()->firstOrFail();
            if (!$escrow->canTransitionTo('refund_pending')) throw new RuntimeException('Only funded or disputed escrow can be refunded.');
            $escrow->transitionTo('refund_pending');
            $escrow->update(['status' => 'refund_pending', 'refund_note' => $note]);
            if (!$escrow->ledgerEntries()->where('type', 'buyer_refund_due')->exists()) EscrowLedgerEntry::create(['escrow_transaction_id' => $escrow->id, 'type' => 'buyer_refund_due', 'amount' => $escrow->bitcoin_amount ?? $escrow->amount, 'currency' => 'BTC', 'reference' => 'REFUND-' . $escrow->id]);
            BitcoinSettlement::firstOrCreate(['escrow_transaction_id' => $escrow->id, 'type' => 'buyer_refund'], ['vendor_id' => null, 'amount' => $escrow->bitcoin_amount ?? $escrow->amount, 'currency' => 'BTC', 'status' => 'needs_destination']);
            return $escrow;
        });
    }

    public function submitSettlement(BitcoinSettlement $settlement): BitcoinSettlement
    {
        if ($settlement->status === 'completed') return $settlement;
        if (!$settlement->destination_address) throw new RuntimeException('Settlement destination is missing.');
        if (!preg_match('/^(bc1[ac-hj-np-z02-9]{11,87}|[13][a-km-zA-HJ-NP-Z1-9]{25,34})$/', $settlement->destination_address)) throw new RuntimeException('Only valid Bitcoin mainnet addresses are accepted.');
        $baseUrl = rtrim((string) config('bitcoin.shkeeper_url'), '/');
        $username = (string) config('bitcoin.shkeeper_username');
        $password = (string) config('bitcoin.shkeeper_password');
        if (!$baseUrl || !$username || !$password) throw new RuntimeException('SHKeeper payout credentials are not configured.');

        $externalId = 'settlement-' . $settlement->id;
        $response = Http::timeout(20)->withBasicAuth($username, $password)->acceptJson()->post($baseUrl . '/api/v1/BTC/payout', ['amount' => $this->normalizeBtcAmount($settlement->amount), 'destination' => $settlement->destination_address, 'fee' => config('bitcoin.payout_fee', '5'), 'external_id' => $externalId]);
        if ($response->failed() || ($response->json('status') ?? 'success') === 'error') {
            $settlement->update(['status' => 'failed', 'error_message' => $response->body()]);
            if ($settlement->type === 'vendor_withdrawal') $this->wallets->finalizeWithdrawal($settlement, false);
            throw new RuntimeException('SHKeeper payout request failed.');
        }
        $taskId = $response->json('task_id');
        if (!$taskId) throw new RuntimeException('SHKeeper did not return a payout task ID.');
        $settlement->update(['status' => 'in_progress', 'shkeeper_payout_id' => $taskId, 'submitted_at' => now(), 'error_message' => null]);
        return $settlement->fresh();
    }

    public function approveSettlement(BitcoinSettlement $settlement): BitcoinSettlement { return $this->syncSettlement($settlement); }

    public function syncSettlement(BitcoinSettlement $settlement): BitcoinSettlement
    {
        if (!$settlement->shkeeper_payout_id) return $settlement;
        $baseUrl = rtrim((string) config('bitcoin.shkeeper_url'), '/');
        $username = (string) config('bitcoin.shkeeper_username');
        $password = (string) config('bitcoin.shkeeper_password');
        $response = Http::timeout(20)->withBasicAuth($username, $password)->acceptJson()->get($baseUrl . '/api/v1/BTC/task/' . $settlement->shkeeper_payout_id);
        if ($response->failed()) throw new RuntimeException('Unable to retrieve SHKeeper payout status.');
        $payout = $response->json();
        $status = strtoupper((string) ($payout['status'] ?? 'PENDING'));
        $mapped = match ($status) { 'SUCCESS' => 'completed', 'FAILURE', 'FAIL' => 'failed', default => 'in_progress' };
        $txid = data_get($payout, 'result.0.txids.0') ?? data_get($payout, 'result.0.txid');
        $settlement->update(['status' => $mapped, 'bitcoin_txid' => $txid ?: $settlement->bitcoin_txid, 'completed_at' => $mapped === 'completed' ? ($settlement->completed_at ?: now()) : $settlement->completed_at, 'error_message' => $mapped === 'failed' ? 'SHKeeper payout failed.' : null]);

        if ($settlement->type === 'vendor_withdrawal' && in_array($mapped, ['completed', 'failed'], true)) {
            $this->wallets->finalizeWithdrawal($settlement, $mapped === 'completed');
        }
        if ($mapped === 'completed') {
            $escrow = $settlement->escrow()->first();
            if ($escrow && $settlement->type !== 'vendor_withdrawal' && $escrow->canTransitionTo('paid')) $escrow->update(['status' => 'paid']);
            if ($escrow && $settlement->type !== 'seller_payout' && $settlement->type !== 'vendor_withdrawal' && $escrow->canTransitionTo('refunded')) $escrow->update(['status' => 'refunded', 'refunded_at' => $escrow->refunded_at ?: now()]);
        }
        return $settlement->fresh();
    }
}
