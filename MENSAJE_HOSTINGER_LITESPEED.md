# Mensaje para Hostinger - Problema con LiteSpeed

```
Hola,

He identificado que el servidor es LiteSpeed y el error 403 está siendo generado por LiteSpeed antes de que la solicitud llegue a PHP.

Información del diagnóstico:
- Server: LiteSpeed (según headers: server: LiteSpeed, panel: hpanel)
- El error 403 es una página HTML genérica de LiteSpeed, no viene de PHP
- La URL que falla: /web/api/v1/procesos/?estado=analizado&per_page=5
- El archivo .htaccess está en: /public_html/bybjuridicos/web/api/.htaccess

He verificado que:
- El archivo index.php existe y es accesible
- La sesión PHP funciona correctamente
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
1. Si LiteSpeed está procesando correctamente el .htaccess
2. Si hay configuración de seguridad en LiteSpeed bloqueando URLs con barra diagonal antes de query parameters
3. Si necesito configuración adicional para LiteSpeed

Gracias.
```
