#!/bin/sh
set -eu

REPO_ROOT="$(CDPATH= cd -- "$(dirname "$0")/.." && pwd)"
DOCKER_BIN="${DOCKER_BIN:-docker}"

if [ -z "${STATSCARRIER_DB_PASSWORD:-}" ]; then
  echo "STATSCARRIER_DB_PASSWORD must be set" >&2
  exit 1
fi

exec "$DOCKER_BIN" run --rm --network host \
  -v "$REPO_ROOT":/workspace:ro \
  -w /workspace \
  -e STATSCARRIER_DB_PASSWORD \
  -e STATSCARRIER_DB_HOST="${STATSCARRIER_DB_HOST:-127.0.0.1}" \
  -e STATSCARRIER_DB_PORT="${STATSCARRIER_DB_PORT:-3306}" \
  -e STATSCARRIER_DB_NAME="${STATSCARRIER_DB_NAME:-statscarrier_test}" \
  -e STATSCARRIER_DB_USER="${STATSCARRIER_DB_USER:-statscarrier}" \
  statscarrier-php56:local \
  php -d error_reporting=-1 -d display_errors=1 /workspace/tests/run-suite.php
