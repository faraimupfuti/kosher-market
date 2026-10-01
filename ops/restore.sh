#!/usr/bin/env bash
set -euo pipefail
if [ "$#" -ne 1 ]; then echo "Usage: $0 <backup-directory>"; exit 2; fi
BACKUP_DIR="$1"
test -f "$BACKUP_DIR/database.sql"
test -f "$BACKUP_DIR/application-storage.tar.gz"
echo "WARNING: restore overwrites application database state and storage."
read -r -p "Type RESTORE to continue: " confirm
[ "$confirm" = "RESTORE" ] || { echo "Cancelled."; exit 1; }
docker compose exec -T mysql sh -c 'exec mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"' < "$BACKUP_DIR/database.sql"
docker exec -i kosher-market-app sh -c 'rm -rf /var/www/html/storage && tar -C /var/www/html -xzf -' < "$BACKUP_DIR/application-storage.tar.gz"
echo "Restore completed. Verify migrations, health endpoint, escrow states and wallet reconciliation before enabling financial operations."
