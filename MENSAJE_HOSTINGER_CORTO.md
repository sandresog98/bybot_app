# Mensaje Corto para Hostinger - LiteSpeed

```
Hola,

El servidor es LiteSpeed y está bloqueando URLs antes de llegar a PHP.

Diagnóstico:
- Server: LiteSpeed
- Error 403 es HTML genérico de LiteSpeed (no viene de PHP)
- URL que falla: /web/api/v1/procesos/?estado=analizado&per_page=5
- .htaccess en: /public_html/bybjuridicos/web/api/.htaccess

Verificado:
- index.php existe y funciona
- Sesión PHP funciona
- LiteSpeed bloquea ANTES de llegar a PHP

.htaccess actual:
RewriteEngine On
Options -Indexes
RewriteCond %{REQUEST_FILENAME} -f
RewriteRule ^ - [L]
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.php [L,QSA]

¿Pueden verificar si LiteSpeed tiene configuración de seguridad bloqueando URLs con barra diagonal antes de query parameters? Gracias.
```

