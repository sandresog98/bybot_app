<?php
/**
 * Subir Archivos desde n8n
 * 
 * POST /api/v1/archivos/subir-externo
 * 
 * Permite a n8n subir archivos procesados (ej: pagaré llenado)
 */

require_once BASE_DIR . '/web/core/Response.php';
require_once BASE_DIR . '/web/core/N8nClient.php';
require_once BASE_DIR . '/web/modules/procesos/models/Proceso.php';
require_once BASE_DIR . '/web/modules/procesos/models/Anexo.php';

/**
 * Procesa la subida de archivo desde n8n
 */
function handleExternalUpload(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        Response::error('Método no permitido', [], 405);
    }
    
    // Validar autenticación n8n
    $n8nToken = $_SERVER['HTTP_X_N8N_ACCESS_TOKEN'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    $n8nToken = str_replace('Bearer ', '', $n8nToken);
    
    $expectedToken = $_ENV['N8N_ACCESS_TOKEN'] ?? '';
    
    if (empty($expectedToken) || $n8nToken !== $expectedToken) {
        Response::error('Token de acceso inválido', [], 401);
    }
    
    // Obtener datos
    $procesoId = $_POST['proceso_id'] ?? null;
    $tipo = $_POST['tipo'] ?? 'pagare_llenado';
    
    if (!$procesoId) {
        Response::error('proceso_id es requerido', [], 400);
    }
    
    // Verificar proceso
    $procesoModel = new Proceso();
    $proceso = $procesoModel->findById((int)$procesoId);
    
    if (!$proceso) {
        Response::error('Proceso no encontrado', [], 404);
    }
    
    // Verificar que hay archivo
    if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
        // Intentar con contenido base64
        $base64Content = $_POST['archivo_base64'] ?? null;
        $nombreArchivo = $_POST['nombre_archivo'] ?? "archivo_{$proceso['codigo']}.pdf";
        
        if ($base64Content) {
            $resultado = guardarArchivoBase64($procesoId, $base64Content, $nombreArchivo, $tipo);
            Response::success('Archivo subido exitosamente', $resultado);
            return;
        }
        
        Response::error('No se recibió archivo', [], 400);
    }
    
    // Guardar archivo subido
    $resultado = guardarArchivoSubido($procesoId, $_FILES['archivo'], $tipo);
    Response::success('Archivo subido exitosamente', $resultado);
}

/**
 * Guarda un archivo subido
 */
function guardarArchivoSubido(int $procesoId, array $file, string $tipo): array {
    $anexoModel = new Anexo();
    $procesoModel = new Proceso();
    $proceso = $procesoModel->findById($procesoId);
    
    // Generar nombre único
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $nombreArchivo = uniqid("{$tipo}_{$proceso['codigo']}_") . '.' . $extension;
    
    // Determinar ruta
    $yearMonth = date('Y/m');
    $uploadsDir = defined('UPLOADS_DIR') ? UPLOADS_DIR : (BASE_DIR . '/uploads');
    $targetDir = $uploadsDir . '/procesos/' . $yearMonth;
    
    // Crear directorio si no existe
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0775, true);
    }
    
    $targetPath = $targetDir . '/' . $nombreArchivo;
    $relativePath = 'procesos/' . $yearMonth . '/' . $nombreArchivo;
    
    // Mover archivo
    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        Response::error('Error al guardar archivo', [], 500);
    }
    
    // Registrar en BD
    $anexoId = $anexoModel->create([
        'proceso_id' => $procesoId,
        'nombre_original' => $file['name'],
        'nombre_archivo' => $nombreArchivo,
        'ruta_archivo' => $relativePath,
        'tipo' => $tipo,
        'tamanio_bytes' => $file['size'],
        'mime_type' => $file['type'] ?? 'application/octet-stream'
    ]);
    
    // Si es pagaré llenado, actualizar proceso
    if ($tipo === 'pagare_llenado') {
        $procesoModel->update($procesoId, [
            'archivo_pagare_llenado' => $relativePath
        ]);
    }
    
    return [
        'anexo_id' => $anexoId,
        'nombre_archivo' => $nombreArchivo,
        'ruta_archivo' => $relativePath,
        'tamanio' => $file['size']
    ];
}

/**
 * Guarda un archivo desde contenido base64
 */
function guardarArchivoBase64(int $procesoId, string $base64Content, string $nombreOriginal, string $tipo): array {
    $anexoModel = new Anexo();
    $procesoModel = new Proceso();
    $proceso = $procesoModel->findById($procesoId);
    
    // Decodificar base64
    // Manejar formato data:application/pdf;base64,xxxxx
    if (strpos($base64Content, ',') !== false) {
        $parts = explode(',', $base64Content, 2);
        $base64Content = $parts[1];
    }
    
    $contenido = base64_decode($base64Content);
    if ($contenido === false) {
        Response::error('Contenido base64 inválido', [], 400);
    }
    
    // Generar nombre único
    $extension = pathinfo($nombreOriginal, PATHINFO_EXTENSION) ?: 'pdf';
    $nombreArchivo = uniqid("{$tipo}_{$proceso['codigo']}_") . '.' . $extension;
    
    // Determinar ruta
    $yearMonth = date('Y/m');
    $uploadsDir = defined('UPLOADS_DIR') ? UPLOADS_DIR : (BASE_DIR . '/uploads');
    $targetDir = $uploadsDir . '/procesos/' . $yearMonth;
    
    // Crear directorio si no existe
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0775, true);
    }
    
    $targetPath = $targetDir . '/' . $nombreArchivo;
    $relativePath = 'procesos/' . $yearMonth . '/' . $nombreArchivo;
    
    // Guardar archivo
    $bytes = file_put_contents($targetPath, $contenido);
    if ($bytes === false) {
        Response::error('Error al guardar archivo', [], 500);
    }
    
    // Detectar mime type
    $mimeType = 'application/pdf';
    if (function_exists('mime_content_type')) {
        $mimeType = mime_content_type($targetPath);
    }
    
    // Registrar en BD
    $anexoId = $anexoModel->create([
        'proceso_id' => $procesoId,
        'nombre_original' => $nombreOriginal,
        'nombre_archivo' => $nombreArchivo,
        'ruta_archivo' => $relativePath,
        'tipo' => $tipo,
        'tamanio_bytes' => $bytes,
        'mime_type' => $mimeType
    ]);
    
    // Si es pagaré llenado, actualizar proceso
    if ($tipo === 'pagare_llenado') {
        $procesoModel->update($procesoId, [
            'archivo_pagare_llenado' => $relativePath
        ]);
    }
    
    return [
        'anexo_id' => $anexoId,
        'nombre_archivo' => $nombreArchivo,
        'ruta_archivo' => $relativePath,
        'tamanio' => $bytes
    ];
}

// Ejecutar
handleExternalUpload();

