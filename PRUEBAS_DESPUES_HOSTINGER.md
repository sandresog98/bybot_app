# Pruebas Después de la Respuesta de Hostinger

## URLs a Probar

Después de que Hostinger responda o haga cambios, prueba estas URLs en el navegador:

### 1. URL sin barra diagonal (debe funcionar):
```
https://bybjuridicos.andapps.cloud/web/api/v1/procesos?estado=analizado&per_page=5
```

### 2. URL con barra diagonal (la que está fallando):
```
https://bybjuridicos.andapps.cloud/web/api/v1/procesos/?estado=analizado&per_page=5
```

### 3. Otras URLs de la API para verificar:
```
https://bybjuridicos.andapps.cloud/web/api/v1/stats/dashboard
https://bybjuridicos.andapps.cloud/web/api/v1/colas/estado
https://bybjuridicos.andapps.cloud/web/api/v1/stats/actividad?limit=8
```

## Cómo Probar

1. Abre el navegador
2. Pega la URL en la barra de direcciones
3. Presiona Enter
4. **Si funciona**: Deberías ver una respuesta JSON (no HTML)
5. **Si da 403**: Verás la página HTML de error de LiteSpeed

## Qué Esperar

- ✅ **Funciona**: Respuesta JSON con datos
- ❌ **No funciona**: Página HTML con "403 Forbidden"

## Después de Probar

Si después de que Hostinger haga cambios las URLs siguen dando 403, comparte con ellos:
- Qué URLs probaste
- Qué respuesta obtuviste
- Si alguna URL funciona y cuál no

