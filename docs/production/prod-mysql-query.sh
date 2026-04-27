#!/usr/bin/env bash
set -euo pipefail

HOST="${HOST:-10.51.0.84}"
USER_NAME="${USER_NAME:-xinreal}"
SSH_PASSWORD="${SSH_PASSWORD:-}"
DB_NAME="${DB_NAME:-pms_drive}"
DB_USER="${DB_USER:-pms_drive_app}"
DB_PASS="${DB_PASS:-2qpDEr2TYAR9NrBXCfZ6W}"
SQL="${SQL:-SELECT 1;}"

if [[ -z "${SSH_PASSWORD}" ]]; then
  echo "SSH_PASSWORD is required" >&2
  exit 1
fi

export SSHPASS="${SSH_PASSWORD}"
sshpass -e ssh -o StrictHostKeyChecking=no "${USER_NAME}@${HOST}" \
  "mysql -h127.0.0.1 -u${DB_USER} -p${DB_PASS} -D ${DB_NAME} -e \"$SQL\""
