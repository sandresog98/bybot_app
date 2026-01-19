<?php
// test_rewrite.php - Verificar que el .htaccess funciona
header('Content-Type: application/json');

echo json_encode([
    'status' => 'ok',
    'message' => 'Si ves esto, el archivo se está ejecutando directamente',
    'script_name' => $_SERVER['SCRIPT_NAME'] ?? 'N/A',
    'request_uri' => $_SERVER['REQUEST_URI'] ?? 'N/A',
    'note' => 'Si accedes a /web/api/v1/test_rewrite y ves este mensaje, el .htaccess NO está funcionando'
], JSON_PRETTY_PRINT);

