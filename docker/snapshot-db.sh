#!/usr/bin/env sh
# Обновляет снимок БД  docker/init/01_database.sql  из работающего контейнера shire_db.
# Запускать после изменений в админке, чтобы они уехали вместе с папкой проекта.
# На новой машине снимок подхватится автоматически при первом `docker compose up`.
#
# Использование:  sh docker/snapshot-db.sh
set -e

CONTAINER=shire_db
DEST="$(dirname "$0")/init/01_database.sql"

docker exec "$CONTAINER" sh -c '{
  echo "-- ============================================================";
  echo "-- Snapshot of the shire_corner database (schema + data).";
  echo "-- Auto-loaded by MySQL on a FRESH volume (docker compose up).";
  echo "-- Regenerate after admin changes:  docker/snapshot-db.ps1 (or .sh)";
  echo "-- ============================================================";
  export MYSQL_PWD="$MYSQL_ROOT_PASSWORD";
  mysqldump -uroot --single-transaction --no-tablespaces --default-character-set=utf8mb4 --add-drop-table "$MYSQL_DATABASE";
} > /tmp/snapshot.sql'
docker cp "$CONTAINER:/tmp/snapshot.sql" "$DEST"
echo "OK - snapshot updated: $DEST"
