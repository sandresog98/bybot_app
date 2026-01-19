# Respuesta para Hostinger - Contenido de .htaccess

## Archivo 1: /public_html/bybjuridicos/web/api/.htaccess

```apache
# =============================================
# .htaccess - ByBot API
# Redirige todas las solicitudes al router principal
# =============================================

RewriteEngine On

# Desactivar el listado de directorios (evita 403 en directorios)
Options -Indexes

# Prevenir que Apache agregue barras diagonales automáticamente
DirectorySlash Off

# Deshabilitar DirectoryIndex para evitar que busque index en subdirectorios
DirectoryIndex disabled

# Permitir acceso directo a archivos físicos (PHP, CSS, JS, imágenes, etc.)
RewriteCond %{REQUEST_FILENAME} -f
RewriteRule ^ - [L]

# IMPORTANTE: No permitir que Apache trate las rutas como directorios
# Esto previene que URLs como /procesos/ sean interpretadas como directorios
# y generen 403 antes de llegar a las reglas de reescritura

# Redirigir todas las solicitudes a index.php
# Maneja tanto URLs con como sin barra diagonal al final
# Usar [PT] (Pass Through) para que Apache procese la URL como si fuera un archivo
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [PT,QSA,L]

# Seguridad: bloquear acceso directo a archivos sensibles
<FilesMatch "\.(json|md|log)$">
    <IfModule mod_authz_core.c>
        Require all denied
    </IfModule>
    <IfModule !mod_authz_core.c>
        Order deny,allow
        Deny from all
    </IfModule>
</FilesMatch>

# Headers de seguridad
<IfModule mod_headers.c>
    Header set X-Content-Type-Options "nosniff"
    Header set X-Frame-Options "DENY"
    Header set X-XSS-Protection "1; mode=block"
</IfModule>
```

---

## Archivo 2: /public_html/bybjuridicos/web/.htaccess

**NOTA**: Este archivo NO existe actualmente en el proyecto. Solo existe un `.htaccess` en la raíz del proyecto (`/public_html/bybjuridicos/.htaccess`).

Si existe un archivo en `/public_html/bybjuridicos/web/.htaccess` en el servidor, por favor comparte su contenido. Si no existe, aquí está el contenido del `.htaccess` de la raíz del proyecto:

```apache
# =============================================
# .htaccess - ByBot Root
# =============================================

# Redirigir favicon.ico al handler PHP
RewriteEngine On
RewriteRule ^favicon\.ico$ favicon.php [L]

# Seguridad: bloquear acceso a archivos sensibles
<FilesMatch "\.(env|log|md|json|sql)$">
    <IfModule mod_authz_core.c>
        Require all denied
    </IfModule>
    <IfModule !mod_authz_core.c>
        Order deny,allow
        Deny from all
    </IfModule>
</FilesMatch>

# Headers de seguridad
<IfModule mod_headers.c>
    Header set X-Content-Type-Options "nosniff"
    Header set X-Frame-Options "SAMEORIGIN"
    Header set X-XSS-Protection "1; mode=block"
</IfModule>
```

---

## Información Adicional

- **Ubicación del index.php de la API**: `/public_html/bybjuridicos/web/api/index.php`
- **Estructura de la aplicación**: 
  - `/web/admin/` - Panel administrativo
  - `/web/api/` - API REST
  - Ambos comparten la misma sesión PHP

El problema específico es que las URLs como `/web/api/v1/procesos/?estado=analizado&per_page=5` están devolviendo 403, y necesitamos que estas solicitudes sean redirigidas correctamente a `index.php` para que el router PHP las procese.

