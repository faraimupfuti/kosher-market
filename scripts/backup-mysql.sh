#!/usr/bin/env sh
set -eu

BACKUP_DIR="${BACKUP_DIR:-./backups}"
COMPOSE_FILE="${COMPOSE_FILE:-docker-compose.production.yml}"
TIMESTAMP="$(date -u +%Y%m%dT%H%M%SZ)"
FILE="$BACKUP_DIR/kosher_market_$TIMESTAMP.sql.gz"

mkdir -p "$BACKUP_DIR"

if [ ! -f .env.production ]; then
  echo "Missing .env.production" >&2
  exit 1
fi

set -a
. ./.env.production
set +a

: "${DB_DATABASE:?DB_DATABASE is required}"
: "${DB_USERNAME:?DB_USERNAME is required}"
: "${DB_PASSWORD:?DB_PASSWORD is required}"

# The backup is streamed directly from the database container so database
# credentials and data do not need to be copied into another container.
docker compose -f "$COMPOSE_FILE" exec -T mysql \
  sh -c 'exec mysqldump --single-transaction --quick --routines --triggers --events -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"' \
  | gzip > "$FILE"

chmod 600 "$FILE"

echo "Created $FILE"
echo "Store this backup outside the host and encrypt it before off-site retention."
