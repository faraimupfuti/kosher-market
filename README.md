# Kosher Market

**Bitcoin-only multi-vendor marketplace with escrow-protected transactions.**

Kosher Market is a Laravel marketplace for buyers, vendors and administrators. The repository is intentionally **Docker-first** and **manually operated**.

## Operating model

The application does not run settlement, escrow-release or reconciliation schedules automatically.

- No Laravel scheduler service is started by Docker Compose.
- No background queue worker is started by Docker Compose.
- `QUEUE_CONNECTION=sync`, so application jobs execute synchronously with the initiating request.
- `ESCROW_AUTO_RELEASE=false`.
- GitHub Actions, Render, Netlify and Kubernetes deployment automation are not part of the supported runtime.
- Containers use `restart: "no"`, so starting and stopping the application is an explicit operator action.

The underlying Artisan commands are still available for an administrator to run manually after review:

```bash
docker compose exec app php artisan escrow:release-eligible
docker compose exec app php artisan bitcoin:settlements-sync
docker compose exec app php artisan bitcoin:reconcile-payouts --limit=25
```

## Technology

- Laravel / PHP 8.3
- Blade, Vite, JavaScript and Sass
- MySQL 8
- BTCPay Server
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

Review the BTCPay and marketplace settings in `.env`. Never commit wallet credentials, API secrets, seed phrases, private keys or webhook secrets.

### 2. Build the images

```bash
docker compose build
```

### 3. Start the application manually

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

Database migrations are deliberately not executed on container startup.

### 5. Logs

```bash
docker compose logs -f app
```

### 6. Stop the application manually

```bash
docker compose down
```

To remove local database/application volumes as well:

```bash
docker compose down -v
```

**Warning:** `docker compose down -v` permanently removes the local MySQL and application-storage volumes.

## Manual Bitcoin operations

Kosher Market coordinates order, escrow, dispute and settlement records, but wallet custody/signing stays outside the Laravel application.

Before manually releasing escrow or processing settlements, verify the relevant order, payment confirmations, dispute state, vendor payout address and BTCPay records.

Useful manual commands:

```bash
# Review and release eligible escrow records
docker compose exec app php artisan escrow:release-eligible

# Synchronize settlement state with BTCPay
docker compose exec app php artisan bitcoin:settlements-sync

# Reconcile a bounded number of payout records
docker compose exec app php artisan bitcoin:reconcile-payouts --limit=25
```

## Development checks

Checks are run manually:

```bash
docker compose exec app php artisan test
```

Frontend and PHP dependencies are built into the Docker image. Rebuild after dependency or frontend changes:

```bash
docker compose build --no-cache
docker compose up -d
```

## Production safety

Do not use the application to hold or coordinate real Bitcoin until invoice creation, webhook verification, idempotency, confirmation handling, escrow transitions, payout-address verification, disputes/refunds, backup/recovery and operator procedures have been independently tested.
