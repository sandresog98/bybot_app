# Alias Temporal: /casos → /procesos

## Resumen

Se ha implementado un alias temporal `/casos` que apunta a `/procesos` para evitar el bloqueo del WAF/mod_security de LiteSpeed que está bloqueando la palabra "procesos" en las URLs.

## Cambios Realizados

### 1. Router Principal (`web/api/index.php`)
- Agregado alias `'casos'` que apunta al mismo router de procesos
- Ambos endpoints (`/procesos` y `/casos`) funcionan ahora

### 2. Frontend - Cambios en llamadas API

**Archivos modificados:**
- `web/admin/pages/dashboard.php` - Cambiado endpoint de `'procesos'` a `'casos'`
- `web/admin/pages/procesos/lista.php` - Cambiado `/procesos` a `/casos` (2 llamadas)
- `web/admin/pages/procesos/ver.php` - Cambiado `/procesos` a `/casos` (4 llamadas)
- `web/admin/pages/procesos/validar.php` - Cambiado `/procesos` a `/casos` (1 llamada)
- `web/admin/pages/procesos/crear.php` - Cambiado `/procesos` a `/casos` (2 llamadas)

## Estado Actual

✅ **Funcionando:**
- `/web/api/v1/casos` - Lista procesos
- `/web/api/v1/casos/{id}` - Obtiene proceso por ID
- `/web/api/v1/casos/{id}/encolar-analisis` - Encola análisis
- `/web/api/v1/casos/{id}/encolar-llenado` - Encola llenado
- `/web/api/v1/casos/{id}/cancelar` - Cancela proceso
- `/web/api/v1/casos?estado=analizado&per_page=5` - Lista con filtros

❌ **Sigue bloqueado (temporalmente):**
- `/web/api/v1/procesos` - Bloqueado por WAF (pero el alias funciona)

## Revertir Cambios

Cuando Hostinger resuelva el problema del WAF, se deben revertir estos cambios:

1. **Eliminar el alias en `web/api/index.php`:**
   ```php
   case 'procesos':
       // Eliminar la línea: case 'casos':
       require_once __DIR__ . '/v1/procesos/router.php';
       routeProcesos($method, $id, $action, $body);
       break;
   ```

2. **Revertir todos los cambios en el frontend:**
   - Cambiar `'casos'` de vuelta a `'procesos'` en `dashboard.php`
   - Cambiar `/casos` de vuelta a `/procesos` en todos los archivos de `procesos/`

## Nota Importante

Este es un **workaround temporal**. Una vez que Hostinger ajuste las reglas del WAF, se debe revertir a `/procesos` para mantener la consistencia de la API.

