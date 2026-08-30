# Kosher Market

**Bitcoin-only multi-vendor marketplace with escrow-protected transactions.**

Kosher Market is a Laravel-based marketplace designed around one simple principle: buyers and sellers should be able to transact using Bitcoin with a transparent escrow and settlement workflow.

## Core features

- ₿ **Bitcoin-only payments** — marketplace transactions are denominated and settled in BTC.
- 🔐 **Escrow protection** — buyer funds remain in escrow until the transaction is completed or a dispute is resolved.
- 🧾 **BTCPay Server integration** — Bitcoin invoices and payment verification are handled through BTCPay Server.
- 🤝 **Multi-vendor marketplace** — vendors can list products and manage their marketplace activity.
- 💰 **Vendor Bitcoin payouts** — vendors can configure a Bitcoin payout address and track settlements.
- ⚖️ **Dispute resolution** — buyers can open disputes and administrators can resolve them through release or refund workflows.
- ↩️ **Bitcoin refunds** — refunds are represented as settlements and tracked through their lifecycle rather than being marked complete before funds are sent.
- 🏦 **Settlement queue** — seller payouts and buyer refunds are auditable and can be synchronized with BTCPay.
- 🛡️ **Webhook verification** — BTCPay webhook signatures are validated before payment events are processed.
- 🔒 **No private keys in Laravel** — wallet custody/signing remains outside the web application.
- 👤 **Dedicated buyer, vendor and administrator workflows**.
- 📦 **Product and order management**.
- 🌍 **Multi-language marketplace foundation**.
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

## Bitcoin architecture

Kosher Market uses the application layer to coordinate orders, escrow state, disputes and settlement records. Bitcoin wallet custody and transaction signing are delegated to the configured BTCPay Server infrastructure.

**The Laravel application must never contain Bitcoin private keys, seed phrases or wallet signing secrets.**

### Required Bitcoin infrastructure

- A running BTCPay Server instance
- A Bitcoin wallet configured in BTCPay
- A dedicated BTCPay store for Kosher Market
- A restricted BTCPay API key with only the permissions required by the application
- A BTCPay webhook configured for the marketplace

## Technology

- **Backend:** Laravel / PHP
- **Frontend:** Blade, Vite, JavaScript and Sass
- **Database:** MySQL-compatible relational database
- **Bitcoin payments:** BTCPay Server
- **Authentication:** Laravel authentication stack
- **Deployment:** Docker-compatible hosting / Render

## Configuration

Bitcoin configuration is supplied through environment variables rather than committed credentials. At minimum, configure the BTCPay URL, store ID, API credentials, webhook secret and required confirmation policy in the deployment environment.

Never commit `.env`, Bitcoin API keys, wallet credentials or private keys to Git.

## Local development

```bash
cp .env.example .env
composer install
npm install
npm run build
php artisan key:generate
php artisan migrate
php artisan serve
```

For local development with Vite hot reload:

```bash
npm run dev
```

## Production deployment

The repository includes Docker/Render deployment configuration. The production service should run the PHP/Laravel application rather than being configured as a Node-only static service.

Before accepting real Bitcoin, verify:

1. Database migrations complete successfully.
2. The application boots without exceptions.
3. BTCPay invoice creation works.
4. BTCPay webhooks are signed and accepted correctly.
5. A testnet/sandbox transaction reaches the expected escrow state.
6. Vendor payout addresses are verified.
7. Settlement creation and synchronization work.
8. Refunds are tested end-to-end.
9. Admin dispute controls are protected by administrator authentication/authorization.
10. Backups, logging and monitoring are configured.

## Project status

Kosher Market is under active development. The Bitcoin escrow, dispute and settlement architecture is being hardened before production use with real funds.

**Do not use the application to hold real Bitcoin until the complete deployment, wallet, webhook, payout and recovery procedures have been independently tested.**
