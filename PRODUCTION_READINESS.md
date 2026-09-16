# Production readiness status

## Implemented in the repository

- Docker-only production deployment path; no Kubernetes dependency.
- Dedicated PHP-FPM production image with PHP 8.3 and OPcache.
- Node 22 production asset build.
- Nginx reverse-proxy/container boundary with baseline security headers.
- MySQL and Redis isolated on an internal Docker network.
- Database and Redis are not published to host ports in production Compose.
- Production environment template with `APP_DEBUG=false`.
- Production logging defaults to stderr at warning level.
- Automatic escrow release defaults to disabled.
- Automatic Bitcoin payouts are explicitly configurable and require a verified vendor payout address.
- MySQL backup and restore helpers.
- Explicit migration step in the production deployment runbook.
- Laravel config/route/view cache instructions.
- Production container CI build.
- HIGH/CRITICAL container vulnerability scanning with Trivy.
- Composer dependency validation and security auditing in CI.
- Laravel 13 dependency graph and lockfile.
- BTCPay webhook HMAC validation.
- Webhook fingerprint/idempotency handling.
- BTCPay invoice re-verification and escrow binding checks.
- Exact BTC amount and confirmation checks.
- Locked escrow transitions and settlement records.
- Vendor payout-address verification.
- Automated unit coverage for satoshi arithmetic and BTC validation.
- Feature coverage for webhook signature rejection and duplicate-event idempotency.

## Still required before real-money launch

### Financial integration testing

The repository contains deterministic application tests, but real-money operation still requires a controlled BTCPay testnet/sandbox rehearsal covering:

1. invoice creation
2. payment detection
3. confirmation threshold
4. exact amount validation
5. duplicate webhook delivery
6. webhook retry after application failure
7. malformed/invalid signatures
8. expired invoice
9. underpayment/overpayment
10. escrow release
11. dispute
12. refund
13. payout submission
14. payout approval
15. payout synchronization
16. failed payout recovery
17. database restore while settlements are pending

### Security review

Perform an application-level security review covering authorization/IDOR, file uploads, admin routes, vendor/customer isolation, rate limiting, session security, password reset, API tokens, CSRF exceptions and dependency advisories.

### Infrastructure controls

Before launch, verify:

- TLS termination and HTTP-to-HTTPS redirect
- firewall/security groups
- off-host encrypted backups
- tested restore drills
- host monitoring
- queue/failed-job monitoring
- database disk alerts
- Redis memory alerts
- application error monitoring
- BTCPay availability monitoring
- webhook failure alerts
- payout failure alerts

## Launch rule

Do not enable real-money transactions solely because the Docker containers are healthy. Production readiness requires passing CI, a successful controlled BTCPay integration rehearsal, a successful restore drill and an operational procedure for disputes, refunds, webhook failures and payout failures.
