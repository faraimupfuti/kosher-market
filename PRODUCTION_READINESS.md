# Production readiness status

## Implemented in the repository

- Docker-only production deployment path; no Kubernetes dependency.
- Dedicated PHP-FPM production image with OPcache.
- Nginx reverse-proxy/container boundary.
- MySQL and Redis isolated on an internal Docker network.
- Database and Redis are not published to host ports in production Compose.
- Production environment template with `APP_DEBUG=false`.
- Production logging defaults to stderr at warning level.
- Automatic escrow release defaults to disabled.
- MySQL backup and restore helpers.
- Explicit migration step in the production deployment runbook.
- Laravel config/route/view cache instructions.
- Production container CI build.
- HIGH/CRITICAL container vulnerability scan with Trivy.
- Existing BTCPay webhook HMAC validation.
- Existing webhook fingerprint/idempotency handling.
- Existing BTCPay invoice re-verification and escrow binding checks.
- Existing exact BTC amount and confirmation checks.
- Existing locked escrow transitions and settlement records.
- Existing vendor payout-address verification.

## Still required before real-money launch

### Framework upgrade

The application currently targets Laravel 10. Laravel 13 is the current major release and requires PHP 8.3; Laravel 13 receives security fixes through March 17, 2028. Upgrade the Composer dependency graph and lockfile, then run the full test suite before production. Do not simply edit `composer.json` and deploy: the lockfile must be regenerated and the application must pass CI.

### Financial integration testing

Run a dedicated BTCPay testnet/sandbox cycle covering:

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

Before launch, add/verify:

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

Do not enable real-money transactions solely because the Docker containers are healthy. Production readiness requires successful application tests, a successful payment integration test, a successful restore drill and an operational procedure for disputes, refunds and failed payouts.
