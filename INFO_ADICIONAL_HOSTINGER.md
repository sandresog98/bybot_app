# Información Adicional para Hostinger

## Ruta Exacta del .htaccess

**Ubicación:** `/public_html/bybjuridicos/web/api/.htaccess`

**Contenido actual:**
```apache
RewriteEngine On
Options -Indexes

# Permitir acceso directo a archivos físicos
RewriteCond %{REQUEST_FILENAME} -f
RewriteRule ^ - [L]

# Enviar todo lo demás a index.php
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [L,QSA]
```

## Estructura de la API

- **Router principal:** `/public_html/bybjuridicos/web/api/index.php`
- **Router de procesos:** `/public_html/bybjuridicos/web/api/v1/procesos/router.php`
- **Middleware de autenticación:** `/public_html/bybjuridicos/web/api/middleware/auth.php`

## Headers de la Respuesta 403

Cuando se accede a `/web/api/v1/procesos`, la respuesta incluye:
- **Status:** 403 Forbidden
- **Server:** LiteSpeed
- **Content-Type:** text/html (página HTML genérica de LiteSpeed)
- **No hay headers de PHP** (no pasa por PHP)

## Comparación con Rutas que Funcionan

**Ruta que funciona:** `/web/api/v1/stats/dashboard`
- Mismo `.htaccess`
- Mismo router (`index.php`)
- Misma estructura de carpetas
- **Resultado:** 200 OK, respuesta JSON de PHP

**Ruta que falla:** `/web/api/v1/procesos`
- Mismo `.htaccess`
- Mismo router (`index.php`)
- Misma estructura de carpetas
- **Resultado:** 403 Forbidden, HTML de LiteSpeed (no pasa por PHP)

## Palabra Clave Sospechosa

La única diferencia es la palabra **"procesos"** en la URL. Es probable que el WAF tenga una regla que detecta esta palabra como patrón sospechoso (posiblemente relacionado con "proceso" en el contexto de seguridad).

## Solución Sugerida

1. **Revisar logs de LiteSpeed/WAF** para ver qué regla específica está disparando el bloqueo
2. **Crear excepción** para la ruta `/web/api/v1/procesos` o para el path `/public_html/bybjuridicos/web/api/v1/procesos`
3. **Alternativa:** Si la regla es muy general, ajustarla para que no bloquee URLs dentro de `/web/api/`

## Información del Dominio

- **Dominio:** bybjuridicos.andapps.cloud
- **Ruta base:** /public_html/bybjuridicos/
- **Servidor:** LiteSpeed
- **Plan:** Business Web Hosting

