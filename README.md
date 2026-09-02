# Kosher Market

**Bitcoin-only multi-vendor marketplace with escrow-protected transactions.**

Kosher Market is a Laravel-based marketplace designed around one simple principle: buyers and sellers should be able to transact using Bitcoin with a transparent escrow and settlement workflow.

## Core features

- ₿ **Bitcoin-only payments** — marketplace transactions are denominated and settled in BTC.
- 🔐 **Escrow protection** — buyer funds remain in escrow until the transaction is completed or a dispute is resolved.
- 🧾 **BTCPay Server integration** — Bitcoin invoices and payment verification are handled through BTCPay Server.
- 🤝 **Multi-vendor marketplace** — vendors can list products and manage marketplace activity.
- 💰 **Vendor Bitcoin payouts** — vendors can configure a Bitcoin payout address and track settlements.
- ⚖️ **Dispute resolution** — buyers can open disputes and administrators can resolve them through release or refund workflows.
- ↩️ **Bitcoin refunds** — refunds are represented as settlements and tracked through their lifecycle.
- 🏦 **Settlement queue** — seller payouts and buyer refunds are auditable and can be synchronized with BTCPay.
- 🛡️ **Webhook verification** — BTCPay webhook signatures are validated before payment events are processed.
- 🔒 **No private keys in Laravel** — wallet custody/signing remains outside the web application.
- 👤 **Buyer, vendor and administrator workflows**.
- 📦 **Product and order management**.
- 🌍 **Global marketplace and shipping foundation**.
- 📊 **Administrative monitoring and transaction records**.

## Transaction lifecycle

```text
Buyer → Bitcoin Checkout → BTCPay Invoice → Bitcoin Payment → Escrow Funded
                                                   │
                                    ┌──────────────┴──────────────┐
                                    ▼                             ▼
                                 Receipt                       Dispute
                                    │                             │
                                    ▼                             ▼
                                  Release                    Admin Review
                                    │                       ┌─────┴─────┐
                                    ▼                       ▼           ▼
                                  Payout                 Release      Refund
                                                              \       /
                                                               Settlement
```

## Technology

- **Backend:** Laravel 10 / PHP 8.3+
- **Frontend:** Blade, Vite, JavaScript and Sass
- **Database:** MySQL 8-compatible relational database
- **Bitcoin payments:** BTCPay Server
- **Authentication:** Laravel authentication stack
- **Local containers:** Podman + Podman Compose
- **Production orchestration:** Kubernetes

## Ubuntu local installation

The following instructions are for Ubuntu development/testing. Do **not** use real Bitcoin while testing the escrow and payout system.

### Option A — native Ubuntu PHP/MySQL setup

Install the required packages:

```bash
sudo apt update
sudo apt install -y git curl unzip mysql-server redis-server \
  php-cli php-common php-mysql php-sqlite3 php-mbstring php-bcmath \
  php-curl php-xml php-zip php-intl
```

`php-xml` provides the DOM and XML extensions required by Composer packages and PHPUnit.

Check PHP/extensions:

```bash
php -v
php -m | grep -E 'dom|xml|mbstring|bcmath|curl|intl|mysqli|pdo_mysql|pdo_sqlite|zip'
```

Install Composer if necessary:

```bash
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php composer-setup.php --install-dir=/usr/local/bin --filename=composer
rm composer-setup.php
composer --version
```

Install/use a supported Node.js LTS release and verify:

```bash
node --version
npm --version
```

Clone the repository:

```bash
git clone https://github.com/faraimupfuti/kosher-market.git
cd kosher-market
```

Install dependencies:

```bash
composer install
npm ci
```

If Composer reports missing `ext-dom` or `ext-xml`, install `php-xml` and rerun Composer. Do **not** use `--ignore-platform-req` as the normal solution.

Configure Laravel:

```bash
cp .env.example .env
php artisan key:generate
```

Configure MySQL, Redis and BTCPay values in `.env`. Never commit `.env`, API credentials, wallet credentials, private keys or webhook secrets.

Create the local database if required:

```sql
CREATE DATABASE kosher_market CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'kosher_market'@'localhost' IDENTIFIED BY 'change-this-password';
GRANT ALL PRIVILEGES ON kosher_market.* TO 'kosher_market'@'localhost';
FLUSH PRIVILEGES;
```

Run the application:

```bash
php artisan migrate
npm run build
php artisan serve --host=127.0.0.1 --port=8000
```

For frontend hot reload:

```bash
npm run dev
```

In separate terminals, run workers/scheduler as needed:

```bash
php artisan queue:work
php artisan schedule:work
```

## Podman local development — recommended container workflow

**Podman is the recommended container runtime for local development. Docker is not required.** The repository provides `Containerfile.local` and `podman-compose.yml` for the complete Laravel stack.

### 1. Install Podman on Ubuntu

```bash
sudo apt update
sudo apt install -y podman podman-compose
```

Verify:

```bash
podman --version
podman-compose --version
```

### 2. Configure the environment

From the repository root:

```bash
cp .env.podman.example .env
php -r 'echo "APP_KEY=" . base64_encode(random_bytes(32)) . PHP_EOL;'
```

For Laravel, generate the application key inside the application container after the image is built:

