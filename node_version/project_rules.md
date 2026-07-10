Parámetros y reglas de programación:

1 . Stack y diseño
1 . 1 . Debemos utilizar Bootstrap para el diseño.
1 . 2 . El stack backend es Node.js 18+ con TypeScript y Fastify.
1 . 3 . El frontend es una SPA React + Vite + TypeScript.
1 . 4 . El diseño debe ser muy amigable y lindo.
1 . 5 . Los colores corporativos son:
* Azul #003268
* Gris #7D7D7D
* Azul 2 #1D4191
No es necesario que estos colores sean los únicos de la página, pero es bueno que el diseño los tenga en detalles de la misma.
1 . 6 . La fuente de letra que utiliza es Poppins ExtraBold.
1 . 7 . El proyecto se organiza como un monorepo npm workspaces con tres servicios: backend/, frontend/ y botstorage/. El ORM de base de datos es Prisma. La validación de entrada/salida se hace con zod. Los logs con Pino.

2 . Almacenamiento de archivos
2 . 1 . Si se sube una imagen debemos tener límite de peso editable en las variables de entorno.
2 . 2 . Si se sube un archivo PDF debemos tener límite de peso editable en las variables de entorno.
2 . 3 . Al almacenar archivos debemos utilizar el cambio de nombre para que no se reemplacen en el servidor.
Por ejemplo: Si el archivo es una identificación "identificacion" + codigo + llave_unica + formato.
2 . 4 . Para visualizar utilizar un endpoint de la API y que no sea visible la ruta del archivo.
2 . 5 . El almacenamiento vive en el servicio botstorage/, abstraído mediante una interfaz Storage con dos implementaciones: LocalStorage (por defecto) y RemoteStorage (placeholder para S3/B2/R2). El driver se selecciona con la variable de entorno STORAGE_DRIVER (local|remote) y es intercambiable sin tocar el backend ni el frontend.

3 . Base de datos
3 . 1 . La base de datos utilizada es MariaDB (XAMPP) compatible MySQL 8.
3 . 2 . Se utilizan llaves foráneas entre tablas para la base de datos.
3 . 3 . Mantengamos un archivo ddl.sql con la query para crear la base de datos en un futuro por si deseamos crearla de nuevo y un archivo reset_db.sql que nos permite reiniciar la base de datos. Al mismo tiempo una carpeta migrations/ para almacenar .sql de ajustes posteriores.
3 . 4 . Al crear tablas es importante especificar para qué módulo se usarán en el nombre por ejemplo en el módulo de cuestionarios: cuestionarios_preguntas, cuestionarios_opciones y etc. en el módulo tienda: tienda_productos, tienda_ventas, tienda_inventario, etc.
3 . 5 . No utilizar columnas enum, solo aseguremos de dejar comentados los valores posibles como guia.
3 . 6 . Prisma no gestiona las migraciones (las hace sql/). Prisma se usa solo como cliente tipado que espeja las tablas existentes (schema.prisma con @map).

4 . Experiencia de usuario
4 . 1 . En algunas etapas solicitamos que carguen bases de datos en excel, siempre que esto ocurra debemos tener un botón en el cual descargan la plantilla que funciona para cargar.

