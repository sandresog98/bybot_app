<?php
/**
 * Módulo Configuración - Router
 */

$action = $_GET['action'] ?? 'general';

switch ($action) {
    case 'prompts':
        requireAccess('configuracion.prompts');
        require_once __DIR__ . '/prompts.php';
        break;
        
    case 'plantillas':
        requireAccess('configuracion.plantillas');
        require_once __DIR__ . '/plantillas.php';
        break;
        
    case 'colas':
        requireAccess('configuracion.colas');
        require_once __DIR__ . '/colas.php';
        break;
        
    case 'general':
    default:
        requireAccess('configuracion');
        require_once __DIR__ . '/general.php';
        break;
}

