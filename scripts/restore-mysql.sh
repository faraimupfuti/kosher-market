#!/usr/bin/env sh
set -eu

BACKUP_FILE="${1:-}"
COMPOSE_FILE="${COMPOSE_FILE:-docker-compose.production.yml}"

if [ -z "$BACKUP_FILE" ] || [ ! -f "$BACKUP_FILE" ]; then
  echo "Usage: $0 /path/to/backup.sql.gz" >&2
  exit 1
fi

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

echo "WARNING: this replaces the contents of $DB_DATABASE."
printf 'Type RESTORE to continue: '
read confirmation
[ "$confirmation" = "RESTORE" ] || { echo "Restore cancelled."; exit 1; }

gunzip -c "$BACKUP_FILE" \
  | docker compose -f "$COMPOSE_FILE" exec -T mysql \
      sh -c 'exec mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"'

echo "Restore completed."
