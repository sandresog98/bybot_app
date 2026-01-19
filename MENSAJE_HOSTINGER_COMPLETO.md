# Mensaje Completo para Hostinger (si no hay límite de caracteres)

Hola,

El WAF/mod_security de LiteSpeed está bloqueando la ruta /web/api/v1/procesos con código 403 Forbidden.

**URLs que funcionan:**
- https://bybjuridicos.andapps.cloud/web/api/v1/stats/dashboard
- https://bybjuridicos.andapps.cloud/web/api/v1/colas/estado

**URL que falla:**
- https://bybjuridicos.andapps.cloud/web/api/v1/procesos?estado=analizado&per_page=5

El 403 es HTML genérico de LiteSpeed (no viene de PHP). Todas las rutas usan el mismo .htaccess y estructura, solo /procesos falla.

**Ubicación .htaccess:** /public_html/bybjuridicos/web/api/.htaccess

**Contenido:**
```
RewriteEngine On
Options -Indexes
RewriteCond %{REQUEST_FILENAME} -f
RewriteRule ^ - [L]
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [L,QSA]
```

Por favor, ¿pueden revisar los logs del WAF/mod_security y excluir /web/api/v1/procesos del bloqueo?

Gracias.

