#!/usr/bin/env bash
set -euo pipefail

APP_DIR="${APP_DIR:-/home/xinreal/drive}"
SERVICE_NAME="${SERVICE_NAME:-pms-drive-queue.service}"
SERVICE_PATH="/etc/systemd/system/${SERVICE_NAME}"
PHP_BIN="${PHP_BIN:-/usr/bin/php}"
RUN_USER="${RUN_USER:-xinreal}"
RUN_GROUP="${RUN_GROUP:-www-data}"

sudo tee "${SERVICE_PATH}" >/dev/null <<SERVICE
[Unit]
Description=PMS Drive Laravel Queue Worker
After=network.target mariadb.service

[Service]
User=${RUN_USER}
Group=${RUN_GROUP}
Restart=always
RestartSec=5
WorkingDirectory=${APP_DIR}
ExecStart=${PHP_BIN} ${APP_DIR}/artisan queue:work --sleep=3 --tries=3 --timeout=120 --queue=default

[Install]
WantedBy=multi-user.target
SERVICE

sudo systemctl daemon-reload
sudo systemctl enable --now "${SERVICE_NAME}"
sudo systemctl status "${SERVICE_NAME}" --no-pager --lines=10
