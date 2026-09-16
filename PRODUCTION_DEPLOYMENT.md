# Kosher Market production deployment

This runbook is for the Docker production stack. Do not put real Bitcoin into production until the BTCPay integration, payout approval, refund, dispute, backup and restore procedures have been tested end-to-end.

## 1. Prepare the host

Use a supported Linux server with Docker Engine and the Compose plugin. Put TLS termination in front of the application (for example, Caddy, Traefik or a managed load balancer) and forward HTTPS traffic to port 80 of the `web` service.

Do not expose MySQL or Redis to the public Internet. The production Compose file deliberately keeps them on an internal Docker network.

## 2. Configure secrets

```bash
cp .env.production.example .env.production
chmod 600 .env.production
```

Set at minimum:

- `APP_KEY`
- `APP_URL`
- `DB_PASSWORD`
- `MYSQL_ROOT_PASSWORD`
- `BTCPAY_URL`
- `BTCPAY_STORE_ID`
- `BTCPAY_API_KEY`
- `BTCPAY_WEBHOOK_SECRET`

Generate the application key before first deployment:

```bash
docker compose -f docker-compose.production.yml run --rm app php artisan key:generate --show
```

Copy the generated value into `APP_KEY`. Never commit `.env.production`.

## 3. Build the immutable application image

```bash
docker compose -f docker-compose.production.yml build --pull
```

The production image uses PHP-FPM and OPcache. Nginx is a separate container. Queue and scheduler processes use the same immutable application image.

## 4. Start infrastructure

```bash
docker compose -f docker-compose.production.yml up -d mysql redis
```

Verify health:

```bash
docker compose -f docker-compose.production.yml ps
```

## 5. Run database migrations

Run migrations explicitly during deployment rather than from every web container:

```bash
docker compose -f docker-compose.production.yml run --rm app php artisan migrate --force
```

## 6. Optimize Laravel

```bash
docker compose -f docker-compose.production.yml run --rm app php artisan config:cache
docker compose -f docker-compose.production.yml run --rm app php artisan route:cache
docker compose -f docker-compose.production.yml run --rm app php artisan view:cache
```

If the application contains routes that cannot be cached, fix them rather than disabling route caching in production.

## 7. Start the application

```bash
docker compose -f docker-compose.production.yml up -d
```

Check the services:

```bash
docker compose -f docker-compose.production.yml ps
```

Check logs:

```bash
docker compose -f docker-compose.production.yml logs --tail=200 app web queue scheduler
```

## 8. TLS and DNS

Point `kosher.market` and the required subdomains to the server/load balancer. Terminate TLS at the reverse proxy or load balancer. Only expose ports 80/443 from the host. Redirect HTTP to HTTPS at the TLS proxy.

## 9. Backups

Run a database backup at least daily and retain multiple generations off-host:

```bash
./scripts/backup-mysql.sh
```

The repository helper creates a compressed logical backup. Encrypt the resulting file and copy it to storage outside the application host. A backup is not considered a disaster-recovery control until restoration has been tested.

## 10. Restore test

On a non-production environment:

```bash
./scripts/restore-mysql.sh backups/kosher_market_YYYYMMDDTHHMMSSZ.sql.gz
```

Verify:

- users and vendors exist
- orders exist
- escrow ledger entries exist
- settlement records exist
- webhook events exist
- migrations are current
- application login works
- checkout pages load

Perform a documented restore drill periodically.

## 11. Payment safety checklist

Before enabling real transactions:

- Verify BTCPay webhook HMAC signatures.
- Verify the invoice by querying BTCPay instead of trusting webhook payload amounts.
- Verify the invoice ID is bound to the intended escrow record.
- Require the configured confirmation threshold.
- Reject mismatched BTC amounts.
- Record webhook fingerprints and make processing idempotent.
- Test duplicate webhook delivery.
- Test webhook retries after a temporary application failure.
- Test payout submission, approval and synchronization.
- Test refund destination handling.
- Test a failed payout and recovery.
- Test a dispute before releasing funds.
- Test database recovery before accepting real funds.

The current application already records BTCPay webhook fingerprints and processing status and validates the BTCPay invoice against the escrow binding before funding escrow. Keep those controls in place when changing payment providers.

## 12. Escrow safety

Production defaults should keep automatic escrow release disabled until the full operational runbook has been tested. A human/admin-controlled release path should remain available for disputes and exceptional cases.

Do not change `ESCROW_AUTO_RELEASE=true` merely to make the happy path faster.

## 13. Deployment rollback

Keep the previous application image/tag available. For a code-only rollback, redeploy the previous image and only roll back database migrations when the migration has an explicitly tested down/rollback procedure.

Never destroy the production database to resolve a failed deployment.

## 14. Operational monitoring

Monitor at minimum:

- HTTP 5xx rate
- `/up` health endpoint
- queue backlog and failed jobs
- scheduler execution
- MySQL availability/disk usage
- Redis availability/memory
- application error rate
- BTCPay API failures
- webhook failures/retries
- pending escrow duration
- pending settlements
- payout failures
- backup freshness

Alert on failed backups and failed payment/settlement processing, not only on server CPU usage.
