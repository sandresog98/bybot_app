# 🧪 Plan de Pruebas - ByBot v2.0

Este documento contiene las pruebas necesarias para validar cada fase del proyecto antes de pasar a producción.

---

## 📋 Índice

1. [Fase 1: Fundamentos](#fase-1-fundamentos)
2. [Fase 2: API REST](#fase-2-api-rest)
3. [Fase 3: Panel Administrativo](#fase-3-panel-administrativo)
4. [Fase 4: Integración n8n (PHP)](#fase-4-integración-n8n-php)
5. [Fase 5: Scripts Python y Flujos n8n](#fase-5-scripts-python-y-flujos-n8n)
6. [Fase 6: Integración Completa](#fase-6-integración-completa)

---

## 🔧 Preparación del Entorno

### Requisitos Previos

1. **Servidor Web** con PHP 8.2+ (XAMPP local o Hostinger)
2. **Base de Datos** MariaDB 11.8+
3. **Composer** instalado
4. **Navegador** con DevTools
5. **Herramienta HTTP** (Postman, Insomnia, o cURL)

### Configuración Inicial

```bash
# 1. Navegar al proyecto
cd /path/to/bybot

# 2. Crear archivo .env desde el template
cp env_example.txt .env

# 3. Editar .env con tus credenciales
nano .env
```

**Variables mínimas requeridas para pruebas locales:**
```env
APP_ENV=development
APP_DEBUG=true
APP_URL=http://localhost/projects/bybot

DB_HOST=localhost
DB_PORT=3306
DB_NAME=bybot_test
DB_USER=root
DB_PASS=

# Para Fase 4+
WORKER_API_TOKEN=test_token_12345
N8N_WEBHOOK_URL=http://localhost:5678/webhook
```

---

## 📦 Fase 1: Fundamentos

### 1.1 Verificar Estructura de Carpetas

**Objetivo:** Confirmar que todas las carpetas necesarias existen.

```bash
# Ejecutar desde la raíz del proyecto
ls -la bybot/
```

**Resultado esperado:**
```
├── assets/
├── config/
├── logs/
├── n8n/
├── sql/
├── uploads/
└── web/
```

**Estado:** ✅ Completado - 2026-01-18

---

### 1.2 Probar Carga de Configuración

**Archivo a probar:** `config/constants.php`

**Crear archivo de prueba:** `test_config.php`

```php
<?php
// test_config.php - Eliminar después de probar
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Test de Configuración</h2>";

try {
    require_once __DIR__ . '/config/constants.php';
    
    echo "<p>✅ constants.php cargado correctamente</p>";
    echo "<p>APP_URL: " . APP_URL . "</p>";
    echo "<p>APP_ENV: " . APP_ENV . "</p>";
    echo "<p>BYBOT_ROOT: " . BYBOT_ROOT . "</p>";
    
    // Probar clases
    echo "<p>Estados disponibles: " . implode(', ', EstadoProceso::todos()) . "</p>";
    echo "<p>Roles disponibles: " . implode(', ', RolUsuario::todos()) . "</p>";
    
    echo "<p style='color:green;font-weight:bold'>✅ FASE 1.2 COMPLETADA</p>";
    
} catch (Exception $e) {
    echo "<p style='color:red'>❌ Error: " . $e->getMessage() . "</p>";
}
```

**Ejecutar:** `http://localhost/projects/bybot/test_config.php`

**Resultado esperado:**
- No errores PHP
- Variables mostradas correctamente
- Clases EstadoProceso y RolUsuario funcionan

**Estado:** ✅ Completado - 2026-01-18

---

### 1.3 Probar Conexión a Base de Datos

**Archivo a probar:** `config/database.php`

**Crear archivo de prueba:** `test_database.php`

```php
<?php
// test_database.php - Eliminar después de probar
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Test de Base de Datos</h2>";

try {
    require_once __DIR__ . '/config/database.php';
    
    $conn = getConnection();
    
    if ($conn) {
        echo "<p>✅ Conexión exitosa a la base de datos</p>";
        
        // Probar query simple
        $stmt = $conn->query("SELECT 1 as test");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['test'] == 1) {
            echo "<p>✅ Query de prueba exitoso</p>";
        }
        
        echo "<p style='color:green;font-weight:bold'>✅ FASE 1.3 COMPLETADA</p>";
    }
    
} catch (PDOException $e) {
    echo "<p style='color:red'>❌ Error de conexión: " . $e->getMessage() . "</p>";
    echo "<p>Verifica las credenciales en .env</p>";
} catch (Exception $e) {
    echo "<p style='color:red'>❌ Error: " . $e->getMessage() . "</p>";
}
```

**Estado:** ✅ Completado - 2026-01-18

---

### 1.4 Crear Tablas de Base de Datos

**Archivo:** `sql/ddl.sql`

**Pasos:**
1. Abrir phpMyAdmin o cliente MySQL
2. Crear base de datos: `CREATE DATABASE bybot_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;`
3. Seleccionar la base de datos
4. Ejecutar el contenido de `sql/ddl.sql`

**Verificar tablas creadas:**
```sql
SHOW TABLES;
```

**Resultado esperado:**
```
control_usuarios
control_logs
procesos
procesos_anexos
procesos_datos_ia
procesos_historial
cola_trabajos
configuracion
configuracion_prompts
configuracion_plantillas
```

**Estado:** ⬜ Pendiente

---

### 1.5 Insertar Usuario de Prueba

```sql
-- Insertar usuario admin de prueba
-- Password: admin123 (hash bcrypt)
INSERT INTO control_usuarios (nombre, email, password, rol, activo) 
VALUES (
    'Admin Test',
    'admin@test.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'admin',
    1
);
```

**Credenciales de prueba:**
- Email: `admin@test.com`
- Password: `admin123`

**Estado:** ✅ Completado - 2026-01-18

---

### 1.6 Verificar Assets CSS/JS

**Archivos a verificar:**
- `assets/css/variables.css`
- `assets/css/common.css`
- `assets/css/admin.css`
- `assets/js/common.js`
- `assets/js/admin.js`

**Crear archivo de prueba:** `test_assets.html`

```html
<!DOCTYPE html>
<html>
<head>
    <title>Test Assets</title>
    <link rel="stylesheet" href="assets/css/variables.css">
    <link rel="stylesheet" href="assets/css/common.css">
    <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>
    <h2>Test de Assets</h2>
    
    <div class="card" style="padding: 20px; margin: 20px;">
        <p>Si ves estilos aplicados, los CSS funcionan.</p>
        <span class="badge bg-primary">Badge Primary</span>
        <span class="badge bg-success">Badge Success</span>
        <button class="btn btn-primary">Botón</button>
    </div>
    
    <script src="assets/js/common.js"></script>
    <script src="assets/js/admin.js"></script>
    <script>
        console.log('JS cargado correctamente');
        if (typeof ByBot !== 'undefined') {
            console.log('✅ ByBot object disponible');
        }
    </script>
</body>
</html>
```

**Verificar en DevTools:**
- Console sin errores 404
- Estilos aplicados visualmente

**Estado:** ✅ Completado - 2026-01-18

---

## 📡 Fase 2: API REST

### 2.1 Verificar Routing de API

**Crear archivo de prueba:** `test_api_routing.php`

```php
<?php
// test_api_routing.php - Eliminar después de probar
header('Content-Type: application/json');

echo json_encode([
    'test' => 'API routing works',
    'method' => $_SERVER['REQUEST_METHOD'],
    'uri' => $_SERVER['REQUEST_URI']
]);
```

**Probar .htaccess:**
```bash
curl -X GET "http://localhost/projects/bybot/web/api/v1/test"
```

**Estado:** ⬜ Pendiente

---

### 2.2 Probar Middleware CORS

```bash
# Probar OPTIONS (preflight)
curl -X OPTIONS "http://localhost/projects/bybot/web/api/v1/auth/login" \
  -H "Origin: http://example.com" \
  -H "Access-Control-Request-Method: POST" \
  -v
```

**Resultado esperado:**
- Header `Access-Control-Allow-Origin` presente
- Status 200

**Estado:** ⬜ Pendiente

---

### 2.3 Probar Autenticación - Login

```bash
curl -X POST "http://localhost/projects/bybot/web/api/v1/auth/login" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "admin@test.com",
    "password": "admin123"
  }'
```

**Resultado esperado:**
```json
{
    "success": true,
    "message": "Login exitoso",
    "data": {
        "user": {
            "id": 1,
            "nombre": "Admin Test",
            "email": "admin@test.com",
            "rol": "admin"
        }
    }
}
```

**Estado:** ⬜ Pendiente

---

### 2.4 Probar Autenticación - Me (con sesión)

```bash
# Primero obtener cookie de sesión con login
curl -X POST "http://localhost/projects/bybot/web/api/v1/auth/login" \
  -H "Content-Type: application/json" \
  -d '{"email": "admin@test.com", "password": "admin123"}' \
  -c cookies.txt

# Luego probar /me con la cookie
curl -X GET "http://localhost/projects/bybot/web/api/v1/auth/me" \
  -b cookies.txt
```

**Resultado esperado:**
```json
{
    "success": true,
    "data": {
        "id": 1,
        "nombre": "Admin Test",
        ...
    }
}
```

**Estado:** ⬜ Pendiente

---

### 2.5 Probar Endpoint de Procesos - Listar

```bash
curl -X GET "http://localhost/projects/bybot/web/api/v1/procesos" \
  -b cookies.txt
```

**Resultado esperado:**
```json
{
    "success": true,
    "data": [],
    "pagination": {
        "page": 1,
        "per_page": 10,
        "total": 0
    }
}
```

**Estado:** ⬜ Pendiente

---

### 2.6 Probar Endpoint de Procesos - Crear

```bash
curl -X POST "http://localhost/projects/bybot/web/api/v1/procesos" \
  -H "Content-Type: application/json" \
  -b cookies.txt \
  -d '{
    "tipo": "cobranza",
    "prioridad": 5,
    "notas": "Proceso de prueba"
  }'
```

**Resultado esperado:**
```json
{
    "success": true,
    "message": "Proceso creado exitosamente",
    "data": {
        "id": 1,
        "codigo": "PR-20260118-0001",
        "estado": "creado",
        ...
    }
}
```

**Estado:** ⬜ Pendiente

---

### 2.7 Probar Endpoint de Estadísticas

```bash
curl -X GET "http://localhost/projects/bybot/web/api/v1/stats/dashboard" \
  -b cookies.txt
```

**Resultado esperado:** JSON con estadísticas del dashboard

**Estado:** ⬜ Pendiente

---

### 2.8 Probar Middleware de Token (para Workers)

```bash
# Sin token - debe fallar
curl -X POST "http://localhost/projects/bybot/web/api/v1/webhook/n8n/analisis" \
  -H "Content-Type: application/json" \
  -d '{"proceso_id": 1}'

# Con token - debe funcionar (o dar error de proceso no encontrado)
curl -X POST "http://localhost/projects/bybot/web/api/v1/webhook/n8n/analisis" \
  -H "Content-Type: application/json" \
  -H "X-N8N-Access-Token: test_token_12345" \
  -d '{"proceso_id": 1, "success": true, "datos": {}}'
```

**Estado:** ⬜ Pendiente

---

## 🖥️ Fase 3: Panel Administrativo

### 3.1 Acceder a Login

**URL:** `http://localhost/projects/bybot/web/admin/login.php`

**Verificar:**
- [ ] Página carga sin errores
- [ ] Formulario visible
- [ ] Estilos aplicados correctamente

**Estado:** ⬜ Pendiente

---

### 3.2 Probar Login

**Credenciales:**
- Email: `admin@test.com`
- Password: `admin123`

**Verificar:**
- [ ] Login exitoso
- [ ] Redirección a dashboard
- [ ] Sesión creada

**Estado:** ⬜ Pendiente

---

### 3.3 Verificar Dashboard

**URL:** `http://localhost/projects/bybot/web/admin/index.php`

**Verificar:**
- [ ] Header con nombre de usuario
- [ ] Sidebar con menú
- [ ] Cards de estadísticas
- [ ] Sin errores en consola

**Estado:** ⬜ Pendiente

---

### 3.4 Probar Módulo de Procesos - Lista

**URL:** `http://localhost/projects/bybot/web/admin/index.php?page=procesos`

**Verificar:**
- [ ] Tabla de procesos
- [ ] Filtros funcionan
- [ ] Botón "Nuevo Proceso"

**Estado:** ⬜ Pendiente

---

### 3.5 Probar Módulo de Procesos - Crear

**URL:** `http://localhost/projects/bybot/web/admin/index.php?page=procesos&action=crear`

**Verificar:**
- [ ] Formulario de creación
- [ ] Upload de archivos
- [ ] Proceso se crea correctamente

**Estado:** ⬜ Pendiente

---

### 3.6 Probar Módulo de Procesos - Ver

**URL:** `http://localhost/projects/bybot/web/admin/index.php?page=procesos&action=ver&id=1`

**Verificar:**
- [ ] Información del proceso
- [ ] Archivos adjuntos
- [ ] Historial
- [ ] Acciones disponibles

**Estado:** ⬜ Pendiente

---

### 3.7 Probar Módulo de Usuarios

**URL:** `http://localhost/projects/bybot/web/admin/index.php?page=usuarios`

**Verificar:**
- [ ] Lista de usuarios
- [ ] Crear nuevo usuario
- [ ] Editar usuario
- [ ] Cambiar rol

**Estado:** ⬜ Pendiente

---

### 3.8 Probar Módulo de Configuración

**URL:** `http://localhost/projects/bybot/web/admin/index.php?page=configuracion`

**Verificar:**
- [ ] Configuración general
- [ ] Prompts de IA
- [ ] Estado de colas

**Estado:** ⬜ Pendiente

---

### 3.9 Probar Logout

**Verificar:**
- [ ] Click en logout
- [ ] Sesión destruida
- [ ] Redirección a login

**Estado:** ⬜ Pendiente

---

### 3.10 Probar Permisos por Rol

1. Crear usuario con rol `operador`
2. Login con ese usuario
3. Verificar que no puede acceder a:
   - Usuarios
   - Configuración

**Estado:** ⬜ Pendiente

---

## 🔌 Fase 4: Integración n8n (PHP)

### 4.1 Verificar N8nClient

**Crear archivo de prueba:** `test_n8n_client.php`

```php
<?php
// test_n8n_client.php - Eliminar después de probar
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Test N8nClient</h2>";

try {
    define('BASE_DIR', __DIR__);
    require_once __DIR__ . '/config/constants.php';
    require_once __DIR__ . '/web/core/N8nClient.php';
    
    $client = new N8nClient();
    
    echo "<p>✅ N8nClient instanciado correctamente</p>";
    echo "<p>N8N_WEBHOOK_URL: " . N8N_WEBHOOK_URL . "</p>";
    
    // Nota: Este test solo verifica que la clase carga
    // El test real de conexión se hace en Fase 6
    
    echo "<p style='color:green;font-weight:bold'>✅ FASE 4.1 COMPLETADA</p>";
    
} catch (Exception $e) {
    echo "<p style='color:red'>❌ Error: " . $e->getMessage() . "</p>";
}
```

**Estado:** ⬜ Pendiente

---

### 4.2 Verificar Endpoint de Webhook n8n

```bash
# Test callback de análisis (simulado)
curl -X POST "http://localhost/projects/bybot/web/api/v1/webhook/n8n/analisis" \
  -H "Content-Type: application/json" \
  -H "X-N8N-Access-Token: test_token_12345" \
  -d '{
    "proceso_id": 1,
    "success": true,
    "datos": {
        "estado_cuenta": {"total_deuda": 5000000},
        "deudor": {"nombre_completo": "Juan Pérez"}
    }
  }'
```

**Resultado esperado:** 
- Si proceso existe: actualización exitosa
- Si no existe: error 404 controlado

**Estado:** ⬜ Pendiente

---

### 4.3 Verificar Endpoint de Servir Archivos

```bash
# Primero subir un archivo mediante el admin
# Luego probar descarga con token

curl -X GET "http://localhost/projects/bybot/web/api/v1/archivos/servir?id=1" \
  -H "X-N8N-Access-Token: test_token_12345" \
  -o archivo_descargado.pdf
```

**Estado:** ⬜ Pendiente

---

### 4.4 Verificar Endpoint de Subida Externa

```bash
curl -X POST "http://localhost/projects/bybot/web/api/v1/archivos/subir-externo" \
  -H "X-N8N-Access-Token: test_token_12345" \
  -F "proceso_id=1" \
  -F "tipo=pagare_llenado" \
  -F "archivo=@/path/to/test.pdf"
```

**Estado:** ⬜ Pendiente

---

### 4.5 Verificar ProcesoService con n8n

**Crear archivo de prueba:** `test_proceso_service.php`

```php
<?php
// test_proceso_service.php - Eliminar después de probar
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Test ProcesoService</h2>";

try {
    define('BASE_DIR', __DIR__);
    require_once __DIR__ . '/config/constants.php';
    require_once __DIR__ . '/config/database.php';
    require_once __DIR__ . '/web/core/Response.php';
    require_once __DIR__ . '/web/modules/procesos/services/ProcesoService.php';
    
    $service = new ProcesoService();
    
    echo "<p>✅ ProcesoService instanciado</p>";
    
    // Listar procesos
    $procesos = $service->listar();
    echo "<p>Procesos encontrados: " . count($procesos['data']) . "</p>";
    
    // Obtener estadísticas
    $stats = $service->getEstadisticas();
    echo "<p>Estadísticas: " . json_encode($stats) . "</p>";
    
    echo "<p style='color:green;font-weight:bold'>✅ FASE 4.5 COMPLETADA</p>";
    
} catch (Exception $e) {
    echo "<p style='color:red'>❌ Error: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
```

**Estado:** ⬜ Pendiente

---

## 🐍 Fase 5: Scripts Python y Flujos n8n

> **Nota:** Esta fase requiere acceso al VPS con n8n y Python instalado.

### 5.1 Verificar Instalación Python en VPS

```bash
# Conectar al VPS
ssh usuario@tu-vps

# Verificar Python
python3 --version  # Debe ser 3.12.3

# Verificar pip
pip3 --version
```

**Estado:** ⬜ Pendiente

---

### 5.2 Instalar Scripts en VPS

```bash
# Crear estructura
sudo mkdir -p /opt/bybot/scripts
sudo chown -R $USER:$USER /opt/bybot

# Copiar scripts (desde tu local)
# O clonar desde repositorio
```

**Estado:** ⬜ Pendiente

---

### 5.3 Crear Entorno Virtual

```bash
cd /opt/bybot/scripts
python3 -m venv venv
source venv/bin/activate

# Instalar dependencias
pip install -r requirements.txt

# Verificar instalación
python -c "import google.generativeai; import fitz; print('OK')"
```

**Estado:** ⬜ Pendiente

---

### 5.4 Configurar .env en VPS

```bash
cp env_example.txt .env
nano .env

# Configurar:
# - BYBOT_API_URL
# - BYBOT_ACCESS_TOKEN (mismo que WORKER_API_TOKEN en PHP)
# - GEMINI_API_KEY
```

**Estado:** ⬜ Pendiente

---

### 5.5 Test Standalone - Analyzer

```bash
cd /opt/bybot/scripts
source venv/bin/activate

# Test sin callback (solo ver output)
python analyzer/main.py \
  --proceso_id 0 \
  --archivos_locales "/path/to/test.pdf" \
  --no_callback
```

**Resultado esperado:** JSON con datos extraídos o error descriptivo

**Estado:** ⬜ Pendiente

---

### 5.6 Test Standalone - Filler

```bash
python filler/main.py \
  --proceso_id 0 \
  --pagare_local "/path/to/pagare.pdf" \
  --datos '{"deudor":{"nombre_completo":"Test User"}}' \
  --no_callback \
  --no_upload
```

**Resultado esperado:** PDF generado en temp/

**Estado:** ⬜ Pendiente

---

### 5.7 Importar Flujos en n8n

1. Acceder a n8n: `https://n8n.srv1083920.hstgr.cloud`
2. Ir a Workflows → Import
3. Importar `flujo_analisis.json`
4. Importar `flujo_llenado.json`
5. **NO ACTIVAR AÚN**

**Estado:** ⬜ Pendiente

---

### 5.8 Verificar Configuración de Flujos

Para cada flujo importado:
1. Abrir el flujo
2. Verificar nodos de "Execute Command"
3. Ajustar ruta de Python si es necesario:
   ```
   /opt/bybot/scripts/venv/bin/python
   ```
4. Verificar URLs de callback apuntan a Hostinger

**Estado:** ⬜ Pendiente

---

## 🔗 Fase 6: Integración Completa

> **Prerrequisito:** Todas las fases anteriores deben estar completadas.

### 6.1 Configurar .env de Producción (Hostinger)

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://bybjuridicos.andapps.cloud

DB_HOST=tu_host_hostinger
DB_NAME=tu_base_datos
DB_USER=tu_usuario
DB_PASS=tu_password

N8N_WEBHOOK_URL=https://n8n.srv1083920.hstgr.cloud/webhook
WORKER_API_TOKEN=token_seguro_generado
```

**Estado:** ⬜ Pendiente

---

### 6.2 Activar Flujos en n8n

1. Abrir cada flujo en n8n
2. Click en toggle "Active"
3. Copiar URLs de webhook generadas
4. Verificar que coincidan con las configuradas en PHP

**Estado:** ⬜ Pendiente

---

### 6.3 Test End-to-End: Crear Proceso

1. Login en Admin Panel
2. Ir a Procesos → Nuevo Proceso
3. Subir archivos de prueba
4. Crear proceso
5. Verificar estado "creado"

**Estado:** ⬜ Pendiente

---

### 6.4 Test End-to-End: Encolar Análisis

1. En el proceso creado, click "Analizar"
2. Verificar cambio de estado a "en_cola_analisis"
3. Verificar en n8n que se ejecutó el flujo
4. Esperar callback
5. Verificar estado "analizado" y datos extraídos

**Estado:** ⬜ Pendiente

---

### 6.5 Test End-to-End: Validar Datos

1. En el proceso analizado, ir a "Validar"
2. Revisar datos extraídos
3. Editar si es necesario
4. Click "Confirmar Validación"
5. Verificar estado "validado"

**Estado:** ⬜ Pendiente

---

### 6.6 Test End-to-End: Llenar Pagaré

1. En el proceso validado, click "Llenar Pagaré"
2. Verificar cambio de estado a "en_cola_llenado"
3. Verificar en n8n que se ejecutó el flujo
4. Esperar callback
5. Verificar estado "completado"
6. Descargar pagaré llenado

**Estado:** ⬜ Pendiente

---

## 📝 Checklist de Verificación Final

### Funcionalidad Core
- [ ] Login/Logout funcionan
- [ ] Crear proceso funciona
- [ ] Subir archivos funciona
- [ ] Análisis con IA funciona
- [ ] Validación de datos funciona
- [ ] Llenado de pagaré funciona
- [ ] Descarga de archivos funciona

### Seguridad
- [ ] Sin acceso sin login
- [ ] Permisos por rol funcionan
- [ ] Token de API validado
- [ ] Archivos protegidos

### Rendimiento
- [ ] Páginas cargan < 3 segundos
- [ ] Análisis completa < 60 segundos
- [ ] Llenado completa < 30 segundos

### Errores
- [ ] Errores manejados gracefully
- [ ] Logs registrados
- [ ] Reintentos automáticos funcionan

---

## 🐛 Troubleshooting Común

### Error: "Class not found"
```
Verificar:
1. Rutas de require_once
2. Nombres de archivos (case-sensitive en Linux)
3. Autoloader si usas Composer
```

### Error: "Connection refused" a n8n
```
Verificar:
1. URL de n8n correcta
2. Webhook activado en n8n
3. Firewall permite conexión
```

### Error: "Permission denied" en VPS
```
Verificar:
1. Permisos de archivos: chmod +x main.py
2. Propietario correcto: chown -R $USER:$USER /opt/bybot
```

### Error: "CORS blocked"
```
Verificar:
1. Middleware CORS incluido
2. Headers correctos en respuesta
3. Dominio permitido en configuración
```

---

## 📊 Registro de Pruebas

| Fase | Test | Estado | Fecha | Notas |
|------|------|--------|-------|-------|
| 1.1 | Estructura carpetas | ✅ | 2026-01-18 | Todas las carpetas verificadas |
| 1.2 | Configuración | ✅ | 2026-01-18 | test_config.php ejecutado exitosamente |
| 1.3 | Base de datos | ✅ | 2026-01-18 | test_database.php ejecutado exitosamente |
| 1.4 | DDL | ✅ | 2026-01-18 | Tablas creadas en BD 'bybot' |
| 1.5 | Usuario prueba | ✅ | 2026-01-18 | Usuario admin insertado |
| 1.6 | Assets | ✅ | 2026-01-18 | test_assets.html ejecutado exitosamente |
| 2.1 | API Routing | ⬜ | | |
| 2.2 | CORS | ⬜ | | |
| 2.3 | Login API | ⬜ | | |
| ... | ... | ⬜ | | |

---

**Documento creado:** 2026-01-18  
**Última actualización:** 2026-01-18  
**Versión:** 1.0

