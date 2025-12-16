<?php
/**
 * Script de debug para verificar carga de .env
 * TEMPORAL - ELIMINAR DESPUÉS DE DEBUGGING
 */

header('Content-Type: text/plain');

echo "=== DEBUG DE CARGA DE .ENV ===\n\n";

// Ruta del .env desde diferentes ubicaciones
$possiblePaths = [
    __DIR__ . '/../.env',
    __DIR__ . '/../../.env',
    dirname(__DIR__, 2) . '/.env',
    dirname(__DIR__, 3) . '/.env',
    $_SERVER['DOCUMENT_ROOT'] . '/.env',
    $_SERVER['DOCUMENT_ROOT'] . '/bybot_app/.env',
    $_SERVER['DOCUMENT_ROOT'] . '/projects/bybot_app/.env',
];

echo "📁 Rutas posibles del .env:\n";
foreach ($possiblePaths as $path) {
    $exists = file_exists($path);
    $readable = $exists && is_readable($path);
    echo "   " . ($exists ? "✅" : "❌") . " " . ($readable ? "📖" : "🔒") . " $path\n";
    if ($exists && $readable) {
        $content = file_get_contents($path);
        $hasToken = strpos($content, 'BOT_API_TOKEN') !== false;
        echo "      └─ Contiene BOT_API_TOKEN: " . ($hasToken ? "✅ Sí" : "❌ No") . "\n";
    }
}

echo "\n📂 Información del sistema:\n";
echo "   __DIR__: " . __DIR__ . "\n";
echo "   DOCUMENT_ROOT: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'NO DEFINIDO') . "\n";
echo "   SCRIPT_FILENAME: " . ($_SERVER['SCRIPT_FILENAME'] ?? 'NO DEFINIDO') . "\n";

echo "\n🔍 Intentando cargar .env:\n";

// Probar cargar desde la ruta más probable
$envPath = dirname(__DIR__) . '/.env';
echo "   Ruta intentada: $envPath\n";
echo "   Existe: " . (file_exists($envPath) ? "Sí" : "No") . "\n";

if (file_exists($envPath)) {
    require_once __DIR__ . '/../config/env_loader.php';
    loadEnv();
    
    echo "\n📋 Variables después de loadEnv():\n";
    echo "   getenv('BOT_API_TOKEN'): " . (getenv('BOT_API_TOKEN') ?: 'NO ENCONTRADO') . "\n";
    echo "   \$_ENV['BOT_API_TOKEN']: " . (isset($_ENV['BOT_API_TOKEN']) ? $_ENV['BOT_API_TOKEN'] : 'NO ENCONTRADO') . "\n";
    echo "   \$_SERVER['BOT_API_TOKEN']: " . (isset($_SERVER['BOT_API_TOKEN']) ? $_SERVER['BOT_API_TOKEN'] : 'NO ENCONTRADO') . "\n";
    if (isset($GLOBALS['_BY_BOT_ENV']['BOT_API_TOKEN'])) {
        echo "   \$GLOBALS['_BY_BOT_ENV']['BOT_API_TOKEN']: " . $GLOBALS['_BY_BOT_ENV']['BOT_API_TOKEN'] . "\n";
    } else {
        echo "   \$GLOBALS['_BY_BOT_ENV']['BOT_API_TOKEN']: NO ENCONTRADO\n";
    }
    
    // Leer directamente el archivo
    echo "\n📄 Contenido del .env (primeras líneas con BOT_API_TOKEN):\n";
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, 'BOT_API_TOKEN') !== false) {
            echo "   $line\n";
        }
    }
} else {
    echo "   ❌ El archivo .env no existe en la ruta esperada\n";
}

echo "\n=== FIN DEL DEBUG ===\n";

