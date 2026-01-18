# Prompt: Análisis de Anexos
## Versión: v1

Analiza estos documentos anexos y extrae la siguiente información en formato JSON:

```json
{
    "deudor": {
        "tipo_identificacion": "CC, CE, NIT, etc. o null",
        "numero_identificacion": "string o null",
        "nombres": "string o null",
        "apellidos": "string o null",
        "fecha_expedicion_cedula": "YYYY-MM-DD o null",
        "fecha_nacimiento": "YYYY-MM-DD o null",
        "telefono": "string o null",
        "direccion": "string o null",
        "correo": "string o null"
    },
    "codeudor": {
        "tipo_identificacion": "CC, CE, NIT, etc. o null",
        "numero_identificacion": "string o null",
        "nombres": "string o null",
        "apellidos": "string o null",
        "fecha_expedicion_cedula": "YYYY-MM-DD o null",
        "fecha_nacimiento": "YYYY-MM-DD o null",
        "telefono": "string o null",
        "direccion": "string o null",
        "correo": "string o null"
    },
    "tasa_interes_efectiva_anual": número decimal (porcentaje) o null
}
```

## INSTRUCCIONES ESPECÍFICAS:

### Deudor/Solicitante
- Es la persona principal del crédito
- Busca campos marcados como "Solicitante", "Deudor", "Titular"

### Codeudor
- Es la persona que garantiza el crédito (puede no existir)
- Busca campos marcados como "Codeudor", "Garante", "Fiador"
- Si no hay codeudor, devuelve null para todo el objeto codeudor

### Tasa de Interés Efectiva Anual (TEA)
- Busca EXHAUSTIVAMENTE en TODOS los documentos anexos
- Términos: "TEA", "T.E.A.", "Tasa Efectiva Anual", "Tasa de Interés Efectiva Anual"
- Busca porcentajes que puedan ser tasas de interés
- Revisa tablas, encabezados, pies de página, condiciones del crédito
- El valor debe ser un número decimal (ejemplo: 15.5 para 15.5% anual)

## IMPORTANTE:
- Si no encuentras algún dato, usa null
- Las fechas deben estar en formato YYYY-MM-DD
- Responde SOLO con el JSON válido, sin texto adicional, sin explicaciones

