# Respuesta Corta para Hostinger

## Versión 1: Solo el .htaccess de la API

```
Archivo: /public_html/bybjuridicos/web/api/.htaccess

RewriteEngine On
Options -Indexes
DirectorySlash Off
DirectoryIndex disabled

RewriteCond %{REQUEST_FILENAME} -f
RewriteRule ^ - [L]

RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [PT,QSA,L]

<FilesMatch "\.(json|md|log)$">
    <IfModule mod_authz_core.c>
        Require all denied
    </IfModule>
    <IfModule !mod_authz_core.c>
        Order deny,allow
        Deny from all
    </IfModule>
</FilesMatch>
```

---

## Versión 2: Con información adicional (si cabe)

```
Archivo: /public_html/bybjuridicos/web/api/.htaccess

RewriteEngine On
Options -Indexes
DirectorySlash Off
DirectoryIndex disabled
RewriteCond %{REQUEST_FILENAME} -f
RewriteRule ^ - [L]
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [PT,QSA,L]

Archivo: /public_html/bybjuridicos/web/.htaccess
NO EXISTE en mi proyecto. Si existe en el servidor, ¿puedes compartir su contenido?

El index.php está en: /public_html/bybjuridicos/web/api/index.php
El problema: URLs como /web/api/v1/procesos/?estado=analizado&per_page=5 devuelven 403.
```

---

## Versión 3: Muy corta (solo lo esencial)

```
Archivo: /public_html/bybjuridicos/web/api/.htaccess

RewriteEngine On
Options -Indexes
DirectorySlash Off
DirectoryIndex disabled
RewriteCond %{REQUEST_FILENAME} -f
RewriteRule ^ - [L]
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [PT,QSA,L]

¿Existe /public_html/bybjuridicos/web/.htaccess? Si sí, comparte su contenido.
```

