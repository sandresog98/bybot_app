# botworker — Worker Python para IA (Gemini)

Rutinas Python ejecutadas por el **backend Node** vía `child_process` (`backend/src/core/pythonInvoker.ts`).

Se mantienen aquí (y no en `backend/`) porque la librería `google-generativeai` es Python-native, y porque ya existe `bots/` (bots de registros públicos) en el mismo runtime; el backend los invoca igual.

## Estado
- **Fase 0b**: carpeta placeholder (vacía excepto este README).
- **Fase 2**: se migrará aquí `bybot_app/n8n/scripts/analyzer/` (cliente Gemini + prompts) y `shared/` (config/utils), adaptados para:
  - Leer el `proceso_id` por CLI (`--proceso_id=NN`).
  - Leer archivos desde `uploads/` (vía path local, no descarga HTTP).
  - Escribir resultados directo a `procesos_datos_ia` en MariaDB.
  - Sin n8n, sin callbacks HTTP — solo stdout JSON.

## Estructura futura (Fase 2)
```
botworker/
├── README.md
├── requirements.txt
├── .env.example
├── analizador.py
├── llenador.py            # descartado por ahora (sin generación de PDFs)
└── shared/
    ├── config.py
    └── utils.py
```

## Comunicación con backend (Fase 2)
```bash
python3 botworker/analizador.py --proceso_id=123
# → lee procesos_archivos en BD, descarga/abre de uploads/, llama Gemini,
# → escribe procesos_datos_ia con datos_originales + metadata,
# → imprime JSON en stdout ({success:true, datos_ia_id:NN}).
```