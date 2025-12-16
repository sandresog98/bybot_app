#!/bin/bash
# Script de instalación del Bot - ByBot App

cd "$(dirname "$0")"

echo "🔧 Instalando Bot de Análisis ByBot..."
echo ""

# Verificar que Python 3 esté instalado
if ! command -v python3 &> /dev/null; then
    echo "❌ Error: Python 3 no está instalado"
    exit 1
fi

# Verificar que python3-venv esté instalado
if ! python3 -m venv --help &> /dev/null; then
    echo "❌ Error: python3-venv no está instalado"
    echo ""
    echo "   Instala el paquete con:"
    echo "   sudo apt install python3.12-venv"
    echo ""
    exit 1
fi

# Crear entorno virtual si no existe
if [ ! -d "venv" ]; then
    echo "📦 Creando entorno virtual..."
    python3 -m venv venv
    if [ $? -ne 0 ]; then
        echo "❌ Error al crear el entorno virtual"
        exit 1
    fi
    echo "✅ Entorno virtual creado"
else
    echo "✅ Entorno virtual ya existe"
fi

# Verificar que el entorno virtual está completo
if [ ! -f "venv/bin/activate" ] || [ ! -f "venv/bin/pip" ]; then
    echo "⚠️  Entorno virtual incompleto, recreando..."
    rm -rf venv
    python3 -m venv venv
    if [ $? -ne 0 ]; then
        echo "❌ Error al recrear el entorno virtual"
        exit 1
    fi
    echo "✅ Entorno virtual recreado"
fi

# Usar pip del entorno virtual directamente (más confiable que source activate)
VENV_PIP="venv/bin/pip"
VENV_PYTHON="venv/bin/python"

# Actualizar pip
echo "⬆️  Actualizando pip..."
$VENV_PIP install --upgrade pip --quiet

# Instalar dependencias
echo "📥 Instalando dependencias..."
$VENV_PIP install -r requirements.txt

if [ $? -eq 0 ]; then
    echo ""
    echo "✅ Instalación completada exitosamente!"
    echo ""
    echo "Para iniciar el bot, ejecuta:"
    echo "  ./start.sh"
    echo ""
else
    echo ""
    echo "❌ Error al instalar dependencias"
    exit 1
fi

