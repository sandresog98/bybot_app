<?php
/**
 * API: Validar Datos IA - ByBot App
 */

header('Content-Type: application/json');

require_once '../../../controllers/AuthController.php';
require_once '../../../config/paths.php';
require_once '../models/CrearCoop.php';

$auth = new AuthController();
$auth->requireAuth();
$auth->requireModule('crear_coop.procesos');

$currentUser = $auth->getCurrentUser();

// Solo aceptar POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// Obtener datos JSON
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!isset($data['proceso_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit;
}

$procesoId = (int)$data['proceso_id'];
$accion = $data['accion'] ?? 'guardar'; // 'guardar' o 'marcar_validado'
$datos = $data['datos'] ?? [];

try {
    $crearCoopModel = new CrearCoop();
    
    // Validar que el proceso existe
    $proceso = $crearCoopModel->obtenerProceso($procesoId);
    if (!$proceso) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Proceso no encontrado']);
        exit;
    }
    
    // Validar que el proceso está en estado correcto
    if (!in_array($proceso['estado'], ['analizado_con_ia', 'informacion_ia_validada'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'El proceso no está en estado válido para validación']);
        exit;
    }
    
    if ($accion === 'marcar_validado') {
        // Solo cambiar el estado a "informacion_ia_validada"
        if ($proceso['estado'] !== 'analizado_con_ia') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Solo se puede marcar como validado si está en estado "Analizado con IA"']);
            exit;
        }
        
        $crearCoopModel->actualizarEstado($procesoId, 'informacion_ia_validada');
        
        echo json_encode([
            'success' => true,
            'message' => 'Proceso marcado como validado exitosamente'
        ]);
    } else {
        // Acción 'guardar': solo guardar datos validados sin cambiar estado
        if (empty($datos)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'No se proporcionaron datos para guardar']);
            exit;
        }
        
        // Guardar validación (sin cambiar estado)
        $resultado = $crearCoopModel->validarDatosIA($procesoId, $datos, $currentUser['id'], false);
        
        if ($resultado) {
            echo json_encode([
                'success' => true,
                'message' => 'Cambios guardados exitosamente'
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error al guardar los cambios'
            ]);
        }
    }
} catch (Exception $e) {
    error_log('Error en validar_datos_ia.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor'
    ]);
}

