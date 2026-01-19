# Mensaje Final para Hostinger - Problema con LiteSpeed

```
Hola,

He identificado que el servidor es LiteSpeed y el error 403 está siendo generado por LiteSpeed antes de que la solicitud llegue a PHP.

Información del diagnóstico:
- Server: LiteSpeed (según headers de respuesta)
- El error 403 es una página HTML genérica de LiteSpeed, no viene de PHP
- Response Headers muestran: server: LiteSpeed, panel: hpanel, platform: hostinger
- La URL que falla: /web/api/v1/procesos/?estado=analizado&per_page=5
- El archivo .htaccess está en: /public_html/bybjuridicos/web/api/.htaccess

He verificado que:
- El archivo index.php existe y es accesible
- La sesión PHP funciona correctamente (verificado con test_debug.php)
- El parsing de rutas funciona
- El problema es que LiteSpeed está bloqueando la solicitud ANTES de llegar a PHP

El .htaccess actual tiene:
RewriteEngine On
Options -Indexes
RewriteCond %{REQUEST_FILENAME} -f
RewriteRule ^ - [L]
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.php [L,QSA]

¿Pueden verificar:
1. Si LiteSpeed está procesando correctamente el .htaccess en /public_html/bybjuridicos/web/api/
2. Si hay alguna configuración de seguridad en LiteSpeed que esté bloqueando URLs con barras diagonales antes de query parameters (como /procesos/?estado=...)
3. Si necesito alguna configuración adicional o diferente para LiteSpeed (además de las reglas de Apache)
4. Si hay reglas de seguridad a nivel de servidor en LiteSpeed que puedan estar causando esto

El problema específico es que cuando la URL tiene una barra diagonal antes del query string (/procesos/?estado=...), LiteSpeed devuelve 403 antes de que PHP pueda procesar la solicitud.

Gracias.
```

