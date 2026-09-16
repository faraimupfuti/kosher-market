<?php

namespace Tests\Feature;

use App\Models\BtcpayWebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BitcoinWebhookSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_rejects_missing_signature(): void
    {
        config(['bitcoin.webhook_secret' => 'test-secret']);

        $response = $this->postJson(route('bitcoin.btcpay.webhook'), [
            'type' => 'TestEvent',
        ]);

        $response->assertUnauthorized();
        $this->assertDatabaseCount('btcpay_webhook_events', 0);
    }

    public function test_webhook_rejects_invalid_signature(): void
    {
        config(['bitcoin.webhook_secret' => 'test-secret']);
        $payload = ['type' => 'TestEvent'];

        $response = $this->postJson(
            route('bitcoin.btcpay.webhook'),
            $payload,
            ['BTCPay-Sig' => 'sha256=invalid']
        );

        $response->assertUnauthorized();
        $this->assertDatabaseCount('btcpay_webhook_events', 0);
    }

    public function test_webhook_records_and_idempotently_accepts_duplicate_event(): void
    {
        $secret = 'test-secret';
        config(['bitcoin.webhook_secret' => $secret]);
        $payload = ['type' => 'TestEvent', 'deliveryId' => 'delivery-001'];
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = 'sha256='.hash_hmac('sha256', $body, $secret);

        $first = $this->call(
            'POST',
            route('bitcoin.btcpay.webhook'),
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_BTCPAY_SIG' => $signature,
            ],
            $body
        );

        $first->assertOk()->assertJson(['received' => true]);
        $this->assertDatabaseCount('btcpay_webhook_events', 1);
        $this->assertNotNull(BtcpayWebhookEvent::first()->processed_at);

        $second = $this->call(
            'POST',
            route('bitcoin.btcpay.webhook'),
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_BTCPAY_SIG' => $signature,
            ],
            $body
        );

        $second->assertOk()->assertJson(['received' => true, 'duplicate' => true]);
        $this->assertDatabaseCount('btcpay_webhook_events', 1);
    }
}
