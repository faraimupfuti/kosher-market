# Kosher Market

**Bitcoin-only multi-vendor marketplace with escrow-protected transactions.**

Kosher Market is a Laravel marketplace designed around one simple principle: buyers and sellers should be able to transact using Bitcoin with transparent escrow, reputation and buyer-protection workflows.

## Core features

- ₿ **Bitcoin-only payments** — marketplace transactions are denominated and settled in BTC.
- 🔐 **Escrow protection** — buyer funds remain in escrow until the transaction is completed or a dispute is resolved.
- 🧾 **BTCPay Server integration** — Bitcoin invoices and payment verification are handled through BTCPay Server.
- 🤝 **Multi-vendor marketplace** — vendors can list products and manage marketplace activity.
- 🛡️ **Vendor trust scoring** — public trust scores, verification badges and performance signals.
- ⚖️ **Trust & Safety Center** — buyer disputes, vendor responses, evidence submission and administrative resolution.
- 📦 **Order tracking** — timestamped shipment and delivery lifecycle events.
- 🔎 **Advanced search** — price, rating, vendor, category and popularity filters.
- 🧠 **Smart search** — natural-language-style queries such as "4K monitor under 0.01 BTC rated 4.5" are converted into marketplace filters.
- 🔖 **Saved searches** — customers can save filtered searches for repeat discovery.
- 📊 **Vendor analytics** — sales, order activity, disputes and trust-score metrics.
- ❤️ **Wishlist** — save products for later.
- 🎟️ **Coupons/promotions** — marketplace discount infrastructure.
- 💬 **Buyer/vendor chat** — messaging, attachments, blocking, reporting and notifications.
- 📱 **PWA support** — installable marketplace shell with offline caching.
- 💰 **Vendor Bitcoin payouts** — vendors can configure a Bitcoin payout address and track settlements.
- ↩️ **Bitcoin refunds** — refunds are represented as settlements and tracked through their lifecycle.
- 🏦 **Settlement queue** — seller payouts and buyer refunds are auditable and can be synchronized with BTCPay.
- 🛡️ **Webhook verification** — BTCPay webhook signatures are validated before payment events are processed.
- 🔒 **No private keys in Laravel** — wallet custody/signing remains outside the web application.
- 👤 **Buyer, vendor and administrator workflows**.
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
- **Deployment:** Docker and Docker Compose

## Validation

Every production-oriented change should pass the repository CI pipeline, which validates Composer dependencies, frontend compilation, code formatting, database migrations and the Laravel/PHPUnit test suite.

```bash
composer install
npm ci
npm run build
vendor/bin/pint --test
php artisan migrate --force
php artisan test
```

For Bitcoin functionality, automated application tests are not a substitute for a BTCPay integration/testnet validation cycle.

## Docker deployment

Kosher Market runs as a Docker Compose application. The Compose stack provides the Laravel web application, queue worker, scheduler, MySQL database and Redis service.

### 1. Configure the environment

```bash
cp .env.docker.example .env
```

Review the `.env` values before starting the stack. Never commit `.env`, wallet credentials, private keys or webhook secrets.

### 2. Build and start the application

```bash
docker compose build
docker compose up -d
```

The Laravel application will be available at:

```text
http://localhost:8000
```

### 3. Run database migrations

```bash
docker compose exec app php artisan migrate --force
```

### 4. View application logs

```bash
docker compose logs -f app
```

View all service logs with:

```bash
docker compose logs -f
```

### 5. Stop the application

```bash
docker compose down
```

To stop the stack while preserving its database, Redis and application-storage volumes:

```bash
docker compose down
```

To remove the persistent Docker volumes as well, use:

```bash
docker compose down -v
```

**Warning:** removing the volumes deletes the local MySQL and Redis data stored by this Compose stack.

## Ubuntu local installation

The following instructions are for Ubuntu development/testing. Do **not** use real Bitcoin while testing the escrow and payout system.

### 1. Install system packages

```bash
sudo apt update
sudo apt install -y git curl unzip mysql-server redis-server \
  php-cli php-common php-mysql php-sqlite3 php-mbstring php-bcmath \
  php-curl php-xml php-zip php-intl
```

Check PHP and required extensions:

```bash
php -v
php -m | grep -E 'dom|xml|mbstring|bcmath|curl|intl|mysqli|pdo_mysql|pdo_sqlite|zip'
```

### 2. Install Composer

```bash
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php composer-setup.php --install-dir=/usr/local/bin --filename=composer
rm composer-setup.php
composer --version
```

### 3. Install Node.js/npm

Use a supported LTS Node.js release and verify:

```bash
node --version
npm --version
```

### 4. Clone the repository

```bash
git clone git@github.com:faraimupfuti/kosher-market.git
cd kosher-market
```

### 5. Install dependencies

```bash
composer install
npm ci
```

### 6. Configure the environment

```bash
cp .env.example .env
php artisan key:generate
```

Configure MySQL, Redis and the appropriate BTCPay integration values in `.env`. Never commit `.env`, wallet credentials, private keys or webhook secrets.

### 7. Create the local MySQL database

```bash
sudo mysql
```

```sql
CREATE DATABASE kosher_market CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'kosher_market'@'localhost' IDENTIFIED BY 'change-this-password';
GRANT ALL PRIVILEGES ON kosher_market.* TO 'kosher_market'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### 8. Run migrations and build

```bash
php artisan migrate
npm run build
```

### 9. Start Laravel and workers

```bash
php artisan serve --host=127.0.0.1 --port=8000
```

In separate terminals:

```bash
php artisan queue:work
php artisan schedule:work
```

## Bitcoin / BTCPay Server

Kosher Market coordinates orders, escrow state, disputes and settlement records. Bitcoin wallet custody and transaction signing remain outside the Laravel web application.

For integration testing, use Bitcoin testnet/sandbox infrastructure appropriate to your BTCPay deployment. Never put a Bitcoin seed phrase or private key in `.env` or source control.

## Production safety

Before accepting real Bitcoin, verify invoice creation, webhook HMAC verification, duplicate webhook idempotency, payment confirmation, escrow transitions, vendor payout-address verification, payout/recovery behavior, disputes/refunds, backups, monitoring and disaster recovery.

**Do not use the application to hold real Bitcoin until the complete deployment, wallet, webhook, payout and recovery procedures have been independently tested.**

## Docker services

The Docker Compose deployment uses the following services:

- **app** — Laravel web application
- **queue** — Laravel queue worker
- **scheduler** — Laravel scheduler
- **mysql** — MySQL 8 database
- **redis** — Redis 7 cache/queue backend

The application container is built from `Dockerfile.local` by the Compose development/testing stack, while `Dockerfile` provides the production-oriented application image. The container build compiles the Vite frontend and installs the PHP dependencies inside the image.

## Project status

Kosher Market is under active development. The core marketplace, Bitcoin escrow, payment, dispute, settlement, vendor trust, search and tracking functionality is being hardened before production use with real funds.
