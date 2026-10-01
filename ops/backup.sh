#!/usr/bin/env bash
set -euo pipefail
umask 077
BACKUP_DIR="${1:-./backups/$(date -u +%Y%m%dT%H%M%SZ)}"
mkdir -p "$BACKUP_DIR"
docker compose exec -T mysql sh -c 'exec mysqldump -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" --single-transaction --routines --triggers "$MYSQL_DATABASE"' > "$BACKUP_DIR/database.sql"
docker exec kosher-market-app sh -c 'tar -C /var/www/html -czf - storage' > "$BACKUP_DIR/application-storage.tar.gz"
printf 'Backup written to %s\n' "$BACKUP_DIR"
printf 'Encrypt and copy this directory to independent storage before considering the backup durable.\n'
