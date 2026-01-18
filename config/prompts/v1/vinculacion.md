# Prompt: Identificación de Solicitudes de Vinculación
## Versión: v1

Analiza estos documentos anexos e identifica las páginas que contienen las solicitudes de vinculación.

Una solicitud de vinculación típicamente:
- Tiene un título como "SOLICITUD DE VINCULACIÓN", "FORMULARIO DE VINCULACIÓN", "SOLICITUD DE ASOCIACIÓN"
- Contiene datos personales del solicitante (nombres, apellidos, identificación, etc.)
- Suele ser 2 páginas consecutivas para cada persona
- La solicitud del DEUDOR/SOLICITANTE es la persona principal del crédito
- Hay un campo que suele estar marcado con una X o un Check junto a la palabra "solicitante"
- La solicitud del CODEUDOR es la persona que garantiza el crédito (puede no existir)
- Hay un campo que suele estar marcado con una X o un Check junto a la palabra "codeudor"

Responde SOLO con un JSON válido en este formato:

```json
{
    "deudor": {
        "archivo_index": número del índice del archivo (0-based),
        "paginas": [número_pagina_1, número_pagina_2]
    },
    "codeudor": {
        "archivo_index": número del índice del archivo (0-based),
        "paginas": [número_pagina_1, número_pagina_2]
    }
}
```

## INSTRUCCIONES CRÍTICAS:

1. **Números de página son 1-based**: la primera página es 1, no 0
2. **archivo_index es 0-based**: 0 = primer archivo, 1 = segundo archivo, etc.
3. Si solo hay 1 archivo, TODOS los archivo_index deben ser 0
4. Si no encuentras la solicitud del deudor, usa null para deudor
5. Si no hay codeudor o no encuentras su solicitud, usa null para codeudor
6. Las páginas deben ser consecutivas (ej: [3, 4] o [5, 6])

## EJEMPLO DE RESPUESTA:

```json
{
    "deudor": {
        "archivo_index": 0,
        "paginas": [1, 2]
    },
    "codeudor": {
        "archivo_index": 0,
        "paginas": [3, 4]
    }
}
```

Responde SOLO con el JSON válido, sin texto adicional, sin explicaciones.

