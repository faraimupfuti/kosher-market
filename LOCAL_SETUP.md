# Kosher Market — Local Ubuntu Setup

This repository can be tested locally with Docker on Ubuntu. Docker provides PHP, Composer, Node/Vite and MySQL so the host machine does not need to match the production runtime.

## Requirements

- Ubuntu 22.04/24.04 or newer
- Git
- Docker Engine
- Docker Compose plugin

## 1. Clone

```bash
git clone https://github.com/faraimupfuti/velstore.git kosher-market
cd kosher-market
```

If the GitHub repository has been renamed, replace the URL with the new repository URL.

## 2. Configure environment

```bash
cp .env.example .env
```

For local testing, set at least:

```dotenv
APP_NAME="Kosher Market"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000
DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=kosher_market
DB_USERNAME=kosher
DB_PASSWORD=kosher
```

Do not put production BTCPay API keys in a public repository. For local escrow testing, use a dedicated BTCPay test/store environment and place its credentials only in `.env`.

## 3. Start the application

```bash
docker compose -f docker-compose.local.yml up --build
```

Open:

http://localhost:8000

MySQL is exposed to the Ubuntu host on port `3307` if you need a database client:

```bash
mysql -h 127.0.0.1 -P 3307 -u kosher -pkosher kosher_market
```

## 4. Stop the application

```bash
docker compose -f docker-compose.local.yml down
```

To also remove the local database volume:

```bash
docker compose -f docker-compose.local.yml down -v
```

## 5. Useful commands

```bash
docker compose -f docker-compose.local.yml logs -f app

docker compose -f docker-compose.local.yml exec app php artisan migrate:status
docker compose -f docker-compose.local.yml exec app php artisan optimize:clear
docker compose -f docker-compose.local.yml exec app php artisan route:list
docker compose -f docker-compose.local.yml exec app npm run build
```

## Bitcoin / BTCPay local testing

The Laravel application can run on `localhost`, but a real BTCPay webhook must be reachable from the BTCPay server. For end-to-end webhook testing, use a secure tunnel to the local webhook endpoint or a dedicated development BTCPay instance. Never expose an unrestricted Laravel development server directly to the public internet.

## Troubleshooting

If port 8000 is already occupied:

```bash
sudo ss -ltnp | grep ':8000'
```

Change the host-side mapping in `docker-compose.local.yml` from `8000:8000` to another free port, for example `8080:8000`, then visit `http://localhost:8080`.

If containers have stale dependencies:

```bash
docker compose -f docker-compose.local.yml down -v
docker compose -f docker-compose.local.yml build --no-cache
docker compose -f docker-compose.local.yml up
```
