# botworker — Worker Python (IA Gemini + Bot runner)

Daemons Python que atienden la cola de trabajo del backend. El backend Node escribe a la
tabla `app_colas_trabajos` y estos procesos la consumen de forma asíncrona.

## Procesos
- **`daemon.py`** — análisis IA. Toma `proceso_id`, lee archivos desde `botstorage/uploads/`,
  llama **Gemini** (`gemini-2.5-flash`) y escribe el resultado en `procesos_datos_ia`.
  Log: `/tmp/bydaemon.log`.
- **`bot_runner.py`** — consultas bot. Hace `claim_next()` sobre la cola
  `'bybot:consultar'`, resuelve el bot en `BOT_REGISTRY` y ejecuta `bots/<name>/service.py`.
  Escribe en `processes`. Log: `/tmp/bybotrunner.log`.
- **`analizador.py`** — análisis IA (usado por `daemon.py`). **Multi-entidad (F4)**: agrupa los
  archivos del proceso por categoría lógica, elige el prompt (específico de la entidad si existe,
  si no el global — `utils.get_prompts_activos(conn, entidad_id)`), hace una llamada Gemini por
  categoría y fusiona el resultado canónico. Reintenta ante 429/503 y repara JSON truncado.
  Guarda `tokens_entrada`/`tokens_salida` (la salida incluye *thinking tokens*).
- **`shared/documentos.py`** — normaliza formatos para Gemini: **TIFF→PDF** con Pillow
  (con tope anti *image-bomb*); PDF/PNG/JPEG pasan tal cual.

## Config IA (`.env`, leída por `shared/config.py`)
`GEMINI_MODEL`, `GEMINI_MAX_TOKENS` (26000, evita truncar amortizaciones largas),
`GEMINI_THINKING_BUDGET` (**0 = thinking off** para extracción; -1 = auto; >0 = fijo).
Tras editar `analizador.py` o cambiar la key/config, **reiniciar el daemon**.

## Estado
- Funcional. Reemplazó la Fase 0b (placeholder). El backend ya no invoca Python por
  `child_process`; usa la cola de la BD.

## Requisitos
```bash
source .venv/bin/activate
pip install -r requirements.txt
```

## Uso (dev / manual)
```bash
# desde node_version/ (macOS no tiene setsid → usar nohup)
nohup ./botworker/.venv/bin/python botworker/daemon.py     > /tmp/bydaemon.log    2>&1 &  # análisis IA
nohup ./botworker/.venv/bin/python botworker/bot_runner.py > /tmp/bybotrunner.log 2>&1 &  # consultas bot
```

## Estructura
```
botworker/
├── README.md
├── requirements.txt
├── analizador.py        # análisis IA Gemini multi-entidad por proceso_id
├── daemon.py            # daemon/supervisor de análisis
├── bot_runner.py        # consumidor de cola 'bybot:consultar' (BOT_REGISTRY)
└── shared/
    ├── config.py        # variables de entorno (Gemini, DB, thinking budget)
    ├── documentos.py    # normalización de formato (TIFF→PDF)
    └── utils.py
```

## Cola (app_colas_trabajos)
- `cola = 'bybot:consultar'`, estados `pendiente → procesando → completado/error`.
- `BOT_REGISTRY` (en `bot_runner.py`) mapea `nombre_bot -> (módulo service, tabla consultas)`.