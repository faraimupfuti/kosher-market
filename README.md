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
Buyer
  │
  ▼
Bitcoin Checkout
  │
  ▼
BTCPay Invoice
  │
  ▼
Bitcoin Payment
  │
  ▼
Escrow Funded
  │
  ├───────────────┐
  │               │
  ▼               ▼
Receipt         Dispute
  │               │
  ▼               ▼
Release         Admin Review
  │               │
  ▼          ┌────┴────┐
Payout       ▼         ▼
          Release    Refund
             │         │
             └────┬────┘
                  ▼
             Settlement
```

## Technology

- **Backend:** Laravel 10 / PHP 8.3+
- **Frontend:** Blade, Vite, JavaScript and Sass
- **Database:** MySQL 8-compatible relational database
- **Bitcoin payments:** BTCPay Server
- **Authentication:** Laravel authentication stack
- **Deployment:** Docker, Kubernetes and compatible hosting

## Ubuntu local installation

The following instructions are for Ubuntu development/testing. Do **not** use real Bitcoin while testing the escrow and payout system.

### 1. Install system packages

For Ubuntu systems using the standard PHP packages available to your release:

```bash
sudo apt update
sudo apt install -y git curl unzip mysql-server redis-server \
  php-cli php-common php-mysql php-sqlite3 php-mbstring php-bcmath \
  php-curl php-xml php-zip php-intl
```

`php-xml` is important: it provides the DOM and XML extensions required by Composer packages and PHPUnit.

Check PHP and the required extensions:

```bash
php -v
php -m | grep -E 'dom|xml|mbstring|bcmath|curl|intl|mysqli|pdo_mysql|pdo_sqlite|zip'
```

If your Ubuntu installation uses a versioned PHP package, install the matching XML package, for example `php8.4-xml`, rather than mixing PHP versions.

### 2. Install Composer

If Composer is not already installed:

```bash
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php composer-setup.php --install-dir=/usr/local/bin --filename=composer
rm composer-setup.php
composer --version
```

### 3. Install Node.js/npm

Kosher Market uses Vite for the frontend build. Use a supported LTS Node.js release for local development.

Verify:

```bash
node --version
npm --version
```

### 4. Clone the repository

```bash
git clone https://github.com/faraimupfuti/kosher-market.git
cd kosher-market
```

### 5. Install Laravel dependencies

```bash
composer install
```

If Composer reports missing `ext-dom` or `ext-xml`, install `php-xml` and rerun `composer install`. Do **not** use `--ignore-platform-req` as the normal solution.

### 6. Install frontend dependencies

```bash
npm ci
```

### 7. Configure the environment

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env` and configure at minimum:

```dotenv
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=kosher_market
DB_USERNAME=kosher_market
DB_PASSWORD=change-this-password
```

Configure Redis and BTCPay variables according to the environment being tested. Never commit `.env`, Bitcoin API credentials, wallet credentials, private keys or webhook secrets.

### 8. Create the local MySQL database

Example:

```bash
sudo mysql
```

Then:

```sql
CREATE DATABASE kosher_market CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'kosher_market'@'localhost' IDENTIFIED BY 'change-this-password';
GRANT ALL PRIVILEGES ON kosher_market.* TO 'kosher_market'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### 9. Run migrations

```bash
php artisan migrate
```

If seed data is provided and you specifically want the development seed data:

```bash
php artisan migrate:fresh --seed
```

Do not run `migrate:fresh` against a database containing data you need to preserve.

### 10. Build the frontend

```bash
npm run build
```

For development with Vite hot reload:

```bash
npm run dev
```

### 11. Start Laravel

```bash
php artisan serve --host=127.0.0.1 --port=8000
```

Open:

```text
http://127.0.0.1:8000
```

### 12. Run the queue worker

In a second terminal:

```bash
php artisan queue:work
```

For scheduler-driven tasks during development:

```bash
php artisan schedule:work
```

Redis should be running if your local `.env` config uses Redis:

```bash
sudo systemctl enable --now redis-server
```

## Local testing

Run the automated test suite with:

```bash
php artisan test
```

Check the application configuration with:

```bash
php artisan about
```

For a clean test database, use the test environment rather than your development database.

## Bitcoin / BTCPay Server

Kosher Market uses the application layer to coordinate orders, escrow state, disputes and settlement records. Bitcoin wallet custody and transaction signing must remain outside the Laravel web application.

Required Bitcoin infrastructure for an integration environment:

- A running BTCPay Server instance
- A Bitcoin wallet configured in BTCPay
- A dedicated BTCPay store for Kosher Market
- A restricted BTCPay API key with only required permissions
- A BTCPay webhook configured for the marketplace

For local/integration testing, use Bitcoin testnet or another explicitly non-production environment where supported by your BTCPay setup. Never place a Bitcoin seed phrase or private key in `.env` or source control.

## Financial workflow

The intended marketplace financial model includes:

1. A vendor registration fee of **USD 200 equivalent in BTC**, subject to the configured exchange-rate policy.
2. Automatic vendor activation only after the registration payment has been independently verified.
3. A **3% platform commission** on eligible completed vendor sales.
4. The remaining vendor entitlement becomes eligible for payout according to the escrow/release policy.
5. Payouts and refunds are recorded as auditable settlements.

These financial controls must be integration-tested before real funds are accepted.

## Production safety

Before accepting real Bitcoin, verify:

1. All database migrations complete successfully on a clean database.
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

## Docker

The repository is intended to support containerized development/deployment. When using Docker, ensure the Laravel application, queue workers, scheduler, Redis and MySQL services are configured consistently with the same application environment.

## Kubernetes

The target architecture supports running the database service inside Kubernetes with persistent storage:

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

Before production use, configure persistent volumes, readiness/liveness probes, resource requests/limits, PodDisruptionBudgets, database backups, secrets management and a tested restore procedure.

## CI

GitHub Actions validates the project using PHP 8.3, MySQL 8 and the Vite production build. The CI pipeline installs Composer dependencies, runs Pint, builds the frontend, runs database migrations and executes PHPUnit/Laravel tests.

A local environment should reproduce the same PHP extension requirements as CI where possible.

## Project status

Kosher Market is under active development. The Bitcoin escrow, payment, dispute, settlement, payout, shipping and Kubernetes architecture is being hardened before production use with real funds.
