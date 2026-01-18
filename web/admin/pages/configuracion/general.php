<?php
/**
 * Configuración General
 */

$pageTitle = 'Configuración';
$pageDescription = 'Configuración general del sistema';

include ADMIN_LAYOUTS . '/header.php';
?>

<!-- Breadcrumb -->
<nav aria-label="breadcrumb" class="mb-4">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= adminUrl('index.php') ?>">Dashboard</a></li>
        <li class="breadcrumb-item active">Configuración</li>
    </ol>
</nav>

<!-- Tabs de configuración -->
<ul class="nav nav-tabs mb-4">
    <li class="nav-item">
        <a class="nav-link active" href="<?= adminUrl('index.php?page=configuracion') ?>">
            <i class="bi bi-gear me-1"></i>General
        </a>
    </li>
    <?php if (hasAccess('configuracion.prompts')): ?>
    <li class="nav-item">
        <a class="nav-link" href="<?= adminUrl('index.php?page=configuracion&action=prompts') ?>">
            <i class="bi bi-robot me-1"></i>Prompts IA
        </a>
    </li>
    <?php endif; ?>
    <?php if (hasAccess('configuracion.plantillas')): ?>
    <li class="nav-item">
        <a class="nav-link" href="<?= adminUrl('index.php?page=configuracion&action=plantillas') ?>">
            <i class="bi bi-file-earmark-pdf me-1"></i>Plantillas
        </a>
    </li>
    <?php endif; ?>
    <?php if (hasAccess('configuracion.colas')): ?>
    <li class="nav-item">
        <a class="nav-link" href="<?= adminUrl('index.php?page=configuracion&action=colas') ?>">
            <i class="bi bi-stack me-1"></i>Colas
        </a>
    </li>
    <?php endif; ?>
</ul>

<div class="row">
    <div class="col-lg-8">
        <!-- Sistema -->
        <div class="card mb-4">
            <div class="card-header">
                <i class="bi bi-info-circle me-2"></i>Información del Sistema
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-sm table-borderless">
                            <tr>
                                <th class="text-muted">Versión:</th>
                                <td><?= APP_VERSION ?? '1.0.0' ?></td>
                            </tr>
                            <tr>
                                <th class="text-muted">PHP:</th>
                                <td><?= phpversion() ?></td>
                            </tr>
                            <tr>
                                <th class="text-muted">Base de Datos:</th>
                                <td>MariaDB</td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-sm table-borderless">
                            <tr>
                                <th class="text-muted">Servidor:</th>
                                <td><?= $_SERVER['SERVER_SOFTWARE'] ?? 'N/A' ?></td>
                            </tr>
                            <tr>
                                <th class="text-muted">Redis:</th>
                                <td id="redisStatus">
                                    <span class="spinner-border spinner-border-sm"></span>
                                </td>
                            </tr>
                            <tr>
                                <th class="text-muted">Zona horaria:</th>
                                <td><?= date_default_timezone_get() ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Configuración de Procesamiento -->
        <div class="card mb-4">
            <div class="card-header">
                <i class="bi bi-sliders me-2"></i>Configuración de Procesamiento
            </div>
            <div class="card-body">
                <form id="formConfigProcesamiento">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Máx. intentos de análisis</label>
                            <input type="number" class="form-control" name="max_intentos_analisis" 
                                   value="3" min="1" max="10">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Máx. intentos de llenado</label>
                            <input type="number" class="form-control" name="max_intentos_llenado" 
                                   value="3" min="1" max="10">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Tamaño máx. PDF (MB)</label>
                            <input type="number" class="form-control" name="max_size_pdf" 
                                   value="10" min="1" max="50">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Tamaño máx. imagen (MB)</label>
                            <input type="number" class="form-control" name="max_size_image" 
                                   value="5" min="1" max="20">
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save me-1"></i>Guardar Cambios
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Sidebar -->
    <div class="col-lg-4">
        <!-- Estado de servicios -->
        <div class="card mb-4">
            <div class="card-header">
                <i class="bi bi-activity me-2"></i>Estado de Servicios
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span>API</span>
                    <span class="badge bg-success" id="statusApi">Online</span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span>Base de Datos</span>
                    <span class="badge bg-success" id="statusDb">Online</span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span>Redis</span>
                    <span class="badge bg-secondary" id="statusRedis">Verificando...</span>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <span>Workers</span>
                    <span class="badge bg-secondary" id="statusWorkers">Verificando...</span>
                </div>
            </div>
        </div>
        
        <!-- Acciones rápidas -->
        <div class="card">
            <div class="card-header">
                <i class="bi bi-lightning me-2"></i>Acciones Rápidas
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <button class="btn btn-outline-warning" onclick="limpiarCache()">
                        <i class="bi bi-trash me-1"></i>Limpiar Caché
                    </button>
                    <button class="btn btn-outline-info" onclick="verificarConexiones()">
                        <i class="bi bi-arrow-repeat me-1"></i>Verificar Conexiones
                    </button>
                    <a href="<?= adminUrl('index.php?page=logs') ?>" class="btn btn-outline-secondary">
                        <i class="bi bi-file-text me-1"></i>Ver Logs
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$inlineJs = <<<'JS'
document.addEventListener('DOMContentLoaded', function() {
    verificarConexiones();
    loadConfiguracion();
    
    document.getElementById('formConfigProcesamiento').addEventListener('submit', async (e) => {
        e.preventDefault();
        await guardarConfiguracion();
    });
});

