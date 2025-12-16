<?php
/**
 * Clase utilitaria para manejo seguro de subidas de archivos
 * ByBot App - Límites: Imágenes 5MB, PDFs 10MB
 */

class FileUploadManager {
    
    /**
     * Genera un nombre único para un archivo
     */
    public static function generateUniqueFileName($originalName, $prefix = '', $code = '') {
        $pathInfo = pathinfo(strtolower($originalName));
        $baseName = $pathInfo['filename'] ?? 'archivo';
        $extension = $pathInfo['extension'] ?? '';
        
        $cleanBase = preg_replace('/[^a-z0-9_-]/', '-', $baseName);
        $cleanBase = preg_replace('/-+/', '-', $cleanBase);
        $cleanBase = trim($cleanBase, '-');
        if (empty($cleanBase)) {
            $cleanBase = 'archivo';
        }
        
        $uniquePrefix = '';
        if (!empty($prefix)) {
            $uniquePrefix = $prefix . '_';
        }
        if (!empty($code)) {
            $uniquePrefix .= $code . '_';
        }
        
        $timestamp = date('Ymd_His');
        $uniqueId = substr(uniqid('', true), -8);
        
        $fileName = $uniquePrefix . $cleanBase . '_' . $timestamp . '_' . $uniqueId;
        
        if (!empty($extension)) {
            $fileName .= '.' . $extension;
        }
        
        return $fileName;
    }
    
    /**
     * Valida y guarda un archivo subido con nombre único
     */
    public static function saveUploadedFile($file, $destinationDir, $options = []) {
        // Límites según tipo: Imágenes 5MB, PDFs 10MB
        $defaults = [
            'maxSize' => 10 * 1024 * 1024, // 10MB por defecto (PDFs)
            'allowedExtensions' => ['jpg', 'jpeg', 'png', 'pdf'],
            'prefix' => '',
            'code' => '',
            'createSubdirs' => true,
            'webPath' => ''
        ];
        
        $config = array_merge($defaults, $options);
        
        if (!isset($file) || !is_array($file)) {
            throw new Exception('Archivo no válido');
        }
        
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new Exception('Error en la subida del archivo (código: ' . ($file['error'] ?? 'desconocido') . ')');
        }
        
        $originalName = $file['name'] ?? '';
        $tmpName = $file['tmp_name'] ?? '';
        $size = (int)($file['size'] ?? 0);
        
        if (empty($originalName)) {
            throw new Exception('Nombre de archivo vacío');
        }
        
        if ($size <= 0) {
            throw new Exception('Archivo vacío');
        }
        
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        
        // Ajustar límite según tipo de archivo
        if (in_array($extension, ['jpg', 'jpeg', 'png'], true)) {
            $maxSize = 5 * 1024 * 1024; // 5MB para imágenes
        } else {
            $maxSize = 10 * 1024 * 1024; // 10MB para PDFs
        }
        
        if ($size > $maxSize) {
            throw new Exception('Archivo demasiado grande. Máximo: ' . self::formatBytes($maxSize));
        }
        
        if (!in_array($extension, $config['allowedExtensions'], true)) {
            throw new Exception('Extensión no permitida. Permitidas: ' . implode(', ', $config['allowedExtensions']));
        }
        
        $finalDir = $destinationDir;
        if ($config['createSubdirs']) {
            $year = date('Y');
            $month = date('m');
            $finalDir = rtrim($destinationDir, '/') . '/' . $year . '/' . $month;
        }
        
        if (!is_dir($finalDir)) {
            if (!@mkdir($finalDir, 0775, true)) {
                throw new Exception('No se pudo crear el directorio: ' . $finalDir);
            }
        }
        
        $uniqueFileName = self::generateUniqueFileName($originalName, $config['prefix'], $config['code']);
        $fullPath = rtrim($finalDir, '/') . '/' . $uniqueFileName;
        
        if (!move_uploaded_file($tmpName, $fullPath)) {
            throw new Exception('No se pudo guardar el archivo');
        }
        
        $webPath = '';
        if (!empty($config['webPath'])) {
            $relativePath = str_replace($_SERVER['DOCUMENT_ROOT'], '', $fullPath);
            $webPath = $config['webPath'] . $relativePath;
        }
        
        return [
            'success' => true,
            'fileName' => $uniqueFileName,
            'originalName' => $originalName,
            'fullPath' => $fullPath,
            'relativePath' => str_replace($_SERVER['DOCUMENT_ROOT'], '', $fullPath),
            'webPath' => $webPath,
            'size' => $size,
            'extension' => $extension
        ];
    }
    
    /**
     * Formatea bytes a formato legible
     */
    private static function formatBytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
    
    /**
     * Elimina un archivo de forma segura
     */
    public static function deleteFile($filePath) {
        if (file_exists($filePath) && is_file($filePath)) {
            return @unlink($filePath);
        }
        return false;
    }
}

