<?php
// test.php - Archivo de prueba para verificar que PHP funciona en /web/api/
header('Content-Type: application/json');
echo json_encode([
    'status' => 'ok',
    'message' => 'PHP funciona correctamente en /web/api/',
    'script_name' => $_SERVER['SCRIPT_NAME'] ?? 'N/A',
    'request_uri' => $_SERVER['REQUEST_URI'] ?? 'N/A'
]);

