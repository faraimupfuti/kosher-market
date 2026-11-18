# Kosher Market — Django implementation

This Django application is maintained in this repository, separately from the software catalogue/source repository `faraimupfuti/kosher-software`.

The existing Laravel/XMR implementation at the repository root is intentionally preserved. The Django implementation starts in this isolated `django/` directory so both can be reviewed without destructive migration.

## Architecture
- Django 5.2 LTS, PostgreSQL, Django REST Framework
- Server-rendered storefront with Django templates
- Redis/Celery-ready background processing
- Hosted crypto invoices through a payment-provider adapter (initial adapter: NOWPayments)
- No customer wallet balances or platform-operated crypto wallet
- Private object storage for paid software artefacts
- Immutable order snapshots, idempotent payment events, licence entitlements and auditable vendor payables

## Local setup
```bash
cd django
python -m venv .venv
source .venv/bin/activate
pip install -r requirements.txt
cp .env.example .env
python manage.py migrate
python manage.py createsuperuser
python manage.py runserver
```

PostgreSQL is expected. For a quick local-only smoke test, set `DB_ENGINE=sqlite` in `.env`; do not use SQLite in production.

## Current implementation scope
Foundation milestone: project configuration, custom user model, catalogue, vendor approval, product version metadata, cart/order primitives and admin registration. Payment processing, private downloads and licensing are deliberately separate milestones and must not be represented as production-ready until their integration tests pass.

## Security rules
- Never commit secrets or payment API credentials.
- Never publish paid binaries under a public static/media URL.
- Never fulfil an order based only on a browser redirect.
- Verify payment callbacks cryptographically and independently query the provider.
- Do not create internal customer crypto balances.
- Treat vendor payouts as payables requiring reconciliation and explicit approval.
