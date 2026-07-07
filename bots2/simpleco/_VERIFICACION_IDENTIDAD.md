# Simple.co — Verificacion de identidad (PENDIENTE)

El portal Simple.co activa aleatoriamente una pagina de verificacion de identidad
(`consultaDirectaPreguntas.xhtml`) despues del 1.er Consultar. El bot la detecta
y retorna `ERROR_PREGUNTAS_SEGURIDAD`.

## Campos del formulario

| Campo | Requerido | Selector CSS |
|-------|-----------|-------------|
| Primer Nombre | Si (*) | `input[id$=":txtPrimerNombre"]` |
| Segundo Nombre | No | `input[id$=":txtSegundoNombre"]` |
| Primer Apellido | Si (*) | `input[id$=":txtPrimerApellido"]` |
| Segundo Apellido | No | `input[id$=":txtSegundoApellido"]` |
| Email | Si (*) | `input[id$=":txtEmail"]` |
| Ultima EPS a la que aporto | Si (*) | `<select>` con lista de EPS |
| Ha tenido incapacidad los ultimos 3 meses? | — | `input[type="radio"]` Si/No |
| Licencia de maternidad los ultimos 3 meses? | — | `input[type="radio"]` Si/No |

## Estrategia de automatizacion futura

Para automatizar esta verificacion, el bot necesitaria:

1. **Datos del afiliado** — nombre, apellido, email no los tiene el bot.
   Deben venir de una fuente externa (base de datos de empleados, archivo Excel, etc.)

2. **EPS** — se podria inferir de `fosiga` (ADRES) que ya devuelve la EPS del afiliado.
   Si `fosiga` se ejecuta primero, la EPS queda disponible para `simpleco`.

3. **Preguntas de Si/No** — "incapacidad" y "maternidad" pueden asumirse como "No"
   por defecto para la mayoria de casos.

4. **Flujo**: despues de llenar el form de preguntas, hay que hacer clic en un boton
   de envio (probablemente "Continuar" o "Validar") y luego el portal redirige
   al flujo normal de comprobantes.

## Estado actual

El bot detecta la pagina y corta con `ERROR_PREGUNTAS_SEGURIDAD` en ~1s.
No intenta llenar el formulario automaticamente porque no tiene los datos del afiliado.
