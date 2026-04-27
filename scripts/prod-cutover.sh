#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."
source scripts/prod-remote.sh

DB_NAME="${DB_NAME:-pms_drive}"
DB_USER="${DB_USER:-pms_drive_app}"
DB_PASS="${DB_PASS:-2qpDEr2TYAR9NrBXCfZ6W}"
APP_URL="${APP_URL:-http://drive.pms.eg}"
FRONTEND_URL="${FRONTEND_URL:-http://drive.pms.eg}"
SANCTUM_DOMAINS="${SANCTUM_DOMAINS:-drive.pms.eg}"

run_remote_bash <<REMOTE
set -euo pipefail
cd /home/xinreal/drive

echo "== Current app state =="
php artisan about | sed -n '1,80p'

echo "== Ensure MySQL database and user =="
sudo mysql -uroot <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'127.0.0.1' IDENTIFIED BY '${DB_PASS}';
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
ALTER USER '${DB_USER}'@'127.0.0.1' IDENTIFIED BY '${DB_PASS}';
ALTER USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'127.0.0.1';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL

echo "== Clear Laravel caches before DB switch =="
php artisan optimize:clear || true

echo "== Run MySQL migrations =="
DB_CONNECTION=mysql \
DB_HOST=127.0.0.1 \
DB_PORT=3306 \
DB_DATABASE="${DB_NAME}" \
DB_USERNAME="${DB_USER}" \
DB_PASSWORD="${DB_PASS}" \
php artisan migrate --force

echo "== Copy SQLite data into MySQL =="
php <<'PHP'
<?php
$sqlite = new PDO('sqlite:/home/xinreal/drive/database/database.sqlite');
$sqlite->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$mysql = new PDO('mysql:host=127.0.0.1;port=3306;dbname=' . getenv('DB_NAME') . ';charset=utf8mb4', getenv('DB_USER'), getenv('DB_PASS'), [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$skip = ['migrations'];
$tables = $sqlite->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);

$mysql->exec('SET FOREIGN_KEY_CHECKS=0');

foreach ($tables as $table) {
    if (in_array($table, $skip, true)) {
        continue;
    }

    $exists = $mysql->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
    $exists->execute([$table]);
    if ((int) $exists->fetchColumn() === 0) {
        echo "skip:$table:missing_in_mysql\n";
        continue;
    }

    $columns = $mysql->query("DESCRIBE `$table`")->fetchAll();
    $columnNames = array_map(static fn(array $col): string => $col['Field'], $columns);
    if (empty($columnNames)) {
        echo "skip:$table:no_columns\n";
        continue;
    }

    $rows = $sqlite->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
    $mysql->exec("TRUNCATE TABLE `$table`");

    if (empty($rows)) {
        echo "ok:$table:0\n";
        continue;
    }

    $placeholders = implode(',', array_fill(0, count($columnNames), '?'));
    $columnSql = implode(',', array_map(static fn(string $name): string => "`$name`", $columnNames));
    $insert = $mysql->prepare("INSERT INTO `$table` ($columnSql) VALUES ($placeholders)");

    $count = 0;
    foreach ($rows as $row) {
        $payload = [];
        foreach ($columnNames as $name) {
            $payload[] = $row[$name] ?? null;
        }
        $insert->execute($payload);
        $count++;
    }

    echo "ok:$table:$count\n";
}

$mysql->exec('SET FOREIGN_KEY_CHECKS=1');
PHP

echo "== Update production .env =="
php <<PHP
<?php
\$path = '/home/xinreal/drive/.env';
\$contents = file_get_contents(\$path);
\$updates = [
    'APP_ENV' => 'production',
    'APP_DEBUG' => 'false',
    'APP_URL' => '${APP_URL}',
    'FRONTEND_URL' => '${FRONTEND_URL}',
    'SANCTUM_STATEFUL_DOMAINS' => '${SANCTUM_DOMAINS}',
    'DB_CONNECTION' => 'mysql',
    'DB_HOST' => '127.0.0.1',
    'DB_PORT' => '3306',
    'DB_DATABASE' => '${DB_NAME}',
    'DB_USERNAME' => '${DB_USER}',
    'DB_PASSWORD' => '${DB_PASS}',
];
foreach (\$updates as \$key => \$value) {
    if (preg_match('/^' . preg_quote(\$key, '/') . '=.*/m', \$contents)) {
        \$contents = preg_replace('/^' . preg_quote(\$key, '/') . '=.*/m', \$key . '=' . \$value, \$contents);
    } else {
        \$contents .= PHP_EOL . \$key . '=' . \$value;
    }
}
file_put_contents(\$path, \$contents);
PHP

echo "== Ensure storage symlink =="
php artisan storage:link || true

echo "== Build frontend assets =="
if [ -f package-lock.json ]; then
  npm ci
else
  npm install
fi
npm run build

echo "== Cache Laravel bootstrap =="
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

echo "== Fix writable permissions =="
sudo chgrp -R www-data storage bootstrap/cache
sudo chmod -R ug+rwx storage bootstrap/cache

echo "== Install queue worker service =="
sudo tee /etc/systemd/system/pms-drive-queue.service >/dev/null <<'SERVICE'
[Unit]
Description=PMS Drive Laravel Queue Worker
After=network.target mariadb.service

[Service]
User=xinreal
Group=www-data
Restart=always
RestartSec=5
WorkingDirectory=/home/xinreal/drive
ExecStart=/usr/bin/php /home/xinreal/drive/artisan queue:work --sleep=3 --tries=3 --timeout=120 --queue=default

[Install]
WantedBy=multi-user.target
SERVICE

sudo systemctl daemon-reload
sudo systemctl enable --now pms-drive-queue.service

echo "== Final verification =="
php artisan about | sed -n '1,100p'
systemctl is-active nginx php8.3-fpm mariadb pms-drive-queue || true
curl -I -H 'Host: drive.pms.eg' http://127.0.0.1 | sed -n '1,5p'
REMOTE
