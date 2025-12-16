#!/bin/bash
# Script de inicio del Bot - ByBot App

cd "$(dirname "$0")"

echo "🤖 Iniciando Bot de Análisis ByBot..."
echo ""

# Verificar que Python 3 esté instalado
if ! command -v python3 &> /dev/null; then
    echo "❌ Error: Python 3 no está instalado"
    exit 1
fi

# Verificar que el entorno virtual existe y está completo
if [ ! -d "venv" ] || [ ! -f "venv/bin/python" ]; then
    echo "❌ Error: Entorno virtual no encontrado o incompleto"
    echo "   Ejecuta primero: ./install.sh"
    exit 1
fi

# Usar Python del entorno virtual directamente
VENV_PYTHON="venv/bin/python"

# Verificar que las dependencias estén instaladas
if ! $VENV_PYTHON -c "import mysql.connector" 2>/dev/null; then
    echo "⚠️  Advertencia: mysql-connector-python no está instalado"
    echo "   Ejecuta: ./install.sh"
    exit 1
fi

if ! $VENV_PYTHON -c "import google.generativeai" 2>/dev/null; then
    echo "⚠️  Advertencia: google-generativeai no está instalado"
    echo "   Ejecuta: ./install.sh"
    exit 1
fi

# Verificar archivo .env
if [ ! -f "../.env" ]; then
    echo "❌ Error: Archivo .env no encontrado"
    echo "   Crea el archivo .env en la raíz del proyecto"
    exit 1
fi

# Ejecutar bot
echo "✅ Iniciando bot..."
$VENV_PYTHON main.py

