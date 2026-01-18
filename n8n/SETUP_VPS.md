# 🖥️ Guía de Configuración del VPS para ByBot

Esta guía detalla cómo configurar tu servidor VPS Ubuntu con n8n y los scripts Python necesarios.

## 📋 Requisitos

- **OS:** Ubuntu (cualquier versión reciente)
- **Python:** 3.12.3 (ya instalado según tu info)
- **n8n:** 2.3.2 (ya instalado según tu info)
- **Acceso:** SSH al VPS

## 🗂️ Estructura de Directorios

```bash
/opt/bybot/
├── scripts/
│   ├── shared/
│   │   ├── __init__.py
│   │   ├── config.py
│   │   └── utils.py
│   ├── analyzer/
│   │   ├── __init__.py
│   │   ├── gemini_client.py
│   │   ├── main.py
│   │   └── requirements.txt
│   ├── filler/
│   │   ├── __init__.py
│   │   ├── pdf_filler.py
│   │   ├── main.py
│   │   └── requirements.txt
│   ├── temp/
│   ├── logs/
│   └── .env
└── flows/
    ├── flujo_analisis.json
    └── flujo_llenado.json
```

## 🚀 Instalación Paso a Paso

### 1. Crear Estructura de Directorios

```bash
sudo mkdir -p /opt/bybot/{scripts,flows}
sudo mkdir -p /opt/bybot/scripts/{shared,analyzer,filler,temp,logs}
sudo chown -R $USER:$USER /opt/bybot
```

### 2. Copiar Scripts

Desde tu máquina local, copia los scripts al VPS:

```bash
# Desde la carpeta del proyecto bybot
scp -r n8n/scripts/* usuario@tu-vps-ip:/opt/bybot/scripts/
scp -r n8n/flows/* usuario@tu-vps-ip:/opt/bybot/flows/
```

O directamente en el VPS, clona/copia los archivos manualmente.

### 3. Instalar Dependencias Python

```bash
cd /opt/bybot/scripts

# Crear entorno virtual (recomendado)
python3 -m venv venv
source venv/bin/activate

# Instalar dependencias
pip install --upgrade pip
pip install -r analyzer/requirements.txt
pip install -r filler/requirements.txt

# Verificar instalación
python -c "import google.generativeai; import fitz; print('OK')"
```

### 4. Configurar Variables de Entorno

Crea el archivo `/opt/bybot/scripts/.env`:

```bash
nano /opt/bybot/scripts/.env
```

Contenido:

```bash
# =============================================
# CONFIGURACIÓN DE BYBOT SCRIPTS
# =============================================

# API de ByBot (tu Hostinger)
BYBOT_API_URL=https://bybjuridicos.andapps.cloud/web/api/v1
BYBOT_ACCESS_TOKEN=tu_token_worker_seguro_aqui

# Gemini AI
GEMINI_API_KEY=tu_api_key_de_google
GEMINI_MODEL=gemini-1.5-flash
GEMINI_TEMPERATURE=0.1
GEMINI_MAX_TOKENS=4000

# Procesamiento
MAX_FILE_SIZE_MB=10
TIMEOUT_SECONDS=120

# Logging
LOG_LEVEL=INFO
```

### 5. Cargar Variables de Entorno

Modifica los scripts para cargar el `.env`. Agrega al inicio de `shared/config.py`:

```python
from dotenv import load_dotenv
from pathlib import Path

# Cargar .env desde la carpeta de scripts
env_path = Path(__file__).parent.parent / '.env'
load_dotenv(env_path)
```

### 6. Verificar Funcionamiento

```bash
cd /opt/bybot/scripts
source venv/bin/activate

# Test del analizador (sin callback)
python analyzer/main.py --proceso_id 0 --archivos_locales "/path/to/test.pdf" --no_callback

# Test del filler (sin callback)
python filler/main.py --proceso_id 0 --pagare_local "/path/to/pagare.pdf" --datos '{"deudor":{"nombre_completo":"Test"}}' --no_callback --no_upload
```

---

## 🔧 Configuración de n8n

### 1. Importar Flujos

1. Accede a tu n8n: `https://n8n.srv1083920.hstgr.cloud`
2. Ve a **Workflows**
3. Click en **Import from File**
4. Selecciona `/opt/bybot/flows/flujo_analisis.json`
5. Repite para `flujo_llenado.json`

### 2. Ajustar Rutas en n8n

Después de importar, edita los nodos de "Execute Command" para que apunten al entorno virtual:

