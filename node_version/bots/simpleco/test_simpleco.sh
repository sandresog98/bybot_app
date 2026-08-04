#!/usr/bin/env bash
set -e

DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
VENV="/opt/lampp/htdocs/projects/bybot_v1/node_version/botworker/.venv"

source "$VENV/bin/activate"

cd "$DIR/.."

echo "============================================"
echo "  Simple.co Bot - Prueba manual"
echo "============================================"
echo "CC 1: 1022434547"
echo "CC 2: 39741702"
echo "============================================"

for CC in "1022434547" "39741702"; do
  echo ""
  echo "--- Probando CC: $CC ---"
  python -m simpleco.cli --numero "$CC" --headed -v 2>&1
  echo "Exit code: $?"
  echo ""
done
