# Velstore Bitcoin Escrow

Velstore is configured as a Bitcoin-only marketplace. Laravel does not store Bitcoin private keys or seed phrases; BTCPay Server handles wallet custody, signing, and on-chain payout processing.

## Required environment

```env
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:...
APP_URL=https://your-domain.example

DB_CONNECTION=mysql
DB_HOST=...
DB_PORT=3306
DB_DATABASE=...
DB_USERNAME=...
DB_PASSWORD=...

BTCPAY_URL=https://btcpay.example.com
BTCPAY_STORE_ID=...
BTCPAY_API_KEY=...
BTCPAY_WEBHOOK_SECRET=...
BITCOIN_REQUIRED_CONFIRMATIONS=1

ESCROW_PLATFORM_FEE_PERCENT=2.5
ESCROW_HOLD_DAYS=3
ESCROW_AUTO_RELEASE=true
```

## BTCPay API permissions

Use a dedicated API key. Production permissions should be restricted to the store and the operations Velstore needs: invoice viewing/creation, payout creation/management, and webhook-related access. Do not use an unrestricted administrator key.

For seller payouts, Velstore creates an on-chain `BTC-CHAIN` payout with approval disabled. An administrator can approve it from the Velstore settlement dashboard. BTCPay then performs the actual wallet operation.

## Webhooks

Configure the BTCPay webhook endpoint:

`POST https://your-domain.example/bitcoin/btcpay/webhook`

Use the same secret as `BTCPAY_WEBHOOK_SECRET` and enable invoice settlement and payout update/approval events. Velstore verifies the `BTCPay-Sig` HMAC before processing the event.

## Settlement lifecycle

1. Buyer creates a BTC order.
2. Velstore creates an escrow record and BTCPay invoice.
3. BTCPay confirms the invoice; Velstore marks the escrow funded.
4. Buyer confirms receipt or the configured hold period expires.
5. Velstore creates a seller settlement.
6. BTCPay payout is created with approval disabled.
7. Admin verifies/approves the payout.
8. BTCPay sends BTC from the configured wallet.
9. Velstore synchronizes the payout and records the transaction ID.
10. Seller settlement becomes `completed` and escrow becomes `paid`.

Refunds follow the same settlement mechanism, but the administrator must supply and verify the buyer's Bitcoin refund destination before submission.

## Render

The repository includes `render.yaml` and a Dockerfile. The Docker image installs PHP, Composer, Node, and the Laravel dependencies, builds Vite assets, runs migrations on web startup, and serves Laravel on Render's `$PORT`.

The Render cron service invokes `php artisan schedule:run` every five minutes so eligible escrow releases and Bitcoin settlement synchronization continue running without requiring a long-running scheduler process in the web container.
