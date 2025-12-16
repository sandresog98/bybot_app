<?php
/**
 * API: Servir archivo para el Bot - ByBot App
 * Endpoint seguro para que el bot Python descargue archivos del servidor
 */

header('Content-Type: application/json');

// Validar token de API
$apiToken = $_SERVER['HTTP_X_API_TOKEN'] ?? $_GET['token'] ?? '';

if (empty($apiToken)) {
    http_response_code(401);
    echo json_encode(['error' => 'Token de API requerido']);
    exit;
}

// Cargar configuración
require_once __DIR__ . '/../../../../config/env_loader.php';
loadEnv();

$expectedToken = getenv('BOT_API_TOKEN');
if (empty($expectedToken) || $apiToken !== $expectedToken) {
    http_response_code(403);
    echo json_encode(['error' => 'Token de API inválido']);
    exit;
}

// Validar parámetros
$procesoId = (int)($_GET['proceso_id'] ?? 0);
$tipo = $_GET['tipo'] ?? ''; // 'pagare', 'estado_cuenta', 'anexo'
$anexoId = (int)($_GET['anexo_id'] ?? 0);

if ($procesoId <= 0 || empty($tipo)) {
    http_response_code(400);
    echo json_encode(['error' => 'Parámetros inválidos: proceso_id y tipo son requeridos']);
    exit;
}

require_once __DIR__ . '/../../../../config/database.php';
require_once '../models/CrearCoop.php';

$crearCoopModel = new CrearCoop();
$proceso = $crearCoopModel->obtenerProceso($procesoId);

if (!$proceso) {
    http_response_code(404);
    echo json_encode(['error' => 'Proceso no encontrado']);
    exit;
}

// Convertir rutas relativas de BD a absolutas
$docRoot = $_SERVER['DOCUMENT_ROOT'] ?? '/opt/lampp/htdocs';
$archivoPath = null;
$nombreArchivo = 'archivo.pdf';

switch ($tipo) {
    case 'pagare':
        $rutaRelativa = $proceso['archivo_pagare_original'] ?? null;
        if ($rutaRelativa) {
            $archivoPath = $docRoot . $rutaRelativa;
            $nombreArchivo = 'pagare_' . $proceso['codigo'] . '.pdf';
        }
        break;
        
    case 'estado_cuenta':
        $rutaRelativa = $proceso['archivo_estado_cuenta'] ?? null;
        if ($rutaRelativa) {
            $archivoPath = $docRoot . $rutaRelativa;
            $nombreArchivo = 'estado_cuenta_' . $proceso['codigo'] . '.pdf';
        }
        break;
        
    case 'anexo':
        if ($anexoId <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'anexo_id es requerido para tipo anexo']);
            exit;
        }
        
        $anexo = $crearCoopModel->obtenerAnexoPorId($anexoId);
        if (!$anexo || $anexo['proceso_id'] != $procesoId) {
            http_response_code(404);
            echo json_encode(['error' => 'Anexo no encontrado']);
            exit;
        }
        
        $rutaRelativa = $anexo['ruta_archivo'];
        $archivoPath = $docRoot . $rutaRelativa;
        $nombreArchivo = $anexo['nombre_archivo'];
        break;
        
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Tipo inválido. Valores permitidos: pagare, estado_cuenta, anexo']);
        exit;
}

// Validar que el archivo existe y está dentro del directorio permitido
if (empty($archivoPath) || !file_exists($archivoPath)) {
    http_response_code(404);
    echo json_encode(['error' => 'Archivo no encontrado']);
    exit;
}

$uploadsBase = realpath($docRoot . '/projects/bybot_app/uploads/');
$archivoReal = realpath($archivoPath);

if (!$uploadsBase || !$archivoReal || strpos($archivoReal, $uploadsBase) !== 0) {
    http_response_code(403);
    echo json_encode(['error' => 'Acceso denegado']);
    exit;
}

// Servir el archivo
$extension = strtolower(pathinfo($archivoPath, PATHINFO_EXTENSION));
$mimeTypes = [
    'pdf' => 'application/pdf',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png'
];
$mimeType = $mimeTypes[$extension] ?? 'application/octet-stream';

header('Content-Type: ' . $mimeType);
header('Content-Length: ' . filesize($archivoPath));
header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
header('X-File-Name: ' . $nombreArchivo);
header('X-File-Size: ' . filesize($archivoPath));

readfile($archivoPath);
exit;

