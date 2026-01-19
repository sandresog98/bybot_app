# Pasos para Solucionar el Error 403 - Guía Paso a Paso

## ✅ PASO 1: Verificar que el archivo .htaccess existe en el servidor

### 1.1. Conecta a tu servidor Hostinger
- Usa FTP/SFTP (FileZilla, WinSCP, o el administrador de archivos de Hostinger)
- Navega a: `/public_html/bybjuridicos/web/api/`

### 1.2. Verifica que existe el archivo `.htaccess`
- Debe estar en: `/public_html/bybjuridicos/web/api/.htaccess`
- Si NO existe, créalo o súbelo desde tu máquina local

### 1.3. Verifica los permisos del archivo
- Click derecho en el archivo → Propiedades/Permisos
- Debe ser: **644** (rw-r--r--)
- Si no es 644, cámbialo a 644

---

## ✅ PASO 2: Verificar el contenido del .htaccess

### 2.1. Abre el archivo `.htaccess` en el servidor
- Debe tener exactamente este contenido:

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

### 2.2. Si el contenido es diferente, reemplázalo completamente con el de arriba
- Guarda el archivo

---

## ✅ PASO 3: Verificar que index.php existe

### 3.1. Verifica que existe el archivo
- Ruta: `/public_html/bybjuridicos/web/api/index.php`
- Si NO existe, súbelo desde tu máquina local

### 3.2. Verifica los permisos
- Debe ser: **644** (rw-r--r--)

---

## ✅ PASO 4: Probar la URL

### 4.1. Abre tu navegador
- Ve a: `https://bybjuridicos.andapps.cloud/web/api/v1/procesos?estado=analizado&per_page=5`
- **SIN barra diagonal antes del `?`**

### 4.2. Si sigue dando 403, prueba también:
- `https://bybjuridicos.andapps.cloud/web/api/v1/procesos/?estado=analizado&per_page=5`
- **CON barra diagonal antes del `?`**

### 4.3. Limpia la caché del navegador
- Presiona **Ctrl + Shift + Delete** (o Cmd + Shift + Delete en Mac)
- Selecciona "Caché" o "Cached images and files"
- Click en "Limpiar datos"
- Recarga la página con **Ctrl + F5** (o Cmd + Shift + R en Mac)

---

## ✅ PASO 5: Si TODAVÍA da 403 - Verificar con Hostinger

### 5.1. Si después de los pasos anteriores sigue dando 403, el problema está en el servidor

### 5.2. Contacta a Hostinger con este mensaje:

```
Hola,

Estoy recibiendo errores 403 (Forbidden) en mi API REST cuando accedo a URLs como:

https://bybjuridicos.andapps.cloud/web/api/v1/procesos?estado=analizado&per_page=5

He verificado que:
- El archivo .htaccess existe en /public_html/bybjuridicos/web/api/.htaccess con permisos 644
- El archivo index.php existe en /public_html/bybjuridicos/web/api/index.php
- Las reglas de reescritura están correctas en el .htaccess
- El módulo mod_rewrite debería estar habilitado

El error 403 aparece tanto en URLs con barra diagonal como sin barra diagonal antes de los parámetros de query.

¿Pueden verificar si hay reglas de mod_security o otras configuraciones de seguridad a nivel de servidor que estén bloqueando estas solicitudes? 

¿Pueden revisar los logs de error de Apache para ver qué está causando específicamente el 403?

Gracias.
```

---

## 📋 Checklist Rápido

Marca cada paso cuando lo completes:

- [ ] Archivo `.htaccess` existe en `/public_html/bybjuridicos/web/api/.htaccess`
- [ ] Permisos del `.htaccess` son 644
- [ ] Contenido del `.htaccess` es correcto (copiado de arriba)
- [ ] Archivo `index.php` existe en `/public_html/bybjuridicos/web/api/index.php`
- [ ] Permisos del `index.php` son 644
- [ ] Probé la URL en el navegador (sin barra diagonal)
- [ ] Probé la URL en el navegador (con barra diagonal)
- [ ] Limpié la caché del navegador
- [ ] Si sigue dando 403, contacté a Hostinger

---

## 🔍 Verificación Adicional (Opcional)

Si quieres verificar que el `.htaccess` se está aplicando:

1. Agrega temporalmente esta línea al inicio del `.htaccess`:
```apache
<IfModule mod_headers.c>
    Header set X-Htaccess-Test "Working"
</IfModule>
```

2. Prueba la URL en el navegador
3. Abre las herramientas de desarrollador (F12)
4. Ve a la pestaña "Network" (Red)
5. Click en la solicitud que falló
6. Ve a "Headers" (Encabezados)
7. Busca `X-Htaccess-Test: Working`
   - Si lo ves: El `.htaccess` se está aplicando, el problema es otra cosa
   - Si NO lo ves: El `.htaccess` no se está aplicando, verifica permisos y ubicación

---

## ⚠️ Nota Importante

Si después de completar todos los pasos anteriores el problema persiste, **definitivamente es un problema de configuración del servidor** que solo Hostinger puede resolver. No es un problema de tu código.

