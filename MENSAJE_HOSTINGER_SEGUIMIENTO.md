# Mensaje de Seguimiento para Hostinger

```
Hola,

He actualizado el .htaccess según tus indicaciones, pero el problema 403 persiste.

El archivo /public_html/bybjuridicos/web/api/.htaccess ahora tiene:

RewriteEngine On
Options -Indexes
RewriteCond %{REQUEST_FILENAME} -f
RewriteRule ^ - [L]
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.php [L,QSA]

Sin embargo, las URLs siguen devolviendo 403:
- https://bybjuridicos.andapps.cloud/web/api/v1/procesos?estado=analizado&per_page=5
- https://bybjuridicos.andapps.cloud/web/api/v1/procesos/?estado=analizado&per_page=5

¿Pueden verificar:
1. ¿Hay algún otro .htaccess en directorios padre (/public_html/bybjuridicos/web/ o /public_html/bybjuridicos/) que pueda estar interfiriendo?
2. ¿El .htaccess en /public_html/bybjuridicos/web/api/ se está aplicando correctamente?
3. ¿Hay alguna configuración de Apache a nivel de servidor que pueda estar bloqueando estas solicitudes?

He creado un archivo de prueba: /public_html/bybjuridicos/web/api/test_debug.php
¿Pueden verificar si este archivo es accesible? Si también da 403, entonces el problema está antes de llegar a PHP.

Gracias.
```

