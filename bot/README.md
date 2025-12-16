# Bot de Análisis - ByBot App

Bot Python que utiliza Gemini API para analizar documentos de procesos CoreCoop y extraer información estructurada.

## 🎯 Funcionalidad

El bot:
1. Consulta procesos en estado "creado" en la base de datos
2. Cambia el estado a "analizando_con_ia"
3. Analiza el estado de cuenta con Gemini para extraer:
   - Fecha causación (última fecha de pago)
   - Saldo capital
   - Saldo interés
   - Saldo mora
   - Tasa interés efectiva anual (TEA)
4. Analiza los anexos con Gemini para extraer:
   - Datos del deudor/solicitante
   - Datos del codeudor
5. Actualiza el estado a "analizado_con_ia" y guarda los datos extraídos

## 📁 Estructura

```
bot/
├── config/
│   ├── settings.py          # Configuración centralizada
│   └── logging_config.py    # Configuración de logging
├── core/
│   ├── database.py          # Gestor de base de datos
│   └── gemini_client.py     # Cliente de Gemini API
├── processors/
│   └── crear_coop_processor.py  # Procesador principal
├── logs/                    # Logs del bot
├── main.py                  # Punto de entrada
├── requirements.txt         # Dependencias Python
└── README.md               # Esta documentación
```

## 🚀 Instalación

### 1. Instalar python3-venv (si no está instalado)

```bash
sudo apt install python3.12-venv
```

### 2. Ejecutar script de instalación

```bash
cd /opt/lampp/htdocs/projects/by_bot_app/bot
./install.sh
```

Este script:
- Crea un entorno virtual (`venv/`)
- Instala todas las dependencias del archivo `requirements.txt`
- Configura el entorno para ejecutar el bot

### 2. Configurar variables de entorno

Asegúrate de que el archivo `.env` en la raíz del proyecto tenga:

```env
DB_HOST=localhost
DB_USER=root
DB_PASS=tu_contraseña
DB_NAME=by_bot_app
GEMINI_API_KEY=tu_api_key_de_google

# Configuración del servidor PHP (para descargar archivos)
SERVER_BASE_URL=http://localhost/bybot_app/admin
BOT_API_TOKEN=tu_token_secreto_aqui
```

### 3. Obtener API Key de Gemini

1. Ve a [Google AI Studio](https://makersuite.google.com/app/apikey)
2. Crea una nueva API key
3. Cópiala al archivo `.env`

### 4. Configurar Token de API para el Bot

El bot necesita un token secreto para descargar archivos del servidor PHP de forma segura.

**Generar token:**
```bash
# Generar un token aleatorio seguro
python3 -c "import secrets; print(secrets.token_urlsafe(32))"
```

**Agregar al `.env`:**
```env
BOT_API_TOKEN=el_token_generado_aqui
```

**Importante:** El mismo token debe estar configurado en el servidor PHP (en el mismo archivo `.env`).

## 🔧 Uso

### Ejecución manual

```bash
cd /opt/lampp/htdocs/projects/by_bot_app/bot
./start.sh
```

O manualmente con el entorno virtual:

```bash
cd /opt/lampp/htdocs/projects/by_bot_app/bot
source venv/bin/activate
python main.py
```

### Ejecución como servicio (systemd)

Crear archivo `/etc/systemd/system/bybot.service`:

```ini
[Unit]
Description=ByBot Analysis Bot
After=network.target mysql.service

[Service]
Type=simple
User=www-data
WorkingDirectory=/opt/lampp/htdocs/projects/by_bot_app/bot
ExecStart=/opt/lampp/htdocs/projects/by_bot_app/bot/venv/bin/python /opt/lampp/htdocs/projects/by_bot_app/bot/main.py
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
```

Activar servicio:
```bash
sudo systemctl enable bybot
sudo systemctl start bybot
sudo systemctl status bybot
```

## 📊 Flujo de Trabajo

1. **Proceso creado** (estado: `creado`)
   - Usuario sube archivos en la interfaz admin
   - Proceso queda en estado "creado"

2. **Bot detecta proceso** (estado: `analizando_con_ia`)
   - Bot consulta procesos en estado "creado"
   - Cambia estado a "analizando_con_ia"

3. **Análisis con Gemini**
   - Analiza estado de cuenta
   - Analiza anexos
   - Extrae información estructurada

4. **Datos guardados** (estado: `analizado_con_ia`)
   - Bot actualiza estado a "analizado_con_ia"
   - Guarda todos los datos extraídos en la BD

## 🔍 Logs

Los logs se guardan en:
- Archivo: `bot/logs/bot.log`
- Consola: Salida estándar

## ⚙️ Configuración

Editar `config/settings.py` para ajustar:
- Intervalo de consulta (`poll_interval`)
- Modelo de Gemini (`model`)
- Timeout de análisis (`timeout`)

## 🐛 Solución de Problemas

### Error: "GEMINI_API_KEY no está configurada"
- Verificar que el archivo `.env` existe
- Verificar que la variable `GEMINI_API_KEY` está definida

### Error: "Error conectando a la base de datos"
- Verificar credenciales en `.env`
- Verificar que MySQL/MariaDB está corriendo

### Error: "Archivo no encontrado"
- Verificar que los archivos están en `uploads/crear_coop/`
- Verificar permisos de lectura

## 📝 Notas

- El bot procesa un proceso a la vez
- Si falla el análisis, el proceso vuelve a estado "creado" para reintentar
- El bot se ejecuta en loop continuo consultando cada 30 segundos (configurable)

