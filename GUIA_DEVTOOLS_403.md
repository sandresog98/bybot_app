# Guía: Cómo Verificar el Error 403 en Chrome DevTools

## Paso 2: Verificar la Respuesta del Error 403

### 2.1. Abrir las Herramientas de Desarrollador
1. Abre Chrome (o tu navegador)
2. Presiona **F12** (o **Ctrl+Shift+I** en Windows/Linux, **Cmd+Option+I** en Mac)
3. Se abrirá el panel de DevTools en la parte inferior o lateral

### 2.2. Ir a la Pestaña "Network" (Red)
1. En la parte superior del panel de DevTools, busca la pestaña **"Network"** (o **"Red"** si está en español)
2. Haz click en ella para activarla
3. Deberías ver una tabla vacía o con algunas peticiones

### 2.3. Activar el Registro de Red
1. Asegúrate de que el botón de **grabación** (círculo rojo) esté activado (debe estar rojo)
2. Si está gris, haz click en él para activarlo
3. Marca la casilla **"Preserve log"** (Preservar registro) para que no se borre al navegar

### 2.4. Hacer la Petición que Falla
1. En la barra de direcciones del navegador, escribe o pega:
   ```
   https://bybjuridicos.andapps.cloud/web/api/v1/procesos/?estado=analizado&per_page=5
   ```
2. Presiona **Enter**
3. Verás que aparece una nueva fila en la tabla de Network

### 2.5. Identificar la Petición que Falla
1. Busca en la tabla la petición que tiene:
   - **Name**: `procesos/?estado=analizado&per_page=5` (o similar)
   - **Status**: `403` (debe aparecer en rojo)
   - **Type**: `fetch` o `xhr` o `document`

### 2.6. Ver la Respuesta
1. **Haz click** en esa fila (la que tiene status 403)
2. Se abrirá un panel a la derecha con detalles
3. Busca la pestaña **"Response"** (o **"Respuesta"**)
4. Haz click en ella
5. **Copia todo el contenido** que aparece ahí (puede ser HTML, texto, o JSON)
6. Compártelo conmigo

---

## Paso 3: Verificar los Headers de Respuesta

### 3.1. Con la Petición Seleccionada
1. Con la petición que da 403 seleccionada (del paso anterior)
2. En el panel derecho que se abrió, busca la pestaña **"Headers"** (o **"Encabezados"**)
3. Haz click en ella

### 3.2. Buscar "Response Headers"
1. En el panel de Headers, verás dos secciones:
   - **Request Headers** (Encabezados de solicitud) - arriba
   - **Response Headers** (Encabezados de respuesta) - abajo
2. Ve a la sección **"Response Headers"** (abajo)
3. Verás una lista de headers como:
   - `Content-Type: ...`
   - `Server: ...`
   - `Status: 403`
   - etc.

### 3.3. Copiar los Headers
1. **Copia TODOS los headers** que aparecen en "Response Headers"
2. Puedes hacerlo de dos formas:
   - **Opción A**: Selecciona todo el texto y copia (Ctrl+C / Cmd+C)
   - **Opción B**: Toma una captura de pantalla de esa sección
3. Compártelos conmigo

### 3.4. Headers Importantes a Buscar
Busca específicamente estos headers (si existen):
- `Server:` - Indica qué servidor web está respondiendo
- `X-Powered-By:` - Indica si es PHP
- `Content-Type:` - Indica el tipo de contenido
- `Status:` - El código de estado (403)
- Cualquier header que empiece con `X-` o `Error-`

---

## Paso 3 Alternativo: Ver Request Headers (También Importante)

### 3A.1. Ver Request Headers
1. En la misma pestaña "Headers"
2. Ve a la sección **"Request Headers"** (arriba)
3. Busca específicamente:
   - `Cookie:` - ¿Aparece? ¿Qué contiene?
   - `Referer:` - ¿De dónde viene la petición?
   - `Origin:` - ¿Cuál es el origen?

### 3A.2. Verificar la Cookie de Sesión
1. Busca la línea que dice `Cookie:`
2. Debe contener algo como: `PHPSESSID=...` o `session_id=...`
3. **Copia esa línea completa**
4. Si NO aparece la línea `Cookie:`, eso es el problema - la sesión no se está enviando

---

## Resumen: Qué Compartir

Después de seguir estos pasos, comparte conmigo:

1. ✅ **Contenido de la pestaña "Response"** (Paso 2.6)
2. ✅ **Todos los "Response Headers"** (Paso 3.3)
3. ✅ **La línea "Cookie:" de "Request Headers"** (Paso 3A.2) - o confirma si NO aparece

Con esta información podré identificar exactamente qué está causando el 403.

---

## Captura de Pantalla (Opcional pero Útil)

Si puedes, toma una captura de pantalla de:
- La tabla de Network mostrando la petición con 403
- El panel de Headers con los Response Headers visibles

Esto me ayudará a ver exactamente qué está pasando.

