#!/usr/bin/env bash
set -euo pipefail

APP_DIR="${APP_DIR:-/home/xinreal/drive}"
BRANCH="${BRANCH:-main}"
QUEUE_SERVICE_NAME="${QUEUE_SERVICE_NAME:-pms-drive-queue.service}"
QUEUE_SERVICE_PATH="/etc/systemd/system/${QUEUE_SERVICE_NAME}"

cd "${APP_DIR}"

echo "== Pull latest code from GitHub =="
git fetch origin
git pull --ff-only origin "${BRANCH}"

echo "== Install PHP dependencies =="
composer install --no-dev --optimize-autoloader --no-interaction

echo "== Install JS dependencies =="
if [ -f package-lock.json ]; then
  npm ci
else
  npm install
fi

echo "== Build frontend =="
npm run build

echo "== Run database migrations =="
php artisan migrate --force

echo "== Refresh Laravel caches =="
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

echo "== Restart queue worker =="
if systemctl list-unit-files | grep -q "^${QUEUE_SERVICE_NAME}"; then
  sudo systemctl restart "${QUEUE_SERVICE_NAME}"
  sudo systemctl status "${QUEUE_SERVICE_NAME}" --no-pager --lines=3 || true
else
  echo "Queue worker service not installed yet: ${QUEUE_SERVICE_PATH}"
  echo "Skipping queue restart."
fi

echo "Deployment finished."
