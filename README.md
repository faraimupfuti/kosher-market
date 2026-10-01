# Kosher Market

**Monero-first multi-vendor marketplace with manually controlled escrow and vendor wallets.**

Kosher Market is a Laravel marketplace for buyers, vendors and administrators. It is Docker-first, uses SHKeeper as the cryptocurrency gateway, and keeps all financially consequential marketplace actions under explicit human control.

## Currency and payment gateway

**Primary marketplace currency: XMR (Monero).**

SHKeeper is used for:
- XMR invoice creation
- XMR payment-address generation
- payment callbacks
- XMR transaction lookup
- manually initiated XMR payouts

SHKeeper documents XMR support, invoice-based payment addresses, HMAC-SHA256 webhook signing, and the XMR single-payout API. citeturn2search0turn1search1

Configure the callback URL as:

```text
https://YOUR-MARKET-DOMAIN/monero/shkeeper/webhook
```

Never expose SHKeeper credentials, wallet seed material or private keys in source control.

## Escrow

The payment lifecycle is:

```text
Buyer
  ↓
SHKeeper XMR invoice
  ↓
Unique XMR payment address
  ↓
SHKeeper payment callback
  ↓
Webhook signature + timestamp validation
  ↓
Escrow funded after configured confirmations
  ↓
Administrator manually releases or refunds
```

SHKeeper's callback is verified against the raw HTTP body using HMAC-SHA256 and the API key, with timestamp replay protection. citeturn1search1

Duplicate callbacks are idempotent. Escrow state transitions are locked inside database transactions so two concurrent administrators cannot release the same escrow twice.

## Vendor wallet

Every vendor has an internal XMR wallet ledger.

It tracks:

- available XMR
- XMR locked in pending withdrawals
- SHKeeper-backed XMR deposit address
- deposits
- escrow credits
- withdrawal reservations
- completed withdrawals
- reversed withdrawals

When an administrator releases an escrow, the vendor's net XMR proceeds are credited to the internal wallet. They are **not automatically paid to an external address**.

Vendor withdrawal flow:

```text
Vendor requests withdrawal
        ↓
Wallet balance is locked
        ↓
Administrator reviews
        ↓
Administrator submits SHKeeper XMR payout
        ↓
Administrator synchronizes payout status
        ↓
Wallet withdrawal completes or is reversed
```

No automatic payout worker exists.

## XMR accounting

Monero uses 12 decimal places. Financial calculations use integer atomic units with BCMath rather than floating-point arithmetic.

The wallet invariant is:

```text
available atomic units + locked atomic units
= vendor wallet ledger balance
```

Wallet mutations and escrow credits occur inside database transactions with row locking.

## Overpayments and partial payments

SHKeeper can report PARTIAL, PAID and OVERPAID invoice states. Kosher Market does not treat a partial payment as funded. An overpaid invoice is accepted only after the full escrow amount is covered; the excess must be handled according to the marketplace's refund/credit policy before real-money operation. citeturn2search0

## Production safety

The production environment must use:

```env
APP_ENV=production
APP_DEBUG=false
```

Laravel recommends production configuration/event/route/view caching and explicitly warns against enabling debug mode in production. citeturn0search3

After the production environment is configured, run:

```bash
docker compose exec app php artisan optimize
```

Do **not** run `php artisan optimize` until the production environment variables are loaded, because Laravel's cached configuration must contain the intended production values. citeturn0search0

Health endpoint:

```text
GET /up
```

The endpoint checks application boot and database connectivity and returns HTTP 503 when the database is unavailable.

## Docker

Create the environment file:

```bash
cp .env.production.example .env
```

Generate an application key:

```bash
docker compose run --rm app php artisan key:generate
```

Build:

```bash
docker compose build
```

Start manually:

```bash
docker compose up -d
```

Run migrations manually:

```bash
docker compose exec app php artisan migrate --force
```

Optimize:

```bash
docker compose exec app php artisan optimize
```

Stop:

```bash
docker compose down
```

The Docker runtime does not start Laravel schedulers or queue workers and does not automatically release escrow or submit cryptocurrency payouts.

## Backups and recovery

A production operator must back up:
- MySQL
- Laravel storage that contains business-critical files
- SHKeeper wallet data according to SHKeeper's own backup/recovery procedure

Backups must be encrypted and restoration must be tested periodically. A backup that has never been restored is not a verified recovery mechanism.

## Monitoring

Monitor:

- `GET /up`
- MySQL availability
- application error logs
- SHKeeper connectivity
- failed webhook processing
- escrow state anomalies
- wallet ledger/reconciliation differences
- pending withdrawals
- failed SHKeeper payouts
- administrator financial actions

## Manual financial operations

Kosher Market intentionally does not automate these actions:

- escrow release
- escrow refund
- wallet reconciliation
- vendor withdrawal approval
- SHKeeper payout submission
- payout reconciliation

This is a deliberate operational control.

## Required pre-launch tests

Before accepting real XMR, test the complete SHKeeper demo/testnet flow:

1. Create an XMR invoice.
2. Verify the returned XMR amount and address.
3. Make a test payment.
4. Verify the signed callback.
5. Replay the callback and confirm no duplicate credit.
6. Test a partial payment.
7. Test an overpayment.
8. Verify confirmation handling.
9. Fund escrow.
10. Release escrow once.
11. Confirm the vendor wallet credit.
12. Request a vendor withdrawal.
13. Confirm wallet reservation.
14. Submit the XMR payout manually.
15. Synchronize the payout status.
16. Test failed payout reversal.
17. Test a refund.
18. Test a dispute.
19. Verify database backup and restore.

SHKeeper provides a demo environment operating on testnet, which is appropriate for integration testing before real funds are introduced. citeturn1search0

## Important

This repository should be considered **pre-production until the complete XMR/SHKeeper integration has been executed against a real SHKeeper test environment and the financial reconciliation tests have passed**.

The code contains production hardening, but software-level hardening cannot substitute for an end-to-end payment test with the actual gateway and Monero environment.
