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
    
    // Convertir rutas relativas de BD a absolutas
    $docRoot = $_SERVER['DOCUMENT_ROOT'] ?? '/opt/lampp/htdocs';
    $rutaRelativa = null;
    
    // Determinar qué archivo descargar según el tipo
    switch ($tipo) {
        case 'pagare':
            $rutaRelativa = $proceso['archivo_pagare_original'] ?? null;
            $archivoPath = $rutaRelativa ? $docRoot . $rutaRelativa : null;
            $nombreArchivo = 'pagare_' . $proceso['codigo'] . '.pdf';
            break;
        case 'estado_cuenta':
            $rutaRelativa = $proceso['archivo_estado_cuenta'] ?? null;
            $archivoPath = $rutaRelativa ? $docRoot . $rutaRelativa : null;
            $nombreArchivo = 'estado_cuenta_' . $proceso['codigo'] . '.pdf';
            break;
        case 'anexo':
            if (!empty($fileId)) {
                $anexos = $crearCoopModel->obtenerAnexos($procesoId);
                foreach ($anexos as $anexo) {
                    if ($anexo['id'] == $fileId) {
                        $rutaRelativa = $anexo['ruta_archivo'];
                        $archivoPath = $docRoot . $rutaRelativa;
                        $nombreArchivo = $anexo['nombre_archivo'];
                        break;
                    }
                }
            }
            break;
    }
}

// Validar que el archivo existe
if (empty($archivoPath) || !file_exists($archivoPath)) {
    ob_end_clean();
    http_response_code(404);
    echo 'Archivo no encontrado';
    exit;
}

// Validar que el archivo está dentro del directorio uploads
// Obtener la ruta real del archivo
$archivoReal = realpath($archivoPath);
if (!$archivoReal) {
    ob_end_clean();
    http_response_code(404);
    echo 'Archivo no encontrado';
    exit;
}

// Construir la ruta base de uploads de forma dinámica
// La ruta relativa en BD empieza con /projects/bybot_app/uploads/ o similar
$uploadsBase = false;

if (!empty($rutaRelativa) && strpos($rutaRelativa, '/uploads/') !== false) {
    // Extraer la parte hasta /uploads/ desde la ruta relativa
    $parts = explode('/uploads/', $rutaRelativa);
    $basePath = $parts[0]; // Ej: /projects/bybot_app
    $uploadsBase = realpath($docRoot . $basePath . '/uploads/');
}

// Si no se encontró, intentar ubicaciones comunes
if (!$uploadsBase) {
    $possiblePaths = [
        $docRoot . '/projects/bybot_app/uploads/',
        $docRoot . '/bybot_app/uploads/',
        dirname(dirname(dirname(dirname(__DIR__)))) . '/uploads/',
        // Para producción: buscar uploads en la raíz del proyecto
        dirname($archivoReal) // Subir desde el archivo hasta encontrar uploads
    ];
    
    foreach ($possiblePaths as $path) {
        $resolved = realpath($path);
        if ($resolved !== false && is_dir($resolved)) {
            // Verificar que realmente contiene "uploads" en la ruta
            if (strpos($resolved, 'uploads') !== false) {
                $uploadsBase = $resolved;
                break;
            }
        }
    }
}

// Si aún no se encontró, usar el directorio del archivo y subir hasta encontrar uploads
if (!$uploadsBase) {
    $currentPath = dirname($archivoReal);
    $maxDepth = 10; // Límite de seguridad
    $depth = 0;
    
    while ($depth < $maxDepth) {
        if (basename($currentPath) === 'uploads') {
            $uploadsBase = $currentPath;
            break;
        }
        $parent = dirname($currentPath);
        if ($parent === $currentPath) {
            break; // Llegamos a la raíz
        }
        $currentPath = $parent;
        $depth++;
    }
}

// Validar que el archivo está dentro de uploads
if (!$uploadsBase) {
    // Si no se puede determinar la base, al menos validar que el archivo existe
    // y está en una ruta que contiene "uploads"
    if (strpos($archivoReal, 'uploads') === false) {
        ob_end_clean();
        http_response_code(403);
        echo 'Acceso denegado: archivo fuera de directorio uploads';
        exit;
    }
} else {
    // Validación estricta: el archivo debe estar dentro de uploads
    $uploadsBaseReal = realpath($uploadsBase);
    if (!$uploadsBaseReal || strpos($archivoReal, $uploadsBaseReal) !== 0) {
        // Log para debugging
        error_log("Acceso denegado - Archivo: $archivoReal, Base esperada: $uploadsBaseReal");
    ob_end_clean();
    http_response_code(403);
    echo 'Acceso denegado';
    exit;
    }
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

