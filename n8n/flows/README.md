# 📦 Flujos de n8n para ByBot

Esta carpeta contiene los flujos de n8n exportados como JSON para fácil importación.

---

## ⚠️ Estado

> **IMPORTANTE:** Estos flujos están exportados pero **NO han sido probados** en n8n real.
> Pueden requerir ajustes después de importarlos.

---

## 📁 Archivos

| Archivo | Descripción | Webhook |
|---------|-------------|---------|
| `flujo_analisis.json` | Análisis de documentos con Gemini | `/webhook/analisis` |
| `flujo_llenado.json` | Llenado de pagaré con PyMuPDF | `/webhook/llenado` |

---

## 🔌 Flujo 1: Análisis de Documentos

**Archivo:** `flujo_analisis.json`

**URL del Webhook:** `https://n8n.srv1083920.hstgr.cloud/webhook/analisis`

### Diagrama

```
[Webhook Entrada]
      │
      ├──► [Respuesta Inmediata] ──► (HTTP 200 al PHP)
      │
      └──► [Set Variables]
              │
              ▼
      [Ejecutar Análisis Python]
              │
              ▼
          [¿Éxito?]
           /    \
          /      \
    [Sí]          [No]
      │            │
      ▼            ▼
[Callback      [Callback
   Éxito]        Error]
```

### Payload Esperado (POST)

```json
{
    "proceso_id": 123,
    "codigo": "PR-20260118-0001",
    "prioridad": 5,
    "archivos": [
        {
            "id": 1,
            "url": "https://bybjuridicos.andapps.cloud/web/api/v1/archivos/servir?id=1&token=xxx",
            "tipo": "estado_cuenta",
            "nombre": "estado_cuenta.pdf"
        },
        {
            "id": 2,
            "url": "https://bybjuridicos.andapps.cloud/web/api/v1/archivos/servir?id=2&token=xxx",
            "tipo": "anexo",
            "nombre": "anexo_1.pdf"
        }
    ],
    "callback_url": "https://bybjuridicos.andapps.cloud/web/api/v1/webhook/n8n",
    "api_token": "tu_worker_api_token"
}
```

### Respuesta Inmediata

```json
{
    "success": true,
    "message": "Proceso recibido",
    "proceso_id": 123
}
```

### Callback de Éxito (a PHP)

```json
{
    "proceso_id": 123,
    "success": true,
    "datos": {
        "estado_cuenta": { ... },
        "deudor": { ... },
        "codeudor": { ... }
    }
}
```

### Callback de Error (a PHP)

```json
{
    "proceso_id": 123,
    "success": false,
    "error": "Mensaje de error"
}
```

---

## 🔌 Flujo 2: Llenado de Pagaré

**Archivo:** `flujo_llenado.json`

**URL del Webhook:** `https://n8n.srv1083920.hstgr.cloud/webhook/llenado`

### Diagrama

```
[Webhook Llenado]
      │
      ├──► [Respuesta Inmediata]
      │
      └──► [Set Variables]
              │
              ▼
      [Descargar Pagaré Original]
              │
              ▼
      [Guardar Pagaré Temporal]
              │
              ▼
      [Ejecutar Llenado Python]
              │
              ▼
          [¿Éxito?]
           /    \
    [Parsear]   [Callback Error]
        │
        ▼
[Callback Éxito]
        │
        ▼
[Limpiar Archivos]
```

### Payload Esperado (POST)

```json
{
    "proceso_id": 123,
    "codigo": "PR-20260118-0001",
    "prioridad": 5,
    "datos_validados": {
        "deudor": {
            "nombre_completo": "Juan Pérez García",
            "numero_documento": "12345678",
            "lugar_expedicion": "Bogotá",
            "direccion": "Calle 123 #45-67",
            "ciudad": "Bogotá",
            "celular": "3001234567"
        },
        "estado_cuenta": {
            "numero_credito": "CR-001",
            "total_deuda": 5000000,
            "tasa_interes_corriente": 24.5,
            "tasa_interes_mora": 28.0
        }
    },
    "pagare_original_path": "uploads/procesos/123/pagare_original.pdf",
    "callback_url": "https://bybjuridicos.andapps.cloud/web/api/v1/webhook/n8n",
    "api_token": "tu_worker_api_token"
}
```

