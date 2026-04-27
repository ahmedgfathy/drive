#!/usr/bin/env bash
set -euo pipefail

HOST="${PROD_HOST:-10.51.0.84}"
USER_NAME="${PROD_USER:-xinreal}"
PASSWORD="${PROD_PASSWORD:-}"

if [[ -z "${PASSWORD}" ]]; then
  echo "PROD_PASSWORD is required" >&2
  exit 1
fi

run_remote() {
  SSHPASS="${PASSWORD}" sshpass -e ssh -o StrictHostKeyChecking=no "${USER_NAME}@${HOST}" "$@"
}

run_remote_bash() {
  local script_content
  script_content="$(cat)"
  SSHPASS="${PASSWORD}" sshpass -e ssh -o StrictHostKeyChecking=no "${USER_NAME}@${HOST}" 'bash -s' <<<"${script_content}"
}
