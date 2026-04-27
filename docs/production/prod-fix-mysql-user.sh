#!/usr/bin/env bash
set -euo pipefail

HOST="${HOST:-10.51.0.84}"
USER_NAME="${USER_NAME:-xinreal}"
SSH_PASSWORD="${SSH_PASSWORD:-}"
DB_NAME="${DB_NAME:-pms_drive}"
DB_USER="${DB_USER:-pms_drive_app}"
DB_PASS="${DB_PASS:-2qpDEr2TYAR9NrBXCfZ6W}"

if [[ -z "${SSH_PASSWORD}" ]]; then
  echo "SSH_PASSWORD is required" >&2
  exit 1
fi

export SSHPASS="${SSH_PASSWORD}"
sshpass -e ssh -tt -o StrictHostKeyChecking=no "${USER_NAME}@${HOST}" \
  "bash -lc 'printf \"%s\n\" \"${SSH_PASSWORD}\" | sudo -S -p \"\" mysql -uroot -e \"CREATE DATABASE IF NOT EXISTS \\\`${DB_NAME}\\\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE USER IF NOT EXISTS '\\''${DB_USER}'\\''@'\\''localhost'\\'' IDENTIFIED BY '\\''${DB_PASS}'\\''; CREATE USER IF NOT EXISTS '\\''${DB_USER}'\\''@'\\''127.0.0.1'\\'' IDENTIFIED BY '\\''${DB_PASS}'\\''; ALTER USER '\\''${DB_USER}'\\''@'\\''localhost'\\'' IDENTIFIED BY '\\''${DB_PASS}'\\''; ALTER USER '\\''${DB_USER}'\\''@'\\''127.0.0.1'\\'' IDENTIFIED BY '\\''${DB_PASS}'\\''; GRANT ALL PRIVILEGES ON \\\`${DB_NAME}\\\`.* TO '\\''${DB_USER}'\\''@'\\''localhost'\\''; GRANT ALL PRIVILEGES ON \\\`${DB_NAME}\\\`.* TO '\\''${DB_USER}'\\''@'\\''127.0.0.1'\\''; FLUSH PRIVILEGES;\"'"