### Callback de Éxito (a PHP)

```json
{
    "proceso_id": 123,
    "success": true,
    "archivo_contenido_base64": "JVBERi0xLjQK...",
    "archivo_nombre": "pagare_llenado_PR-20260118-0001.pdf"
}
```

---

## 🚀 Instalación

### Paso 1: Importar Flujos

1. Accede a tu n8n: `https://n8n.srv1083920.hstgr.cloud`
2. Ve a **Workflows** en el menú lateral
3. Click en **Import from File** o el botón de importar
4. Selecciona `flujo_analisis.json`
5. Click en **Import**
6. Repite para `flujo_llenado.json`

### Paso 2: Ajustar Configuración

Después de importar, **edita cada flujo**:

1. **Nodo "Execute Command"** - Ajustar ruta de Python:
   ```bash
   # Cambiar de:
   python analyzer/main.py ...
   
   # A (con entorno virtual):
   /opt/bybot/scripts/venv/bin/python analyzer/main.py ...
   ```

2. **Nodo "HTTP Request" (callbacks)** - Verificar URLs:
   - Deben apuntar a `https://bybjuridicos.andapps.cloud/web/api/v1/webhook/n8n/...`

3. **Variables** - Verificar que `api_token` se pasa correctamente

### Paso 3: Activar Webhooks

1. Abre cada flujo
2. Click en el toggle **Active** (arriba a la derecha)
3. El icono cambiará a verde
4. Los webhooks ahora están escuchando

### Paso 4: Verificar URLs

Una vez activados, verifica que las URLs sean:
- Análisis: `https://n8n.srv1083920.hstgr.cloud/webhook/analisis`
- Llenado: `https://n8n.srv1083920.hstgr.cloud/webhook/llenado`

---

## 🧪 Pruebas

### Test Manual del Webhook de Análisis

```bash
curl -X POST https://n8n.srv1083920.hstgr.cloud/webhook/analisis \
  -H "Content-Type: application/json" \
  -d '{
    "proceso_id": 0,
    "codigo": "TEST-001",
    "archivos": [],
    "callback_url": "https://bybjuridicos.andapps.cloud/web/api/v1/webhook/n8n",
    "api_token": "tu_token"
  }'
```

**Respuesta esperada:**
```json
{
    "success": true,
    "message": "Proceso recibido",
    "proceso_id": 0
}
```

### Verificar Ejecución en n8n

1. Ve a **Executions** en el menú lateral de n8n
2. Filtra por el flujo correspondiente
3. Click en una ejecución para ver detalles
4. Verifica cada nodo y su output

---

## 🐛 Troubleshooting

### Webhook no responde

```
Verificar:
1. ¿El flujo está activado? (toggle verde)
2. ¿La URL es correcta?
3. ¿n8n está corriendo?
```

### Error "Command not found"

```
El nodo Execute Command no encuentra Python.

Solución:
1. Usar ruta absoluta: /opt/bybot/scripts/venv/bin/python
2. Verificar que el entorno virtual existe
3. Verificar permisos de ejecución
```

### Callback no llega a PHP

```
Verificar:
1. URL de callback correcta
2. Token de autenticación incluido
3. CORS configurado en PHP
4. Firewall permite conexión
```

### Timeout en análisis

```
El análisis con Gemini puede tardar.

Solución:
1. Aumentar timeout en n8n
2. Verificar tamaño de archivos
3. Revisar logs de Python
```

---

## 📁 Ubicación de Scripts Python

Los scripts que estos flujos ejecutan están en:

```
/opt/bybot/scripts/
├── analyzer/
│   ├── main.py              # Entry point para análisis
│   └── gemini_client.py     # Cliente de Gemini AI
├── filler/
│   ├── main.py              # Entry point para llenado
│   └── pdf_filler.py        # Llenado de PDF
└── shared/
    ├── config.py            # Configuración
    └── utils.py             # Utilidades
```

---

## 📝 Notas Adicionales

- Los flujos responden inmediatamente para evitar timeout del lado de PHP
- El procesamiento real ocurre de forma asíncrona
- Los callbacks notifican a PHP cuando termina el proceso
- Los archivos temporales se limpian al final de cada ejecución

---

**Última actualización:** 2026-01-18  
**Versión de n8n:** 2.3.2
