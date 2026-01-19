<?php
/**
 * Constantes Globales - ByBot v2.0
 * 
 * Define todas las constantes del sistema en un solo lugar
 */

// Cargar variables de entorno
require_once __DIR__ . '/env_loader.php';

// =============================================
// RUTAS DEL SISTEMA
// =============================================
define('BYBOT_ROOT', dirname(__DIR__));
define('BASE_DIR', BYBOT_ROOT); // Alias para compatibilidad
define('BYBOT_CONFIG', BYBOT_ROOT . '/config');
define('BYBOT_WEB', BYBOT_ROOT . '/web');
define('BYBOT_SERVICES', BYBOT_ROOT . '/services');
define('BYBOT_SHARED', BYBOT_ROOT . '/shared');
define('BYBOT_UPLOADS', BYBOT_ROOT . '/uploads');
define('BYBOT_LOGS', BYBOT_ROOT . '/logs');
define('BYBOT_ASSETS', BYBOT_ROOT . '/assets');

// =============================================
// CONFIGURACIÓN DE LA APLICACIÓN
// =============================================
define('APP_ENV', env('APP_ENV', 'development'));
define('APP_DEBUG', env('APP_DEBUG', 'true') === 'true');
define('APP_URL', env('APP_URL', 'https://bybjuridicos.andapps.cloud'));
define('APP_NAME', env('APP_NAME', 'ByBot'));

// =============================================
// ESTADOS DE PROCESO
// =============================================
class EstadoProceso {
    // Estados iniciales
    const CREADO = 'creado';
    
    // Estados de análisis
    const EN_COLA_ANALISIS = 'en_cola_analisis';
    const ANALIZANDO = 'analizando';
    const ANALIZADO = 'analizado';
    const ERROR_ANALISIS = 'error_analisis';
    
    // Estados de validación
    const VALIDADO = 'validado';
    
    // Estados de llenado
    const EN_COLA_LLENADO = 'en_cola_llenado';
    const LLENANDO = 'llenando';
    const COMPLETADO = 'completado';
    const ERROR_LLENADO = 'error_llenado';
    
    // Estados especiales
    const CANCELADO = 'cancelado';
    
    /**
     * Obtener todos los estados
     */
    public static function todos() {
        return [
            self::CREADO,
            self::EN_COLA_ANALISIS,
            self::ANALIZANDO,
            self::ANALIZADO,
            self::ERROR_ANALISIS,
            self::VALIDADO,
            self::EN_COLA_LLENADO,
            self::LLENANDO,
            self::COMPLETADO,
            self::ERROR_LLENADO,
            self::CANCELADO
        ];
    }
    
    /**
     * Obtener label para UI
     */
    public static function getLabel($estado) {
        $labels = [
            self::CREADO => 'Creado',
            self::EN_COLA_ANALISIS => 'En Cola de Análisis',
            self::ANALIZANDO => 'Analizando con IA',
            self::ANALIZADO => 'Analizado',
            self::ERROR_ANALISIS => 'Error en Análisis',
            self::VALIDADO => 'Validado',
            self::EN_COLA_LLENADO => 'En Cola de Llenado',
            self::LLENANDO => 'Llenando Pagaré',
            self::COMPLETADO => 'Completado',
            self::ERROR_LLENADO => 'Error en Llenado',
            self::CANCELADO => 'Cancelado'
        ];
        return $labels[$estado] ?? ucfirst($estado);
    }
    
    /**
     * Obtener clase CSS para badge
     */
    public static function getBadgeClass($estado) {
        $classes = [
            self::CREADO => 'bg-secondary',
            self::EN_COLA_ANALISIS => 'bg-info',
            self::ANALIZANDO => 'bg-info',
            self::ANALIZADO => 'bg-primary',
            self::ERROR_ANALISIS => 'bg-danger',
            self::VALIDADO => 'bg-success',
            self::EN_COLA_LLENADO => 'bg-warning text-dark',
            self::LLENANDO => 'bg-warning text-dark',
            self::COMPLETADO => 'bg-success',
            self::ERROR_LLENADO => 'bg-danger',
            self::CANCELADO => 'bg-dark'
        ];
        return $classes[$estado] ?? 'bg-secondary';
    }
    
    /**
     * Verificar si el estado permite edición
     */
    public static function permiteEdicion($estado) {
        return in_array($estado, [
            self::ANALIZADO,
            self::VALIDADO,
            self::ERROR_ANALISIS
        ]);
    }
    
    /**
     * Verificar si el estado permite cancelación
     */
    public static function permiteCancelacion($estado) {
        return in_array($estado, [
            self::CREADO,
            self::EN_COLA_ANALISIS,
            self::ANALIZADO,
            self::ERROR_ANALISIS,
            self::VALIDADO,
            self::EN_COLA_LLENADO,
            self::ERROR_LLENADO
        ]);
    }
}

// =============================================
// TIPOS DE PROCESO
// =============================================
class TipoProceso {
    const COBRANZA = 'cobranza';
    const DEMANDA = 'demanda';
    const OTRO = 'otro';
    
    public static function todos() {
        return [self::COBRANZA, self::DEMANDA, self::OTRO];
    }
    
    public static function getLabel($tipo) {
        $labels = [
            self::COBRANZA => 'Cobranza',
            self::DEMANDA => 'Demanda',
            self::OTRO => 'Otro'
        ];
        return $labels[$tipo] ?? ucfirst($tipo);
    }
}

// =============================================
// TIPOS DE ANEXO
// =============================================
class TipoAnexo {
    const ANEXO = 'anexo';
    const SOLICITUD_DEUDOR = 'solicitud_deudor';
    const SOLICITUD_CODEUDOR = 'solicitud_codeudor';
    const OTRO = 'otro';
    