5 . Estructura del proyecto
5 . 1 . El proyecto se divide en servicios independientes (backend, frontend, botstorage) y cada servicio tiene módulos funcionales (por ejemplo: auth, procesos, analisis, prompts, usuarios, configuracion).
5 . 2 . Cada módulo del backend cuenta con sus propios archivos: <modulo>.routes.ts (rutas Fastify), <modulo>.service.ts (lógica de negocio), <modulo>.schema.ts (esquemas zod). El frontend tiene pages/<Modulo>.tsx y api queries asociadas.
5 . 3 . La estructura esperada del proyecto es:
proyecto/
├── backend/                       # Servicio API REST (Fastify + Prisma + TS)
│   ├── src/
│   │   ├── server.ts              # Punto de entrada Fastify
│   │   ├── config/
│   │   │   └── env.ts             # dotenv + zod
│   │   ├── core/                  # Núcleo: auth, db, logger, queue, pythonInvoker, roles, storageClient
│   │   ├── plugins/               # Plugins Fastify (auth, errorHandler)
│   │   ├── modules/               # Módulos funcionales
│   │   │   ├── auth/
│   │   │   │   ├── auth.routes.ts
│   │   │   │   ├── auth.service.ts
│   │   │   │   └── auth.schema.ts
│   │   │   ├── procesos/
│   │   │   ├── analisis/
│   │   │   ├── prompts/
│   │   │   ├── usuarios/
│   │   │   └── configuracion/
│   │   └── utils/
│   ├── prisma/
│   │   └── schema.prisma          # Espejo tipado de las tablas (no migrations)
│   ├── package.json
│   └── tsconfig.json
├── frontend/                      # SPA (React + Vite + TS)
│   ├── src/
│   │   ├── main.tsx               # Punto de entrada React
│   │   ├── App.tsx                # Router + guards de auth
│   │   ├── api/                   # Cliente axios + TanStack Query hooks
│   │   │   ├── client.ts
│   │   │   ├── queries.ts
│   │   │   └── types.ts
│   │   ├── auth/                  # AuthContext + useAuth
│   │   ├── layouts/               # AdminLayout, Sidebar, Header
│   │   ├── pages/                 # Login, ChangePassword, Dashboard, Procesos, ...
│   │   └── styles/                # variables.css (paleta), common.css
│   ├── public/
│   │   └── favicons/
│   ├── package.json
│   ├── vite.config.ts
│   └── tsconfig.json
├── botstorage/                    # Microservicio de archivos (Fastify + TS)
│   ├── src/
│   │   ├── server.ts
│   │   ├── config/env.ts
│   │   ├── storage/               # Storage, LocalStorage, RemoteStorage
│   │   ├── routes/                # /internal/store|read|delete
│   │   └── plugins/               # internalAuth (X-Internal-Token)
│   ├── package.json
│   └── tsconfig.json
├── botworker/                     # Python IA (Gemini) — invocado por backend via child_process
├── bots/                          # Bots Python de registros públicos (Playwright)
├── bots/                          # Legacy Windows, congelado
├── sql/                           # ddl.sql + reset_db.sql + migrations/
├── uploads/                       # Storage local (servido solo por API)
├── logs/
├── docs/                          # Plan y documentación
├── package.json                   # Monorepo raíz con workspaces
├── roles.json                     # Roles -> módulos permitidos
├── .env                           # Variables de entorno únicas
├── .env.example
└── project_rules.md               # Este archivo

6 . Seguridad
6 . 1 . Importante el manejo de roles en un json (roles.json) donde definimos el nombre del rol y los módulos a los cuales tiene acceso. El backend lo lee y emite los módulos como claim del JWT; el frontend filtra el sidebar y las rutas por esos módulos.
6 . 2 . Recuerda que debemos manejar credenciales en un archivo .env. Nunca commit secrets.
6 . 3 . Los archivos en la carpeta uploads no deben poder ser vistos con la URL a menos de que ya hayan hecho un login. Esto es para prevenir que si se filtra una url de un archivo no puedan acceder al mismo. El backend sirve el archivo a través de botstorage por un endpoint que requiere autorización; nunca se expone la ruta de disco.
6 . 4 . Es mejor hacer un enlace tipo API para consultar las imágenes o archivos, que jamás sea visible su ubicación en el servidor.
6 . 5 . Para los login de usuarios es bueno mantener nombres de usuario con una contraseña de un solo uso, que es cambiada al ingresar por primera vez, lo mismo al resetear la contraseña. La autenticación usa JWT (access 15m + refresh 7d). Los refresh tokens se guardan hasheados en la tabla control_sesiones.
6 . 6 . La comunicación entre backend y botstorage usa un token interno compartido en .env (BOTSTORAGE_INTERNAL_TOKEN == BACKEND_BOTSTORAGE_TOKEN), nunca expuesto al navegador.

7 . Servicio
7 . 1 . Hay que diseñar esta app o página web pensando a futuro como una aplicación móvil. Donde vamos a requerir utilizar apis o servicios que hagan la misma función que esta aplicación. Toda la lógica de negocio vive en el backend (REST /api/v1/*); el frontend es solo presentación y no implementa reglas de negocio.
7 . 2 . Los scripts Python (botworker para IA y bots para bots de registros) se invocan desde el backend via child_process con timeout configurable, nunca directamente desde el frontend.