**Nodo "Ejecutar Análisis Python":**
```bash
cd /opt/bybot/scripts && /opt/bybot/scripts/venv/bin/python analyzer/main.py --proceso_id {{ $json.proceso_id }} --archivos '{{ JSON.stringify($json.archivos) }}'
```

**Nodo "Ejecutar Llenado Python":**
```bash
cd /opt/bybot/scripts && /opt/bybot/scripts/venv/bin/python filler/main.py ...
```

### 3. Activar Webhooks

1. Abre cada flujo
2. Click en el toggle **Activate** (arriba a la derecha)
3. Los webhooks estarán disponibles en:
   - `https://n8n.srv1083920.hstgr.cloud/webhook/analisis`
   - `https://n8n.srv1083920.hstgr.cloud/webhook/llenado`

### 4. Configurar Credenciales (Opcional)

Si usas autenticación en los webhooks:

1. Ve a **Settings** → **Credentials**
2. Agrega una credencial de tipo "Header Auth"
3. Configura para validar `X-N8N-API-KEY`

---

## 🔗 Configuración PHP (Hostinger)

### Variables de Entorno

Agrega a tu `.env` en Hostinger:

```bash
# n8n
N8N_WEBHOOK_URL=https://n8n.srv1083920.hstgr.cloud/webhook
N8N_API_KEY=opcional_si_usas_autenticacion_en_n8n

# Token para Workers (n8n usa esto para callbacks)
WORKER_API_TOKEN=tu_token_worker_seguro_aqui
```

### Generar Token Seguro

```bash
# En tu terminal
openssl rand -hex 32
# Resultado ejemplo: a1b2c3d4e5f6...
```

Usa este token tanto en:
- `.env` del VPS (`BYBOT_ACCESS_TOKEN`)
- `.env` de Hostinger (`WORKER_API_TOKEN`)

---

## 📊 Test de Integración Completa

### 1. Test del Webhook desde PHP

```php
// test_n8n.php (ejecutar en Hostinger)
<?php
require_once 'config/constants.php';
require_once 'web/core/N8nClient.php';

$client = new N8nClient();
$result = $client->triggerWebhook('webhook/analisis', [
    'proceso_id' => 0,
    'codigo' => 'TEST-001',
    'archivos' => [],
    'callback_url' => APP_URL . '/web/api/v1/webhook/n8n',
    'api_token' => WORKER_API_TOKEN
]);

var_dump($result);
```

### 2. Test de Callback desde VPS

```bash
curl -X POST https://bybjuridicos.andapps.cloud/web/api/v1/webhook/n8n/analisis \
  -H "Content-Type: application/json" \
  -H "X-N8N-Access-Token: tu_token_worker_seguro" \
  -d '{
    "proceso_id": 1,
    "success": true,
    "datos": {"test": "data"}
  }'
```

---

## 🐛 Troubleshooting

### Error: "Module not found"
```bash
# Asegurar que el venv está activado
source /opt/bybot/scripts/venv/bin/activate
pip list | grep google
```

### Error: "Permission denied"
```bash
sudo chown -R $USER:$USER /opt/bybot
chmod +x /opt/bybot/scripts/analyzer/main.py
chmod +x /opt/bybot/scripts/filler/main.py
```

### Error: "Webhook not found" en n8n
1. Verifica que el flujo está **activado**
2. Verifica la URL exacta del webhook
3. Revisa los logs de n8n

### Ver Logs
```bash
# Logs de Python
tail -f /opt/bybot/scripts/logs/*.log

# Logs de n8n (si usas Docker)
docker logs n8n -f

# Logs de n8n (si es instalación nativa)
journalctl -u n8n -f
```

---

## 🔄 Actualizaciones

Para actualizar los scripts:

```bash
cd /opt/bybot/scripts

# Desactivar flujos en n8n temporalmente

# Actualizar archivos (scp, git pull, etc.)

# Reinstalar dependencias si hay cambios
source venv/bin/activate
pip install -r analyzer/requirements.txt
pip install -r filler/requirements.txt

# Reactivar flujos en n8n
```

---

## ✅ Checklist de Verificación

- [ ] Directorios creados en `/opt/bybot`
- [ ] Scripts copiados
- [ ] Entorno virtual Python creado
- [ ] Dependencias instaladas
- [ ] Archivo `.env` configurado
- [ ] Flujos importados en n8n
- [ ] Webhooks activados
- [ ] Token configurado en ambos servidores
- [ ] Test de integración exitoso

