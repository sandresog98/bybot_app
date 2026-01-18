# Prompt: Análisis de Estado de Cuenta
## Versión: v1

Analiza este documento de estado de cuenta bancario y extrae la siguiente información en formato JSON:

```json
{
    "fecha_causacion": "YYYY-MM-DD o null si no se encuentra",
    "saldo_capital": número decimal o null,
    "saldo_interes": número decimal o null,
    "saldo_mora": número decimal o null,
    "tasa_interes_efectiva_anual": número decimal (porcentaje) o null
}
```

## INSTRUCCIONES ESPECÍFICAS:

### 1. Fecha de Causación
- Busca la ÚLTIMA fecha en la que la persona realizó un pago y toma la fecha del movimiento siguiente
- Revisa movimientos, pagos, abonos o transacciones recientes
- Es de suma importancia analizar el valor Capital-Abono e Intereses-Abono
- El último movimiento que tenga alguna o las dos con un valor mayor a cero es la última fecha de pago
- La fecha de causación es el movimiento siguiente a ese último pago
- Ejemplo: si el último pago fue el 10/12/2025 y el siguiente movimiento es el 11/12/2025, la fecha de causación es 11/12/2025
- Guía: La fecha de causación suele tener el valor "CAUSACION DE MORA Y REINTEGROS" en el campo Descripción Movimiento

### 2. Saldo Capital
- Busca el saldo de capital, capital pendiente, saldo principal o monto del crédito
- Puede aparecer como "Capital", "Principal", "Saldo Capital"

### 3. Saldo Interés
- Busca intereses pendientes, intereses causados, intereses a pagar
- Puede aparecer como "Intereses", "Interés Causado", "Interés Pendiente"

### 4. Saldo Mora
- Busca mora, intereses de mora, recargos por mora, intereses moratorios
- Puede aparecer como "Mora", "Interés de Mora", "Recargo por Mora"

### 5. Tasa de Interés Efectiva Anual (TEA)
- Busca términos como: "TEA", "T.E.A.", "Tasa Efectiva Anual", "Tasa de Interés Efectiva Anual"
- Busca porcentajes que puedan ser tasas de interés (números seguidos de %)
- Revisa tablas, encabezados, pies de página, condiciones del crédito
- Busca en secciones como "Condiciones", "Términos", "Información del Crédito"
- El valor debe ser un número decimal (ejemplo: 15.5 para 15.5% anual)
- Si encuentras una tasa mensual, multiplícala por 12 para obtener la anual

## IMPORTANTE:
- Revisa TODO el documento, no solo la primera página
- La TEA es CRÍTICA, busca en todas las secciones posibles
- Si no encuentras algún dato después de revisar exhaustivamente, usa null
- Responde SOLO con el JSON válido, sin texto adicional, sin explicaciones

