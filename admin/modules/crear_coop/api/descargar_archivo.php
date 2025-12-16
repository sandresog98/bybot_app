<?php
/**
 * API: Descargar archivo - ByBot App
 * Método similar a we_are_app para servir archivos de forma segura
 */

ob_start();

require_once '../../../controllers/AuthController.php';
require_once '../../../config/paths.php';

$auth = new AuthController();
$auth->requireAuth();

$fileId = $_GET['id'] ?? '';
$tipo = $_GET['tipo'] ?? ''; // 'pagare', 'estado_cuenta', 'anexo'
$procesoId = (int)($_GET['proceso_id'] ?? 0);
$ver = isset($_GET['ver']) && $_GET['ver'] == '1'; // Si es true, mostrar en navegador; si es false, descargar

if (empty($fileId) && empty($tipo) && $procesoId <= 0) {
    ob_end_clean();
    http_response_code(400);
    echo 'Parámetros inválidos';
    exit;
}

require_once __DIR__ . '/../../../../config/database.php';
require_once '../models/CrearCoop.php';

$crearCoopModel = new CrearCoop();
$proceso = null;
$archivoPath = null;
$nombreArchivo = 'archivo.pdf';

if ($procesoId > 0) {
    $proceso = $crearCoopModel->obtenerProceso($procesoId);
    
    if (!$proceso) {
        ob_end_clean();
        http_response_code(404);
        echo 'Proceso no encontrado';
        exit;
    }
    
    // Determinar qué archivo descargar según el tipo
    switch ($tipo) {
        case 'pagare':
            $archivoPath = $proceso['archivo_pagare_original'] ?? null;
            $nombreArchivo = 'pagare_' . $proceso['codigo'] . '.pdf';
            break;
        case 'estado_cuenta':
            $archivoPath = $proceso['archivo_estado_cuenta'] ?? null;
            $nombreArchivo = 'estado_cuenta_' . $proceso['codigo'] . '.pdf';
            break;
        case 'anexo':
            if (!empty($fileId)) {
                $anexos = $crearCoopModel->obtenerAnexos($procesoId);
                foreach ($anexos as $anexo) {
                    if ($anexo['id'] == $fileId) {
                        $archivoPath = $anexo['ruta_archivo'];
                        $nombreArchivo = $anexo['nombre_archivo'];
                        break;
                    }
                }
            }
            break;
    }
}

// Validar que el archivo existe y está dentro del directorio permitido
if (empty($archivoPath) || !file_exists($archivoPath)) {
    ob_end_clean();
    http_response_code(404);
    echo 'Archivo no encontrado';
    exit;
}

// Validar que el archivo está dentro del directorio uploads
$uploadsBase = realpath(__DIR__ . '/../../../../uploads/');
$archivoReal = realpath($archivoPath);

if (!$archivoReal || strpos($archivoReal, $uploadsBase) !== 0) {
    ob_end_clean();
    http_response_code(403);
    echo 'Acceso denegado';
    exit;
}

// Limpiar buffer
ob_end_clean();

// Determinar tipo MIME
$extension = strtolower(pathinfo($archivoPath, PATHINFO_EXTENSION));
$mimeTypes = [
    'pdf' => 'application/pdf',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png'
];
$mimeType = $mimeTypes[$extension] ?? 'application/octet-stream';

// Headers para descarga o visualización
header('Content-Type: ' . $mimeType);
header('Content-Length: ' . filesize($archivoPath));
if ($ver) {
    // Mostrar en el navegador (inline)
    header('Content-Disposition: inline; filename="' . $nombreArchivo . '"');
} else {
    // Forzar descarga (attachment)
    header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
}
header('Pragma: no-cache');
header('Expires: 0');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');

// Enviar archivo
readfile($archivoPath);
exit;

