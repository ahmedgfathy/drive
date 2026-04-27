#!/usr/bin/env bash
set -euo pipefail

HOST="${1:-10.51.0.84}"
USER_NAME="${2:-xinreal}"
PASSWORD="${3:-}"

if [[ -z "${PASSWORD}" ]]; then
  echo "password required" >&2
  exit 1
fi

export SSHPASS="${PASSWORD}"
sshpass -e ssh -tt -o StrictHostKeyChecking=no "${USER_NAME}@${HOST}" \
  "bash -lc 'printf \"%s\n\" \"${PASSWORD}\" | sudo -S -p \"\" whoami'"
