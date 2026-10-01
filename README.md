# Kosher Market

**Bitcoin-only multi-vendor marketplace with escrow-protected transactions.**

Kosher Market is a Laravel marketplace for buyers, vendors and administrators. The repository is **Docker-first**, uses **SHKeeper** as its cryptocurrency payment gateway, and is intentionally **manually operated**.

## Payment gateway

SHKeeper is used for Bitcoin invoice creation, payment-address generation, payment callbacks and manually initiated payouts.

Configure the SHKeeper wallet for BTC and set the webhook callback URL to:

```text
https://YOUR-MARKET-DOMAIN/bitcoin/shkeeper/webhook
```

SHKeeper webhook requests are verified using `X-Shkeeper-Timestamp` and `X-Shkeeper-Signature` before a payment is recorded.

## Operating model

The marketplace does not run settlement, escrow-release or reconciliation schedules automatically.

- No Laravel scheduler service is started by Docker Compose.
- No background queue worker is started by Docker Compose.
- `QUEUE_CONNECTION=sync`.
- `ESCROW_AUTO_RELEASE=false`.
- `BITCOIN_AUTO_PAYOUTS=false`.
- Containers use `restart: "no"`.
- Escrow release and cryptocurrency payouts require explicit administrator action.
- Database migrations are run manually.

The underlying Artisan commands remain available for explicit operator use:

```bash
docker compose exec app php artisan escrow:release-eligible
docker compose exec app php artisan bitcoin:settlements-sync
docker compose exec app php artisan bitcoin:reconcile-payouts --limit=25
```

## Technology

- Laravel / PHP 8.3
- Blade, Vite, JavaScript and Sass
- MySQL 8
- SHKeeper
- Docker and Docker Compose

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

## Manual Bitcoin operations

SHKeeper handles blockchain payment detection and provides payment callbacks. Kosher Market records the callback only after signature validation and payment checks.

Escrow release remains a marketplace decision. Releasing an escrow creates a pending seller settlement; it does **not** automatically submit the payout to SHKeeper.

An administrator can explicitly submit a settlement through the existing admin settlement interface or manually invoke the settlement command. SHKeeper's payout API uses HTTP Basic authentication and returns an asynchronous payout task that can subsequently be checked.

Useful commands:

```bash
# Review and release eligible escrow records
docker compose exec app php artisan escrow:release-eligible

# Manually submit/synchronize settlements
docker compose exec app php artisan bitcoin:settlements-sync

# Manually reconcile a bounded number of payout records
docker compose exec app php artisan bitcoin:reconcile-payouts --limit=25
```

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

Before accepting real Bitcoin, independently test invoice creation, webhook signature verification, replay protection, idempotency, partial/overpayment handling, confirmation policy, escrow transitions, vendor payout-address verification, payout failures, refunds, disputes, backups and recovery procedures.
