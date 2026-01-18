<?php
/**
 * Módulo Usuarios - Router
 */

$action = $_GET['action'] ?? 'lista';

switch ($action) {
    case 'crear':
        requireAccess('usuarios.crear');
        require_once __DIR__ . '/crear.php';
        break;
        
    case 'editar':
        requireAccess('usuarios.editar');
        require_once __DIR__ . '/editar.php';
        break;
        
    case 'lista':
    default:
        requireAccess('usuarios');
        require_once __DIR__ . '/lista.php';
        break;
}

