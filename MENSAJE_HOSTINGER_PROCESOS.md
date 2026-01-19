# Mensaje para Hostinger - Problema Específico con /procesos

```
Hola,

He identificado que el problema es específico de la ruta /procesos, no general.

URLs que FUNCIONAN:
- /web/api/v1/stats/dashboard ✅
- /web/api/v1/colas/estado ✅

URLs que FALLAN (403):
- /web/api/v1/procesos?estado=analizado&per_page=5 ❌
- /web/api/v1/procesos/?estado=analizado&per_page=5 ❌

El error 403 es HTML genérico de LiteSpeed (no viene de PHP).

¿Pueden verificar si hay alguna regla de seguridad en LiteSpeed que esté bloqueando específicamente la palabra "procesos" en las URLs? 

Gracias.
```

