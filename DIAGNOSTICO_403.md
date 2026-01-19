# Diagnóstico del Error 403

## Situación Actual

- ✅ **URL construida correctamente**: `https://bybjuridicos.andapps.cloud/web/api/v1/procesos?estado=analizado&per_page=5`
- ❌ **Navegador normaliza a**: `https://bybjuridicos.andapps.cloud/web/api/v1/procesos/?estado=analizado&per_page=5`
- ❌ **Ambas URLs dan 403**: Esto indica que el problema está en el servidor, no en el código

## Posibles Causas

### 1. Regla de Seguridad a Nivel de Servidor (mod_security)
Apache puede tener reglas de seguridad que bloquean URLs con ciertos patrones. Las URLs con barra diagonal antes de query parameters pueden ser bloqueadas.

### 2. El .htaccess no se está aplicando
- Verifica que el archivo esté en `/public_html/bybjuridicos/web/api/.htaccess`
- Verifica los permisos (644)
- Verifica que `mod_rewrite` esté habilitado

### 3. Configuración de DirectoryIndex
Apache puede estar intentando buscar un `index.php` o `index.html` en el "directorio" `/procesos/` y fallando.

## Soluciones a Probar

### Solución 1: Verificar que el .htaccess se está aplicando

Agrega esta línea temporalmente al inicio del `.htaccess` para verificar que se está ejecutando:

```apache
# Test: Si ves este header, el .htaccess está funcionando
<IfModule mod_headers.c>
    Header set X-Htaccess-Test "Working"
</IfModule>
```

Luego verifica en las herramientas de desarrollador del navegador (pestaña Network → Headers) si aparece el header `X-Htaccess-Test: Working`.

### Solución 2: Usar la versión simplificada del .htaccess

Si la versión actual no funciona, prueba con la versión simplificada (archivo `.htaccess.backup`):

```apache
RewriteEngine On
Options -Indexes

# Permitir archivos físicos
RewriteCond %{REQUEST_FILENAME} -f
RewriteRule ^ - [L]

# Redirigir todo a index.php (versión simple)
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^(.*)$ index.php [PT,QSA,L]
```

### Solución 3: Agregar DirectoryIndex explícito

Agrega esto al `.htaccess`:

```apache
# Forzar que Apache no busque index en subdirectorios
DirectoryIndex disabled
```

### Solución 4: Deshabilitar DirectorySlash

Agrega esto al `.htaccess`:

```apache
# Prevenir que Apache agregue barras diagonales automáticamente
DirectorySlash Off
```

## Verificación Paso a Paso

### Paso 1: Verificar que el .htaccess existe y tiene permisos correctos

```bash
# En el servidor (por SSH o FTP)
ls -la /public_html/bybjuridicos/web/api/.htaccess
# Debe mostrar: -rw-r--r-- (644)
```

### Paso 2: Verificar que mod_rewrite está habilitado

Crea un archivo temporal `test_rewrite.php` en `/public_html/bybjuridicos/web/api/`:

```php
<?php
if (function_exists('apache_get_modules')) {
    $modules = apache_get_modules();
    if (in_array('mod_rewrite', $modules)) {
        echo "mod_rewrite está habilitado";
    } else {
        echo "mod_rewrite NO está habilitado";
    }
} else {
    echo "No se puede verificar módulos de Apache";
}
?>
```

Accede a: `https://bybjuridicos.andapps.cloud/web/api/test_rewrite.php`

### Paso 3: Probar directamente con curl

Desde tu máquina local, prueba:

```bash
# Sin barra diagonal
curl -I "https://bybjuridicos.andapps.cloud/web/api/v1/procesos?estado=analizado&per_page=5"

# Con barra diagonal
curl -I "https://bybjuridicos.andapps.cloud/web/api/v1/procesos/?estado=analizado&per_page=5"
```

Observa los headers de respuesta. Si ambos dan 403, el problema está en el servidor.

### Paso 4: Verificar logs de error

En el panel de Hostinger, busca los logs de error de Apache y busca entradas relacionadas con:
- `/web/api/v1/procesos`
- `403`
- `mod_security`
- `Directory index forbidden`

## Pregunta Específica para Hostinger

Si después de verificar todo lo anterior el problema persiste, pregunta a Hostinger:

> "Estoy recibiendo errores 403 en mi API REST cuando accedo a URLs como:
> 
> - `https://bybjuridicos.andapps.cloud/web/api/v1/procesos?estado=analizado&per_page=5`
> - `https://bybjuridicos.andapps.cloud/web/api/v1/procesos/?estado=analizado&per_page=5`
> 
> He verificado que:
> - El archivo `.htaccess` existe en `/public_html/bybjuridicos/web/api/.htaccess` con permisos 644
> - El módulo `mod_rewrite` está habilitado
> - Las reglas de reescritura están correctas
> - El archivo `index.php` existe y es accesible
> 
> **Ambas URLs (con y sin barra diagonal) están dando 403**, lo que sugiere que hay una regla de seguridad a nivel de servidor bloqueando estas solicitudes.
> 
> ¿Pueden verificar si hay reglas de `mod_security` o otras configuraciones de seguridad que estén bloqueando estas solicitudes? ¿Pueden revisar los logs de error de Apache para ver qué está causando específicamente el 403?
> 
> También, ¿pueden verificar si hay alguna configuración de `DirectoryIndex` o `DirectorySlash` que pueda estar interfiriendo?"

## Solución Temporal (Workaround)

Si el problema persiste y necesitas una solución temporal, puedes modificar el código para usar un endpoint diferente que no tenga este problema, o usar POST en lugar de GET para evitar que el navegador normalice la URL.

Pero la solución correcta es que Hostinger revise y ajuste las reglas de seguridad del servidor.

