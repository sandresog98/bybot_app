# Solución .htaccess para ByBot API

## Problema identificado

Las URLs con barra diagonal al final (`/web/api/v1/procesos/?estado=analizado`) estaban generando error 403 (Forbidden) porque Apache las interpretaba como intentos de acceso a directorios que no existen.

## Solución implementada

Se ha actualizado el archivo `/web/api/.htaccess` con las siguientes mejoras:

### Reglas implementadas:

1. **Desactivar listado de directorios**: Evita que Apache liste directorios y devuelva 403
2. **Permitir archivos físicos**: Los archivos reales (PHP, CSS, JS, imágenes) se sirven directamente
3. **Normalizar URLs**: Remueve barras diagonales redundantes al final (excepto root)
4. **Redirigir todo a index.php**: Todas las solicitudes que no sean archivos físicos van al router

### Contenido del .htaccess mejorado:

```apache
# =============================================
# .htaccess - ByBot API
# Redirige todas las solicitudes al router principal
# =============================================

RewriteEngine On

# Desactivar el listado de directorios (evita 403 en directorios)
Options -Indexes

# Permitir acceso directo a archivos físicos (PHP, CSS, JS, imágenes, etc.)
RewriteCond %{REQUEST_FILENAME} -f
RewriteRule ^ - [L]

# Permitir acceso directo a directorios físicos (pero no listarlos)
RewriteCond %{REQUEST_FILENAME} -d
RewriteRule ^ - [L]

# Redirigir todas las solicitudes a index.php
# Maneja tanto URLs con como sin barra diagonal al final
# La barra diagonal al final se maneja internamente, no requiere redirect
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)/?$ index.php [QSA,L]

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

## Explicación de las reglas

### 1. `Options -Indexes`
- **Propósito**: Desactiva el listado automático de directorios
- **Por qué**: Evita que Apache devuelva 403 cuando intenta listar un "directorio" que en realidad es una ruta de la API

### 2. Permitir archivos físicos
```apache
RewriteCond %{REQUEST_FILENAME} -f
RewriteRule ^ - [L]
```
- **Propósito**: Si la solicitud es para un archivo que existe físicamente, servirlo directamente
- **Por qué**: Los archivos estáticos (CSS, JS, imágenes) deben servirse sin pasar por el router

### 3. Permitir directorios físicos
```apache
RewriteCond %{REQUEST_FILENAME} -d
RewriteRule ^ - [L]
```
- **Propósito**: Si la solicitud es para un directorio que existe físicamente, permitir el acceso
- **Por qué**: Evita redirigir directorios reales al router

### 4. Redirigir todo a index.php
```apache
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)/?$ index.php [QSA,L]
```
- **Propósito**: Todas las solicitudes que no sean archivos o directorios físicos van al router
- **Por qué**: Permite que el router PHP maneje todas las rutas de la API
- **`/?`**: Hace que la barra diagonal al final sea opcional, permitiendo ambas variantes
- **QSA**: Preserva los parámetros de query string (`?estado=analizado&per_page=5`)
- **L**: Última regla, no procesa más reglas después
- **Ejemplo**: Tanto `/api/v1/procesos` como `/api/v1/procesos/` funcionan correctamente

## Verificación

Después de aplicar estos cambios, las siguientes URLs deberían funcionar:

✅ `/web/api/v1/procesos?estado=analizado&per_page=5`
✅ `/web/api/v1/procesos/?estado=analizado&per_page=5` (se normaliza a la anterior)
✅ `/web/api/v1/stats/dashboard`
✅ `/web/api/v1/colas/estado`
✅ `/web/api/v1/stats/actividad?limit=8`

## Notas adicionales

1. **Compatibilidad**: Las reglas son compatibles con Apache 2.2+ y 2.4+
2. **Rendimiento**: Las condiciones `-f` y `-d` verifican la existencia de archivos/directorios, lo cual es eficiente
3. **Seguridad**: Se mantienen las reglas de bloqueo de archivos sensibles y headers de seguridad

## Si el problema persiste

Si después de aplicar estos cambios aún recibes errores 403:

1. **Verifica los logs de Apache**: Revisa `/var/log/apache2/error.log` o el equivalente en Hostinger
2. **Verifica mod_rewrite**: Asegúrate de que el módulo `mod_rewrite` esté habilitado
3. **Verifica permisos**: El archivo `.htaccess` debe tener permisos de lectura (644)
4. **Verifica la ruta base**: Asegúrate de que la ruta base de tu aplicación sea correcta

## Pregunta para Hostinger (si el problema persiste)

Si después de aplicar estos cambios el problema continúa, pregunta:

> "He actualizado mi `.htaccess` con las reglas recomendadas, pero aún recibo errores 403 en URLs con barra diagonal al final. ¿Pueden verificar si hay alguna regla de seguridad a nivel de servidor (mod_security, reglas de protección de directorios, etc.) que esté bloqueando estas solicitudes? ¿Pueden revisar los logs de error de Apache para mi dominio y ver qué está causando el 403?"

