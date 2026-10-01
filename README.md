# Kosher Market

**Bitcoin-only multi-vendor marketplace with escrow-protected transactions.**

Kosher Market is a Laravel marketplace for buyers, vendors and administrators. The repository is **Docker-first**, uses **SHKeeper** as its cryptocurrency payment gateway, and is intentionally **manually operated**.

## Payment gateway

SHKeeper is used for Bitcoin invoice creation, payment-address generation, payment callbacks, wallet-address allocation and manually initiated payouts. SHKeeper's API provides generated addresses and address transaction lookup in addition to invoice and payout APIs.

Configure the SHKeeper wallet for BTC and set the webhook callback URL to:

```text
https://YOUR-MARKET-DOMAIN/bitcoin/shkeeper/webhook
```

SHKeeper webhook requests are verified before a payment is recorded.

## Vendor wallets

Version 3 introduces an internal BTC wallet for every vendor.

Each vendor wallet has:

- available BTC balance
- BTC locked in pending withdrawals
- a SHKeeper-backed BTC deposit address
- an immutable wallet transaction ledger
- escrow-credit transactions
- deposit transactions
- withdrawal holds, completions and reversals

When an administrator releases a buyer escrow, the vendor's net proceeds are **credited to the vendor's Kosher Market wallet**. The marketplace does not immediately send those funds to an external address.

Vendors can then:

1. Open **Vendor → Bitcoin Wallet**.
2. Generate their BTC deposit address if they want to deposit BTC directly.
3. Manually synchronize confirmed deposits from SHKeeper.
4. Configure and verify a Bitcoin withdrawal address.
5. Request a withdrawal from their available wallet balance.
6. Wait for administrator review and manual SHKeeper payout submission.

Wallet deposits are intentionally synchronized manually rather than by a scheduler, consistent with the project's manual-operation requirement.

## Operating model

The marketplace does not run settlement, escrow-release or wallet-reconciliation schedules automatically.

- No Laravel scheduler service is started by Docker Compose.
- No background queue worker is started by Docker Compose.
- `QUEUE_CONNECTION=sync`.
- `ESCROW_AUTO_RELEASE=false`.
- `BITCOIN_AUTO_PAYOUTS=false`.
- Containers use `restart: "no"`.
- Escrow release and cryptocurrency payouts require explicit administrator action.
- Vendor wallet deposit synchronization requires an explicit vendor action.
- Database migrations are run manually.

## Docker setup

### 1. Create the environment file

```bash
cp .env.docker.example .env
```

Generate the Laravel application key inside Docker:

```bash
docker compose run --rm app php artisan key:generate
```

Configure these SHKeeper variables in `.env`:

```env
SHKEEPER_URL=https://your-shkeeper-domain
SHKEEPER_API_KEY=your_wallet_api_key
SHKEEPER_USERNAME=your_shkeeper_username
SHKEEPER_PASSWORD=your_shkeeper_password
SHKEEPER_FIAT=USD
SHKEEPER_CALLBACK_URL=https://your-market-domain/bitcoin/shkeeper/webhook
SHKEEPER_WEBHOOK_TOLERANCE_SECONDS=300
SHKEEPER_BTC_PAYOUT_FEE=5
```

Never commit wallet credentials, API keys, passwords, private keys or webhook secrets.

### 2. Build

```bash
docker compose build
```

### 3. Start manually

```bash
docker compose up -d
```

The application is available at:

```text
http://localhost:8000
```

### 4. Run migrations manually

```bash
docker compose exec app php artisan migrate
```

### 5. Logs

```bash
docker compose logs -f app
```

### 6. Stop manually

```bash
docker compose down
```

## Wallet and escrow flow

```text
Buyer
  │
  │ BTC payment
  ▼
SHKeeper invoice
  │
  ▼
Kosher Market escrow
  │
  │ Administrator releases order
  ▼
Vendor Kosher Market Wallet
  │
  │ Vendor requests withdrawal
  ▼
Admin reviews withdrawal
  │
  │ Admin submits payout
  ▼
SHKeeper
  │
  ▼
Vendor's verified external BTC address
```

Kosher Market does **not** store Bitcoin private keys or seed phrases. The vendor wallet in the application is an internal ledger backed by the configured SHKeeper wallet infrastructure. The application never asks a vendor for a seed phrase or private key.

## Manual Bitcoin operations

SHKeeper handles blockchain payment detection and provides payment callbacks. Kosher Market records payment only after validation and idempotency checks.

Escrow release is a marketplace decision. In v3, releasing escrow credits the vendor's internal wallet; it does **not** create an immediate external payout.

Vendor withdrawals create a pending settlement and reserve the requested amount in the vendor wallet. An administrator explicitly submits the withdrawal to SHKeeper. SHKeeper's payout API is asynchronous, so the administrator can subsequently synchronize the payout status.

## Development checks

```bash
docker compose exec app php artisan test
```

Rebuild after dependency or frontend changes:

```bash
docker compose build --no-cache
docker compose up -d
```

## Production safety

Before accepting real Bitcoin, independently test invoice creation, webhook signature verification, replay protection, idempotency, partial/overpayment handling, confirmation policy, escrow transitions, wallet deposit reconciliation, wallet accounting, withdrawal reservation/reversal, payout failures, refunds, disputes, backups and recovery procedures.
