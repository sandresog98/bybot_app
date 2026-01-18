<?php
/**
 * Módulo Procesos - Router
 */

$action = $_GET['action'] ?? 'lista';

switch ($action) {
    case 'crear':
        requireAccess('procesos.crear');
        require_once __DIR__ . '/crear.php';
        break;
        
    case 'ver':
        requireAccess('procesos.ver');
        require_once __DIR__ . '/ver.php';
        break;
        
    case 'validar':
        requireAccess('procesos.validar_ia');
        require_once __DIR__ . '/validar.php';
        break;
        
    case 'lista':
    default:
        requireAccess('procesos');
        require_once __DIR__ . '/lista.php';
        break;
}

