# Resumen de la Solución del Error 403

## Problema Identificado

El error 403 en `/web/api/v1/procesos/?estado=analizado&per_page=5` es causado por **WAF/mod_security de LiteSpeed** que está bloqueando la palabra "procesos" como patrón sospechoso.

## Evidencia

- ✅ URLs que **FUNCIONAN**:
  - `/web/api/v1/stats/dashboard`
  - `/web/api/v1/colas/estado`

- ❌ URLs que **FALLAN** (403):
  - `/web/api/v1/procesos?estado=analizado&per_page=5`
  - `/web/api/v1/procesos/?estado=analizado&per_page=5`

- El error 403 es HTML genérico de LiteSpeed (no viene de PHP)
- El código PHP funciona correctamente (verificado con tests)
- El problema es específico de la palabra "procesos" en la URL

## Solución

**Hostinger debe revisar y ajustar las reglas del WAF/mod_security** para permitir la ruta `/web/api/v1/procesos`.

## Mensaje para Enviar a Hostinger (Versión Corta)

```
Hola,

El WAF/mod_security de LiteSpeed está bloqueando la ruta /web/api/v1/procesos con 403.

URLs que funcionan: /web/api/v1/stats/dashboard, /web/api/v1/colas/estado
URL que falla: /web/api/v1/procesos?estado=analizado&per_page=5

El 403 es HTML genérico de LiteSpeed (no viene de PHP). Otras rutas con la misma estructura funcionan, solo /procesos falla.

¿Pueden revisar las reglas del WAF/mod_security y excluir /web/api/v1/procesos del bloqueo?

Gracias.
```

## Próximos Pasos

1. ✅ Enviar el mensaje a Hostinger (ya está listo arriba)
2. ⏳ Esperar respuesta de Hostinger
3. ⏳ Una vez que ajusten las reglas del WAF, probar de nuevo las URLs

## Nota Importante

Este es un problema de configuración del servidor (WAF/mod_security), **NO es un problema de tu código**. El código PHP funciona correctamente, pero LiteSpeed está bloqueando la solicitud antes de que llegue a PHP.