async function verificarConexiones() {
    // API
    try {
        const response = await fetch(`${CONFIG.apiUrl}/health`, { credentials: 'include' });
        document.getElementById('statusApi').className = 'badge ' + (response.ok ? 'bg-success' : 'bg-danger');
        document.getElementById('statusApi').textContent = response.ok ? 'Online' : 'Error';
    } catch {
        document.getElementById('statusApi').className = 'badge bg-danger';
        document.getElementById('statusApi').textContent = 'Offline';
    }
    
    // Redis y workers
    try {
        const response = await fetch(`${CONFIG.apiUrl}/colas/estado`, { credentials: 'include' });
        const data = await response.json();
        
        document.getElementById('statusRedis').className = 'badge ' + (data.data?.redis ? 'bg-success' : 'bg-danger');
        document.getElementById('statusRedis').textContent = data.data?.redis ? 'Conectado' : 'Desconectado';
        document.getElementById('redisStatus').textContent = data.data?.redis ? 'Conectado' : 'No disponible';
        
        document.getElementById('statusWorkers').className = 'badge ' + (data.data?.workers_active > 0 ? 'bg-success' : 'bg-warning');
        document.getElementById('statusWorkers').textContent = data.data?.workers_active > 0 ? `${data.data.workers_active} activos` : 'Ninguno';
    } catch {
        document.getElementById('statusRedis').className = 'badge bg-secondary';
        document.getElementById('statusRedis').textContent = 'N/A';
        document.getElementById('redisStatus').textContent = 'N/A';
        document.getElementById('statusWorkers').className = 'badge bg-secondary';
        document.getElementById('statusWorkers').textContent = 'N/A';
    }
}

async function loadConfiguracion() {
    try {
        const response = await fetch(`${CONFIG.apiUrl}/config/procesamiento`, { credentials: 'include' });
        if (!response.ok) return;
        
        const { data } = await response.json();
        if (data) {
            Object.keys(data).forEach(key => {
                const input = document.querySelector(`[name="${key}"]`);
                if (input) input.value = data[key];
            });
        }
    } catch (e) {
        console.error('Error cargando configuración:', e);
    }
}

async function guardarConfiguracion() {
    const form = document.getElementById('formConfigProcesamiento');
    const formData = new FormData(form);
    const data = Object.fromEntries(formData);
    
    try {
        const response = await fetch(`${CONFIG.apiUrl}/config/procesamiento`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify(data)
        });
        
        if (!response.ok) throw new Error('Error al guardar');
        
        showAlert('Configuración guardada correctamente', 'success');
        
    } catch (error) {
        showAlert(error.message, 'danger');
    }
}

async function limpiarCache() {
    if (!confirm('¿Está seguro de limpiar la caché?')) return;
    
    try {
        const response = await fetch(`${CONFIG.apiUrl}/config/limpiar-cache`, {
            method: 'POST',
            credentials: 'include'
        });
        
        if (!response.ok) throw new Error('Error al limpiar');
        
        showAlert('Caché limpiada correctamente', 'success');
        
    } catch (error) {
        showAlert(error.message, 'danger');
    }
}

function showAlert(message, type = 'info') {
    const container = document.getElementById('alertContainer');
    const alert = document.createElement('div');
    alert.className = `alert alert-${type} alert-dismissible fade show`;
    alert.innerHTML = `${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
    container.prepend(alert);
    setTimeout(() => { alert.classList.remove('show'); setTimeout(() => alert.remove(), 150); }, 4000);
}
JS;

include ADMIN_LAYOUTS . '/footer.php';