```bash
podman-compose -f podman-compose.yml run --rm app php artisan key:generate --force
```

If `.env` already contains a valid `APP_KEY`, keep it.

### 3. Build and start the stack

```bash
podman-compose -f podman-compose.yml build
podman-compose -f podman-compose.yml up -d
```

The stack contains:

- Laravel/PHP application
- MySQL 8
- Redis 7
- Laravel queue worker
- Laravel scheduler
- Persistent MySQL, Redis and Laravel storage volumes

Check services:

```bash
podman-compose -f podman-compose.yml ps
```

View logs:

```bash
podman-compose -f podman-compose.yml logs -f app
```

The marketplace is available at:

```text
http://localhost:8000
```

MySQL is exposed on host port `3307` and Redis on host port `6380`. Inside the Podman network Laravel uses `mysql:3306` and `redis:6379`.

Useful commands:

```bash
podman-compose -f podman-compose.yml exec app php artisan about
podman-compose -f podman-compose.yml exec app php artisan migrate:status
podman-compose -f podman-compose.yml exec app php artisan test
podman-compose -f podman-compose.yml exec app php artisan optimize
podman-compose -f podman-compose.yml down
```

To remove the development database and Redis data:

```bash
podman-compose -f podman-compose.yml down -v
```

**Warning:** `down -v` permanently deletes the development volumes, including local MySQL data.

The Podman stack is strictly for local development/testing. Never place production wallet secrets or real customer funds in it.

### Podman without Podman Compose

The project can also be built directly with Podman:

```bash
podman build -f Containerfile.local -t kosher-market:local .
```

For the complete multi-service environment, use `podman-compose.yml`.

## Local testing

Run the automated test suite:

```bash
php artisan test
```

Inside the Podman application container:

```bash
podman-compose -f podman-compose.yml exec app php artisan test
```

Check application configuration:

```bash
php artisan about
```

## Bitcoin / BTCPay Server

Kosher Market uses the application layer to coordinate orders, escrow state, disputes and settlement records. Bitcoin wallet custody and transaction signing must remain outside the Laravel web application.

Required integration infrastructure:

- Running BTCPay Server instance
- Bitcoin wallet configured in BTCPay
- Dedicated BTCPay store for Kosher Market
- Restricted BTCPay API key with only required permissions
- Marketplace BTCPay webhook

For local/integration testing, use Bitcoin testnet or another explicitly non-production environment where supported. Never place a Bitcoin seed phrase or private key in `.env` or source control.

## Financial workflow

The intended marketplace financial model includes:

1. Vendor registration fee of **USD 200 equivalent in BTC**, subject to the configured exchange-rate policy.
2. Automatic vendor activation only after registration payment is independently verified.
3. **3% platform commission** on eligible completed vendor sales.
4. Remaining vendor entitlement becomes eligible for payout according to the escrow/release policy.
5. Payouts and refunds are recorded as auditable settlements.

These financial controls must be integration-tested before real funds are accepted.

## Production safety

Before accepting real Bitcoin, verify:

1. All migrations complete successfully on a clean database.
2. The application boots without exceptions.
3. BTCPay invoice creation works.
4. BTCPay webhook signatures are validated.
5. Duplicate webhook delivery is idempotent.
6. A testnet/sandbox transaction reaches the expected escrow state.
7. Vendor registration activation occurs only after confirmed payment.
8. The 3% platform commission is calculated exactly once per eligible sale.
9. Vendor payout addresses are verified.
10. Payout creation is idempotent and recoverable after failure.
11. Refunds and disputes are tested end-to-end.
12. Admin financial controls require authorization.
13. Backups and restore procedures have been tested.
14. Logs, monitoring and alerting are configured.
15. Kubernetes health checks and persistent MySQL storage have been tested if Kubernetes is used.

**Do not use the application to hold real Bitcoin until the complete deployment, wallet, webhook, payout and recovery procedures have been independently tested.**

## Kubernetes

The repository includes Kubernetes manifests for the target architecture, including a MySQL StatefulSet with persistent storage, Redis, web, worker, scheduler, ingress, HPA, PDB, network policy, migration job and backup configuration.

Target architecture:

```text
                    Ingress
                       │
              ┌────────┴────────┐
              │ Laravel Web Pods │
              └────────┬────────┘
                       │
             ┌─────────┴─────────┐
             │                   │
           Redis               MySQL
             │              StatefulSet
             │              + Persistent
       Queue Workers          Volume
             │
         Scheduler
```

Before production use, configure persistent volumes, readiness/liveness probes, resource requests/limits, PodDisruptionBudgets, database backups, secrets management and a tested restore procedure. Kubernetes should be validated against a staging cluster before production traffic is enabled.

## CI

GitHub Actions validates the project using PHP 8.3, MySQL 8 and the Vite production build. The CI pipeline installs Composer dependencies, runs Pint, builds the frontend, runs database migrations and executes PHPUnit/Laravel tests.

The local Podman environment uses the same PHP 8.3 extension requirements as the CI environment where possible.

## Project status

Kosher Market is under active development. The Bitcoin escrow, payment, dispute, settlement, payout, shipping and Kubernetes architecture is being hardened before production use with real funds.
