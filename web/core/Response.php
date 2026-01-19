<?php
/**
 * Response - Clase para respuestas estandarizadas de API
 * ByBot v2.0
 */

class Response {
    /**
     * Respuesta exitosa
     */
    public static function success($data = null, $message = 'Operación exitosa', $code = 200) {
        return [
            'success' => true,
            'message' => $message,
            'data' => $data,
            'code' => $code
        ];
    }
    
    /**
     * Respuesta de error
     */
    public static function error($message, $code = 400, $errors = []) {
        return [
            'success' => false,
            'message' => $message,
            'errors' => $errors,
            'code' => $code
        ];
    }
    
    /**
     * Respuesta paginada
     */
    public static function paginated($data, $total, $page, $perPage, $message = 'Datos obtenidos') {
        $pagination = [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => ceil($total / $perPage)
        ];
        return [
            'success' => true,
            'message' => $message,
            'data' => $data,
            'pagination' => $pagination,
            'code' => 200
        ];
    }
    
    /**
     * Enviar respuesta paginada JSON
     */
    public static function jsonPaginated($data, $total, $page, $perPage, $message = 'Datos obtenidos') {
        self::json(self::paginated($data, $total, $page, $perPage, $message));
    }
    
    /**
     * Enviar respuesta JSON y terminar
     */
    public static function json($response) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($response['code'] ?? 200);
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
    
    /**
     * Enviar éxito JSON
     */
    public static function jsonSuccess($data = null, $message = 'Operación exitosa', $code = 200) {
        self::json(self::success($data, $message, $code));
    }
    
    /**
     * Enviar error JSON
     */
    public static function jsonError($message, $code = 400, $errors = []) {
        self::json(self::error($message, $code, $errors));
    }
    
    /**
     * Error 401 - No autorizado
     */
    public static function unauthorized($message = 'No autorizado') {
        self::json(self::error($message, 401));
    }
    
    /**
     * Error 403 - Prohibido
     */
    public static function forbidden($message = 'Acceso denegado') {
        self::json(self::error($message, 403));
    }
    
    /**
     * Error 404 - No encontrado
     */
    public static function notFound($message = 'Recurso no encontrado') {
        self::json(self::error($message, 404));
    }
    
    /**
     * Error 405 - Método no permitido
     */
    public static function methodNotAllowed($message = 'Método no permitido') {
        self::json(self::error($message, 405));
    }
    
    /**
     * Error 422 - Entidad no procesable (validación)
     */
    public static function validationError($errors, $message = 'Error de validación') {
        self::json(self::error($message, 422, $errors));
    }
    
    /**
     * Error 500 - Error interno
     */
    public static function serverError($message = 'Error interno del servidor') {
        self::json(self::error($message, 500));
    }
    
    /**
     * Error 503 - Servicio no disponible
     */
    public static function serviceUnavailable($message = 'Servicio no disponible') {
        self::json(self::error($message, 503));
    }
    
    /**
     * Respuesta de creación exitosa
     */
    public static function created($data = null, $message = 'Recurso creado exitosamente') {
        self::json(self::success($data, $message, 201));
    }
    
    /**
     * Respuesta sin contenido
     */
    public static function noContent() {
        http_response_code(204);
        exit;
    }
}

