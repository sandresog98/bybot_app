#!/bin/bash
# Levanta los 3 servicios en background y guarda sus logs.
# Uso: ./dev-bg.sh [start|stop|status]
set -u

ROOT="/opt/lampp/htdocs/projects/bybot_v1/node_version"
PIDS="$ROOT/.dev-pids"
LOG_DIR="/tmp"

case "${1:-start}" in
  start)
    mkdir -p "$LOG_DIR"
    cd "$ROOT"
    # Limpiar pids viejos
    rm -f "$PIDS"

    # botstorage
    setsid npm run dev:botstorage > "$LOG_DIR/bybotstorage.log" 2>&1 < /dev/null &
    echo "botstorage:$!" >> "$PIDS"

    # backend
    setsid npm run dev:backend > "$LOG_DIR/bybackend.log" 2>&1 < /dev/null &
    echo "backend:$!" >> "$PIDS"

    # frontend
    setsid npm run dev:frontend > "$LOG_DIR/byfrontend.log" 2>&1 < /dev/null &
    echo "frontend:$!" >> "$PIDS"

    sleep 6
    echo "Procesos iniciados. PIDs en $PIDS"
    cat "$PIDS"
    ;;
  stop)
    if [ -f "$PIDS" ]; then
      while IFS=: read -r name pid; do
        kill "$pid" 2>/dev/null
      done < "$PIDS"
      rm -f "$PIDS"
      echo "Procesos detenidos."
    else
      echo "No hay procesos registrados."
    fi
    # Igual matar tsx watch colgados
    pkill -f 'tsx watch' 2>/dev/null
    pkill -f 'vite' 2>/dev/null
    pkill -f 'esbuild' 2>/dev/null
    pkill -f 'npm run dev:botstorage' 2>/dev/null
    pkill -f 'npm run dev:backend' 2>/dev/null
    pkill -f 'npm run dev:frontend' 2>/dev/null
    ;;
  status)
    echo "=== Puertos en uso ==="
    ss -ltnp 2>/dev/null | grep -E ':3001|:3002|:5173' | head
    echo "=== Procesos registrados ==="
    if [ -f "$PIDS" ]; then cat "$PIDS"; else echo "(ninguno)"; fi
    ;;
esac