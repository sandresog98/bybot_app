<?php
/**
 * N8nClient - Cliente para comunicación con n8n
 * 
 * Permite disparar webhooks y recibir respuestas de n8n
 */

class N8nClient {
    
    private string $baseUrl;
    private string $webhookUrl;
    private string $apiKey;
    private string $webhookSecret;
    private int $timeout;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->baseUrl = $_ENV['N8N_BASE_URL'] ?? 'http://localhost:5678';
        $this->webhookUrl = $_ENV['N8N_WEBHOOK_URL'] ?? $this->baseUrl . '/webhook';
        $this->apiKey = $_ENV['N8N_API_KEY'] ?? '';
        $this->webhookSecret = $_ENV['N8N_WEBHOOK_SECRET'] ?? '';
        $this->timeout = (int)($_ENV['N8N_TIMEOUT'] ?? 30);
    }
    
    /**
     * Dispara el flujo de análisis de documentos
     * 
     * @param int $procesoId ID del proceso
     * @param array $archivos Lista de archivos a analizar
     * @param array $opciones Opciones adicionales
     * @return array Respuesta de n8n
     */
    public function triggerAnalysis(int $procesoId, array $archivos, array $opciones = []): array {
        $payload = [
            'action' => 'analyze',
            'proceso_id' => $procesoId,
            'archivos' => $archivos,
            'callback_url' => $this->getCallbackUrl(),
            'timestamp' => time(),
            'opciones' => array_merge([
                'reintentar' => false,
                'prioridad' => 5
            ], $opciones)
        ];
        
        return $this->sendWebhook('analisis', $payload);
    }
    
    /**
     * Dispara el flujo de llenado de pagaré
     * 
     * @param int $procesoId ID del proceso
     * @param array $datosIA Datos validados para llenar
     * @param string $pagareUrl URL del pagaré original
     * @param array $opciones Opciones adicionales
     * @return array Respuesta de n8n
     */
    public function triggerFilling(int $procesoId, array $datosIA, string $pagareUrl, array $opciones = []): array {
        $payload = [
            'action' => 'fill',
            'proceso_id' => $procesoId,
            'datos_ia' => $datosIA,
            'pagare_url' => $pagareUrl,
            'callback_url' => $this->getCallbackUrl(),
            'upload_url' => $this->getUploadUrl(),
            'timestamp' => time(),
            'opciones' => array_merge([
                'plantilla' => 'default'
            ], $opciones)
        ];
        
        return $this->sendWebhook('llenado', $payload);
    }
    
    /**
     * Dispara el flujo de notificación
     * 
     * @param string $tipo Tipo de notificación
     * @param array $datos Datos de la notificación
     * @return array Respuesta de n8n
     */
    public function triggerNotification(string $tipo, array $datos): array {
        $payload = [
            'action' => 'notify',
            'tipo' => $tipo,
            'datos' => $datos,
            'timestamp' => time()
        ];
        
        return $this->sendWebhook('notificacion', $payload);
    }
    
    /**
     * Envía un webhook a n8n
     * 
     * @param string $flujo Nombre del flujo (analisis, llenado, notificacion)
     * @param array $payload Datos a enviar
     * @return array Respuesta
     */
    private function sendWebhook(string $flujo, array $payload): array {
        $url = $this->webhookUrl . '/' . $flujo;
        
        // Agregar firma de seguridad
        $payload['signature'] = $this->generateSignature($payload);
        
        $ch = curl_init($url);
        
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'X-N8N-API-KEY: ' . $this->apiKey,
                'X-Webhook-Secret: ' . $this->webhookSecret,
                'X-Request-ID: ' . $this->generateRequestId()
            ]
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        // Log de la solicitud
        $this->logRequest($flujo, $payload, $response, $httpCode);
        
        if ($error) {
            return [
                'success' => false,
                'error' => 'CURL_ERROR',
                'message' => $error,
                'http_code' => 0
            ];
        }
        
        if ($httpCode >= 200 && $httpCode < 300) {
            $data = json_decode($response, true);
            return [
                'success' => true,
                'data' => $data,
                'http_code' => $httpCode,
                'execution_id' => $data['executionId'] ?? null
            ];
        }
        
        return [
            'success' => false,
            'error' => 'HTTP_ERROR',
            'message' => "HTTP $httpCode: $response",
            'http_code' => $httpCode
        ];
    }
    
    /**
     * Genera firma HMAC para validar el payload
     */
    private function generateSignature(array $payload): string {
        $data = json_encode($payload);
        return hash_hmac('sha256', $data, $this->webhookSecret);
    }
    
    /**
     * Valida la firma de un callback de n8n
     * 
     * @param string $payload Payload JSON recibido
     * @param string $signature Firma recibida en header
     * @return bool
     */
    public function validateSignature(string $payload, string $signature): bool {
        $expected = hash_hmac('sha256', $payload, $this->webhookSecret);
        return hash_equals($expected, $signature);
    }
    
    /**
     * Genera un ID único para la solicitud
     */
    private function generateRequestId(): string {
        return 'bybot_' . uniqid() . '_' . time();
    }
    
    /**
     * Obtiene la URL de callback para que n8n responda
     */
    private function getCallbackUrl(): string {
        $baseUrl = $_ENV['APP_URL'] ?? '';
        return $baseUrl . '/web/api/v1/webhook/n8n';
    }
    
    /**
     * Obtiene la URL para que n8n suba archivos
     */
    private function getUploadUrl(): string {
        $baseUrl = $_ENV['APP_URL'] ?? '';
        return $baseUrl . '/web/api/v1/archivos/subir-externo';
    }
    
    /**
     * Genera token temporal para que n8n acceda a archivos
     * 
     * @param int $procesoId
     * @param int $archivoId
     * @param int $expiraEn Segundos hasta expiración (default 1 hora)
     * @return string Token de acceso
     */
    public function generateFileAccessToken(int $procesoId, int $archivoId, int $expiraEn = 3600): string {
        $data = [
            'proceso_id' => $procesoId,
            'archivo_id' => $archivoId,
            'expires' => time() + $expiraEn
        ];
        
        $payload = base64_encode(json_encode($data));
        $signature = hash_hmac('sha256', $payload, $this->webhookSecret);
        
        return $payload . '.' . $signature;
    }
    
    /**
     * Valida un token de acceso a archivos
     * 
     * @param string $token
     * @return array|false Datos del token o false si inválido
     */
    public function validateFileAccessToken(string $token) {
        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            return false;
        }
        
        [$payload, $signature] = $parts;
        
        // Verificar firma
        $expectedSignature = hash_hmac('sha256', $payload, $this->webhookSecret);
        if (!hash_equals($expectedSignature, $signature)) {
            return false;
        }
        
        // Decodificar y verificar expiración
        $data = json_decode(base64_decode($payload), true);
        if (!$data || !isset($data['expires'])) {
            return false;
        }
        
        if ($data['expires'] < time()) {
            return false; // Token expirado
        }
        
        return $data;
    }
    
    /**
     * Prepara los datos de archivos para enviar a n8n
     * 
     * @param int $procesoId
     * @param array $anexos Lista de anexos del proceso
     * @return array URLs con tokens de acceso
     */
    public function prepareFilesForN8n(int $procesoId, array $anexos): array {
        $baseUrl = $_ENV['APP_URL'] ?? '';
        $files = [];
        
        foreach ($anexos as $anexo) {
            $token = $this->generateFileAccessToken($procesoId, $anexo['id']);
            $files[] = [
                'id' => $anexo['id'],
                'nombre' => $anexo['nombre_original'],
                'tipo' => $anexo['tipo'],
                'mime_type' => $anexo['mime_type'],
                'url' => $baseUrl . '/web/api/v1/archivos/servir/' . $anexo['id'] . '?token=' . urlencode($token)
            ];
        }
        
        return $files;
    }
    
    /**
     * Log de solicitudes a n8n
     */
    private function logRequest(string $flujo, array $payload, ?string $response, int $httpCode): void {
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'flujo' => $flujo,
            'proceso_id' => $payload['proceso_id'] ?? null,
            'http_code' => $httpCode,
            'success' => $httpCode >= 200 && $httpCode < 300
        ];
        
        // Log a archivo si está configurado
        $logPath = $_ENV['LOG_PATH'] ?? BASE_DIR . '/logs';
        $logFile = $logPath . '/n8n_' . date('Y-m-d') . '.log';
        
        if (is_writable(dirname($logFile))) {
            file_put_contents(
                $logFile, 
                json_encode($logData) . PHP_EOL, 
                FILE_APPEND | LOCK_EX
            );
        }
    }
    
    /**
     * Verifica si n8n está disponible
     * 
     * @return bool
     */
    public function healthCheck(): bool {
        $ch = curl_init($this->baseUrl . '/healthz');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_NOBODY => true
        ]);
        
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return $httpCode === 200;
    }
}

