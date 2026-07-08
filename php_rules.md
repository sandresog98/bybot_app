Parámetros y reglas de programación:

1 . Php y diseño
1 . 1 . Debemos utilizar bootstrap para el diseño.
1 . 2 . La versión de php es 8.2.28.
1 . 3 . El diseño debe ser muy amigable y lindo.
1 . 4 . Los colores corporativos son:
* Azul #003268
* Gris #7D7D7D
* Azul 2 #1D4191
No es necesario que estos colores sean los únicos de la página, pero es bueno que el diseño los tenga en detalles de la misma.
1 . 5 . La fuente de letra que utiliza es Poppins ExtraBold.

2 . Almacenamiento
2 . 1 . Si se sube una imagen debemos tener límite de peso editable en las variables de entorno.
2 . 2 . Si se sube un archivo PDF debemos tener límite de peso editable en las variables de entorno.
2 . 3 . Al almacenar archivos debemos utilizar el cambio de nombre para que no se reemplacen en el servidor.
Por ejemplo: Si el archivo es una identificación “identificacion” + codigo + llave unica + formato. 
2 . 4 . Para visualizar utilizar un api y que no sea visible la ruta del archivo.

3 . Base de datos
3 . 1 . La base de datos utilizada es 11.8.3-MariaDB-log.
3 . 2 . Se utilizan llaves foráneas entre tablas para la base de datos.
3 . 3 . Mantengamos un archivo ddl.sql con la query para crear la base de datos en un futuro por si deseamos crearla de nuevo y un archivo reset_db.sql que nos permite reiniciar la base de datos. Al mismo tiempo una carpeta migrations/ para almacenar .sql de ajustes posteriores.
3 . 4 . Al crear tablas es importante especificar para qué módulo se usarán en el nombre por ejemplo en el módulo de cuestionarios: cuestionarios_preguntas, cuestionarios_opciones y etc. en el módulo tienda: tienda_productos, tienda_ventas, tienda_inventario, etc.
3 . 5 . No utilizar columnas enum, solo aseguremos de dejar comentados los valores posibles como guia.

4 . Experiencia de usuario
4 . 1 . En algunas etapas solicitamos que carguen bases de datos en excel, siempre que esto ocurra debemos tener un botón en el cual descargan la plantilla que funciona para cargar.

5 . Estructura del proyecto
5 . 1 . El proyecto se divide en interfaces de acuerdo a las necesidades (por ejemplo: administrador o UI, usuario o CX, etc.) y cada interfaz tiene sus módulos (Por ejemplo: UI puede tener Usuarios, Oficina, Boletería, etc.).
5 . 2 . Cada módulo cuenta con sus propias carpetas api, models, pages, utils.
5 . 3 . La estructura esperada del proyecto es:
proyecto/
├── interfaz_1/                  # Interfaz
│   ├── modules/                 # Módulos funcionales
│   │   ├── module_1/            # Módulos ejemplo
│   │   │   ├── api/             # Apis
│   │   │   ├── models/          # Modelos
│   │   │   ├── pages/           # Paginas
│   │   │   └── utils/           # Utilidades generales del modulo
│   │   └── module_2/            # Módulos ejemplo
│   ├── controllers/             # Controladores de autenticación
│   ├── views/                   # Plantillas y layouts
│   ├── config/                   # Configuración del módulo
│   │   └── paths.php             # Gestión de rutas y URLs
│   ├── views/                    # Vistas compartidas
│   │   └── layouts/              # Layouts reutilizables
│   │        ├── header.php       # Encabezado común
│   │        ├── footer.php       # Pie de página común
│   │        └── sidebar.php      # Barra lateral de navegación
│   ├── controllers/              # Controladores
│   │    └── AuthController.php   # Controlador de autenticación
│   ├── index.php                 # Punto de entrada
│   ├── login.php                 # Página de inicio de sesión
│   └── logout.php                # Cierre de sesión
├── utils/			    # Utilidades generales del app
│    ├── PhpMailer/	 	    # Funciones para envío de mails
│    ├── vendor/		    # Funciones para crear PDFs
│    │     └── tcpdf/	           # Tcpdf
│    └── ExcelGenerator.php	   # Generador de excel (.xlsx)
├── assets/                   # Recursos estáticos
│   ├── css/                  # Hojas de estilo
│   │   ├── common.css        # Estilos globales
│   ├── js/                   # Scripts JavaScript
│   │   └── common.js         # Funciones comunes
│   ├── img/                  # Imágenes
│   ├── favicons/             # Iconos
│   └── plantillas/           # Plantillas descargables (CSV, etc.)
├── sql/			# Códigos de base de datos
│    ├── ddl.sql		# Creación de BD
│    └── reset_db.php	        # Reiniciar BD
├── uploads/               	# Archivos subidos temporalmente
├── roles.json			# Archivo con roles y permisos
├── .env			# Variables de entorno




6 . Seguridad
6 . 1 . Importante el manejo de roles en un json donde definimos el nombre del rol y los módulos a los cuales tiene acceso.
6 . 2 . Recuerda que debemos manejar credenciales en un archivo .env.
6 . 3 . Los archivos en la carpeta uploads no deben poder ser vistos con la url a menos de que ya hayan hecho un login. Esto es para prevenir que si se filtra una url de un archivo no puedan acceder al mismo.
6 . 4 . Es mejor hacer un enlace tipo api para consultar las imágenes o archivos, que jamás sea visible su ubicación en el servidor.
6.5. Para los login de usuarios es bueno mantener nombres de usuario con una contraseña de un solo uso, que es cambiada al ingresar por primera vez, lo mismo al resetear la contraseña.

7 . Servicio
7 . 1 . Hay que diseñar esta app o página web pensando a futuro como una aplicación móvil. Donde vamos a requerir utilizar apis o servicios que hagan la misma función que esta aplicación.
