# Verificación y Solución del Error 403

## Problema
La URL `/web/api/v1/procesos/?estado=analizado&per_page=5` está generando error 403 (Forbidden).

## Archivos que deben subirse al servidor

### 1. Archivo `.htaccess` de la API
**Ruta en servidor**: `/public_html/bybjuridicos/web/api/.htaccess`

**Contenido esperado**:
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

### 2. Archivo `dashboard.php`
**Ruta en servidor**: `/public_html/bybjuridicos/web/admin/pages/dashboard.php`

**Verificación**: El archivo debe tener la función `loadPendientesValidacion()` que construye la URL sin barras duplicadas.

### 3. Archivo `footer.php`
**Ruta en servidor**: `/public_html/bybjuridicos/web/admin/views/layouts/footer.php`

**Verificación**: El archivo debe tener la función `normalizeUrl()` y `CONFIG.apiUrlFor()`.

## Pasos de verificación

### Paso 1: Verificar que los archivos estén en el servidor
1. Conecta por FTP/SFTP a tu servidor Hostinger
2. Navega a `/public_html/bybjuridicos/web/api/`
3. Verifica que existe el archivo `.htaccess` con el contenido correcto
4. Verifica los permisos del archivo (debe ser 644)

### Paso 2: Limpiar caché del navegador
1. Abre las herramientas de desarrollador (F12)
2. Ve a la pestaña "Network" (Red)
3. Marca la opción "Disable cache" (Desactivar caché)
4. O usa Ctrl+Shift+Delete para limpiar la caché
5. Recarga la página con Ctrl+F5 (o Cmd+Shift+R en Mac)

### Paso 3: Verificar en la consola del navegador
1. Abre las herramientas de desarrollador (F12)
2. Ve a la pestaña "Console" (Consola)
3. Busca el mensaje: `URL construida para procesos:`
4. Verifica que la URL mostrada NO tenga barra diagonal antes del `?`
   - ✅ Correcto: `https://bybjuridicos.andapps.cloud/web/api/v1/procesos?estado=analizado&per_page=5`
   - ❌ Incorrecto: `https://bybjuridicos.andapps.cloud/web/api/v1/procesos/?estado=analizado&per_page=5`

### Paso 4: Verificar el .htaccess
1. Accede a tu servidor por SSH o FTP
2. Verifica que el archivo `/public_html/bybjuridicos/web/api/.htaccess` existe
3. Verifica que tiene las reglas correctas (especialmente `Options -Indexes` y `RewriteRule ^(.*)/?$ index.php [QSA,L]`)

### Paso 5: Probar directamente en el navegador
Intenta acceder directamente a estas URLs en el navegador:

1. **Sin barra diagonal** (debe funcionar):
   ```
   https://bybjuridicos.andapps.cloud/web/api/v1/procesos?estado=analizado&per_page=5
   ```

2. **Con barra diagonal** (debe funcionar después de aplicar .htaccess):
   ```
   https://bybjuridicos.andapps.cloud/web/api/v1/procesos/?estado=analizado&per_page=5
   ```

Si ambas URLs devuelven 403, el problema está en el servidor (reglas de seguridad a nivel de servidor).

## Si el problema persiste

### Verificar logs de Apache
1. Accede al panel de Hostinger
2. Busca la sección "Logs" o "Error Logs"
3. Revisa los logs de error de Apache
4. Busca entradas relacionadas con `/web/api/v1/procesos/`

### Consultar a Hostinger
Si después de verificar todo lo anterior el problema persiste, consulta a Hostinger con este mensaje:

> "He actualizado mi `.htaccess` en `/public_html/bybjuridicos/web/api/` con las reglas recomendadas (incluyendo `Options -Indexes` y `RewriteRule ^(.*)/?$ index.php [QSA,L]`), pero aún recibo errores 403 en URLs con barra diagonal al final como `/web/api/v1/procesos/?estado=analizado&per_page=5`. 
> 
> He verificado que:
> - El archivo `.htaccess` existe y tiene los permisos correctos (644)
> - El módulo `mod_rewrite` está habilitado
> - Las reglas de reescritura están correctas
> 
> ¿Pueden verificar si hay reglas de seguridad a nivel de servidor (mod_security, protección de directorios, etc.) que estén bloqueando estas solicitudes? ¿Pueden revisar los logs de error de Apache para ver qué está causando el 403 específicamente?"

## Solución temporal

Si necesitas una solución temporal mientras se resuelve el problema del servidor, puedes modificar el código para que siempre use URLs sin barra diagonal al final. El código actual ya debería hacer esto, pero si el problema persiste, asegúrate de que:

1. `API_URL` no termine con barra diagonal (ya está corregido con `rtrim()`)
2. Los endpoints no empiecen con barra diagonal (ya está corregido)
3. La construcción de URLs use la función helper o construcción explícita (ya está corregido)

## Checklist final

- [ ] Archivo `.htaccess` subido a `/public_html/bybjuridicos/web/api/.htaccess`
- [ ] Permisos del `.htaccess` son 644
- [ ] Archivo `dashboard.php` actualizado en el servidor
- [ ] Archivo `footer.php` actualizado en el servidor
- [ ] Caché del navegador limpiada
- [ ] Verificado en consola que la URL se construye correctamente
- [ ] Probado directamente en el navegador ambas variantes de URL
- [ ] Revisados los logs de error de Apache

