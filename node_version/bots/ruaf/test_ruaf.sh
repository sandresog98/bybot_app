#!/usr/bin/env bash
set -e

DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
VENV="/opt/lampp/htdocs/projects/bybot_v1/node_version/botworker/.venv"

echo "============================================"
echo "  RUAF Bot - Prueba manual"
echo "============================================"
echo "CC:  39741702"
echo "Fecha nac: 26/08/1993"
echo "============================================"
echo ""

source "$VENV/bin/activate"

cd "$DIR/.."

python -m ruaf.cli \
  --numero "39741702" \
  --fecha "26/08/1993" \
  --headed \
  -v

echo "Exit code: $?"
