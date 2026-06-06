# Обновляет снимок БД  docker/init/01_database.sql  из работающего контейнера shire_db.
#
# Когда запускать: после того как поменял что-то в админке (загрузил/назначил фото,
# добавил блюда и т.п.) и хочешь, чтобы эти изменения уехали вместе с папкой проекта.
# На новой машине снимок подхватится автоматически при первом `docker compose up`.
#
# Использование:  powershell -File docker/snapshot-db.ps1   (или просто запусти из IDE)

$ErrorActionPreference = 'Stop'
$container = 'shire_db'
$dest = Join-Path $PSScriptRoot 'init\01_database.sql'

# Дамп снимаем ВНУТРИ контейнера (берём пароль/имя БД из его окружения — работает
# при любом .env) и копируем наружу через docker cp, чтобы не испортить UTF-8.
$cmd = @'
{
  echo "-- ============================================================";
  echo "-- Snapshot of the shire_corner database (schema + data).";
  echo "-- Auto-loaded by MySQL on a FRESH volume (docker compose up).";
  echo "-- Regenerate after admin changes:  docker/snapshot-db.ps1 (or .sh)";
  echo "-- ============================================================";
  export MYSQL_PWD="$MYSQL_ROOT_PASSWORD";
  mysqldump -uroot --single-transaction --no-tablespaces --default-character-set=utf8mb4 --add-drop-table "$MYSQL_DATABASE";
} > /tmp/snapshot.sql
'@

docker exec $container sh -c $cmd
docker cp "${container}:/tmp/snapshot.sql" $dest
Write-Host "OK - snapshot updated: $dest"