    public static function todos() {
        return [self::ANEXO, self::SOLICITUD_DEUDOR, self::SOLICITUD_CODEUDOR, self::OTRO];
    }
}

// =============================================
// ROLES DE USUARIO
// =============================================
class RolUsuario {
    const ADMIN = 'admin';
    const SUPERVISOR = 'supervisor';
    const OPERADOR = 'operador';
    
    public static function todos() {
        return [self::ADMIN, self::SUPERVISOR, self::OPERADOR];
    }
    
    public static function getLabel($rol) {
        $labels = [
            self::ADMIN => 'Administrador',
            self::SUPERVISOR => 'Supervisor',
            self::OPERADOR => 'Operador'
        ];
        return $labels[$rol] ?? ucfirst($rol);
    }
}

// =============================================
// NIVELES DE LOG
// =============================================
class NivelLog {
    const DEBUG = 'debug';
    const INFO = 'info';
    const WARNING = 'warning';
    const ERROR = 'error';
    const CRITICAL = 'critical';
}

// =============================================
// COLAS DE TRABAJO
// =============================================
class Cola {
    const ANALYZE = 'bybot:analyze';
    const FILL = 'bybot:fill';
    const NOTIFY = 'bybot:notify';
    const RESULTS = 'bybot:results';
}

// =============================================
// ACCIONES DE HISTORIAL
// =============================================
class AccionHistorial {
    const CREADO = 'creado';
    const ESTADO_CAMBIADO = 'estado_cambiado';
    const ARCHIVOS_SUBIDOS = 'archivos_subidos';
    const ANALIZADO = 'analizado';
    const DATOS_EDITADOS = 'datos_editados';
    const VALIDADO = 'validado';
    const LLENADO = 'llenado';
    const ERROR = 'error';
    const NOTA_AGREGADA = 'nota_agregada';
    const CANCELADO = 'cancelado';
    const REINTENTADO = 'reintentado';
}

// =============================================
// CONFIGURACIÓN DE ARCHIVOS
// =============================================
define('MAX_FILE_SIZE_IMAGE', env('UPLOAD_MAX_SIZE_IMAGE', 5242880)); // 5MB
define('MAX_FILE_SIZE_PDF', env('UPLOAD_MAX_SIZE_PDF', 10485760)); // 10MB
define('ALLOWED_EXTENSIONS_IMAGE', ['jpg', 'jpeg', 'png']);
define('ALLOWED_EXTENSIONS_PDF', ['pdf']);
define('ALLOWED_EXTENSIONS_ALL', ['jpg', 'jpeg', 'png', 'pdf']);

// =============================================
// COLORES CORPORATIVOS
// =============================================
class ColoresCorporativos {
    const AZUL_PRIMARIO = '#55A5C8';
    const VERDE_SECUNDARIO = '#9AD082';
    const GRIS_TERCIARIO = '#B1BCBF';
    const AZUL_OSCURO = '#35719E';
    
    // Para ByBot específico (tema oscuro)
    const BYBOT_PRIMARY = '#003168';
    const BYBOT_SECONDARY = '#7D7D7D';
}

// =============================================
// CONFIGURACIÓN DE GEMINI
// =============================================
define('GEMINI_API_KEY', env('GEMINI_API_KEY', ''));
define('GEMINI_MODEL', env('GEMINI_MODEL', 'gemini-1.5-flash'));
define('GEMINI_TEMPERATURE', env('GEMINI_TEMPERATURE', 0.1));
define('GEMINI_MAX_TOKENS', env('GEMINI_MAX_TOKENS', 4000));

// =============================================
// CONFIGURACIÓN DE REDIS
// =============================================
define('REDIS_HOST', env('REDIS_HOST', 'localhost'));
define('REDIS_PORT', env('REDIS_PORT', 6379));
define('REDIS_PASSWORD', env('REDIS_PASSWORD', ''));
define('REDIS_DB', env('REDIS_DB', 0));

// =============================================
// CONFIGURACIÓN DE WEBSOCKET
// =============================================
define('WEBSOCKET_HOST', env('WEBSOCKET_HOST', 'localhost'));
define('WEBSOCKET_PORT', env('WEBSOCKET_PORT', 8765));
define('WEBSOCKET_ENABLED', env('WEBSOCKET_ENABLED', 'true') === 'true');

// =============================================
// CONFIGURACIÓN DE n8n
// =============================================
define('N8N_BASE_URL', env('N8N_BASE_URL', 'https://n8n.srv1083920.hstgr.cloud'));
define('N8N_WEBHOOK_URL', env('N8N_WEBHOOK_URL', N8N_BASE_URL . '/webhook'));
define('N8N_API_KEY', env('N8N_API_KEY', ''));
define('N8N_WEBHOOK_SECRET', env('N8N_WEBHOOK_SECRET', ''));
define('N8N_ACCESS_TOKEN', env('N8N_ACCESS_TOKEN', ''));
define('N8N_TIMEOUT', env('N8N_TIMEOUT', 30));

// =============================================
// CONFIGURACIÓN DE WORKERS (Legacy/Alternativo)
// =============================================
define('WORKER_API_TOKEN', env('WORKER_API_TOKEN', ''));
define('WORKER_POLL_INTERVAL', env('WORKER_POLL_INTERVAL', 5));
define('MAX_INTENTOS_ANALISIS', 3);
define('MAX_INTENTOS_LLENADO', 3);

// =============================================
// CONFIGURACIÓN DE PROCESAMIENTO
// =============================================
define('USE_N8N', env('USE_N8N', 'true') === 'true'); // true = n8n, false = workers directos

