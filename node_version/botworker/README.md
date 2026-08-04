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
- **`analizador.py`** — implementación concreta del análisis IA (usado por `daemon.py`).

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
cd /opt/lampp/htdocs/projects/bybot_v1/node_version/botworker
.venv/bin/python daemon.py          # análisis IA
.venv/bin/python bot_runner.py      # consultas bot
```

## Estructura
```
botworker/
├── README.md
├── requirements.txt
├── analizador.py        # análisis IA Gemini por proceso_id
├── daemon.py            # daemon/supervisor de análisis
├── bot_runner.py        # consumidor de cola 'bybot:consultar' (BOT_REGISTRY)
└── shared/
    ├── config.py
    └── utils.py
```

## Cola (app_colas_trabajos)
- `cola = 'bybot:consultar'`, estados `pendiente → procesando → completado/error`.
- `BOT_REGISTRY` (en `bot_runner.py`) mapea `nombre_bot -> (módulo service, tabla consultas)`.