<?php
/**
 * Monitor de Colas
 */

$pageTitle = 'Monitor de Colas';
$pageDescription = 'Estado y gestión de las colas de procesamiento';

include ADMIN_LAYOUTS . '/header.php';
?>

<!-- Breadcrumb -->
<nav aria-label="breadcrumb" class="mb-4">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= adminUrl('index.php') ?>">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="<?= adminUrl('index.php?page=configuracion') ?>">Configuración</a></li>
        <li class="breadcrumb-item active">Colas</li>
    </ol>
</nav>

<!-- Tabs de configuración -->
<ul class="nav nav-tabs mb-4">
    <li class="nav-item">
        <a class="nav-link" href="<?= adminUrl('index.php?page=configuracion') ?>">
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
    <li class="nav-item">
        <a class="nav-link active" href="<?= adminUrl('index.php?page=configuracion&action=colas') ?>">
            <i class="bi bi-stack me-1"></i>Colas
        </a>
    </li>
</ul>

<!-- Resumen de estado -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card text-white bg-primary">
            <div class="card-body text-center">
                <h3 class="mb-0" id="totalPendientes">-</h3>
                <small>Trabajos Pendientes</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-warning">
            <div class="card-body text-center">
                <h3 class="mb-0" id="totalProcesando">-</h3>
                <small>En Procesamiento</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-success">
            <div class="card-body text-center">
                <h3 class="mb-0" id="totalCompletados">-</h3>
                <small>Completados (hoy)</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-danger">
            <div class="card-body text-center">
                <h3 class="mb-0" id="totalFallidos">-</h3>
                <small>Fallidos (hoy)</small>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <!-- Estado de colas -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-layers me-2"></i>Estado de Colas</span>
                <button class="btn btn-sm btn-outline-primary" onclick="refreshColas()">
                    <i class="bi bi-arrow-repeat"></i>
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Cola</th>
                                <th class="text-center">Pendientes</th>
                                <th class="text-center">Procesando</th>
                                <th class="text-center">Workers</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="tablaColas">
                            <tr>
                                <td colspan="5" class="text-center py-4">
                                    <div class="spinner-border spinner-border-sm"></div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Trabajos recientes -->
        <div class="card">
            <div class="card-header">
                <i class="bi bi-clock-history me-2"></i>Trabajos Recientes
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Cola</th>
                                <th>Proceso</th>
                                <th>Estado</th>
                                <th>Tiempo</th>
                            </tr>
                        </thead>
                        <tbody id="tablaTrabajos">
                            <tr>
                                <td colspan="5" class="text-center py-4">
                                    <div class="spinner-border spinner-border-sm"></div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <!-- Acciones -->
        <div class="card mb-4">
            <div class="card-header">
                <i class="bi bi-lightning me-2"></i>Acciones
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <button class="btn btn-outline-warning" onclick="pausarColas()">
                        <i class="bi bi-pause me-1"></i>Pausar Todas las Colas
                    </button>
                    <button class="btn btn-outline-success" onclick="reanudarColas()">
                        <i class="bi bi-play me-1"></i>Reanudar Colas
                    </button>
                    <hr>
                    <button class="btn btn-outline-danger" onclick="limpiarFallidos()">
                        <i class="bi bi-trash me-1"></i>Limpiar Trabajos Fallidos
                    </button>
                    <button class="btn btn-outline-info" onclick="reintentarFallidos()">
                        <i class="bi bi-arrow-repeat me-1"></i>Reintentar Fallidos
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Conexión Redis -->
        <div class="card">
            <div class="card-header">
                <i class="bi bi-server me-2"></i>Redis
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <strong>Estado:</strong>
                    <span class="badge bg-secondary ms-2" id="redisEstado">Verificando...</span>
                </div>
                <div class="mb-3">
                    <strong>Host:</strong>
                    <span class="text-muted" id="redisHost">-</span>
                </div>
                <div class="mb-3">
                    <strong>Memoria:</strong>
                    <span class="text-muted" id="redisMemoria">-</span>
                </div>
                <div class="mb-0">
                    <strong>Uptime:</strong>
                    <span class="text-muted" id="redisUptime">-</span>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$inlineJs = <<<'JS'
let autoRefresh = null;

document.addEventListener('DOMContentLoaded', function() {
    refreshColas();
    // Auto-refresh cada 10 segundos
    autoRefresh = setInterval(refreshColas, 10000);
});

async function refreshColas() {
    try {
        const response = await fetch(`${CONFIG.apiUrl}/colas/estado`, { credentials: 'include' });
        if (!response.ok) throw new Error('Error cargando estado');
        
        const { data } = await response.json();
        renderColas(data);
        renderTrabajos(data.trabajos_recientes || []);
        updateResumen(data);
        updateRedisInfo(data.redis_info);
        
    } catch (error) {
        console.error('Error:', error);
    }
}

function renderColas(data) {
    const colas = data.colas || [];
    const tbody = document.getElementById('tablaColas');
    
    if (!colas.length) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">No hay colas configuradas</td></tr>';
        return;
    }
    
    tbody.innerHTML = colas.map(c => `
        <tr>
            <td>
                <i class="bi bi-stack me-2"></i>
                <strong>${c.nombre}</strong>
                <br><small class="text-muted">${c.descripcion || ''}</small>
            </td>
            <td class="text-center">
                <span class="badge bg-primary">${c.pendientes || 0}</span>
            </td>
            <td class="text-center">
                <span class="badge bg-warning text-dark">${c.procesando || 0}</span>
            </td>
            <td class="text-center">
                <span class="badge ${c.workers > 0 ? 'bg-success' : 'bg-secondary'}">${c.workers || 0}</span>
            </td>
            <td class="text-end">
                <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-${c.pausada ? 'success' : 'warning'}" 
                            onclick="toggleCola('${c.nombre}')" title="${c.pausada ? 'Reanudar' : 'Pausar'}">
                        <i class="bi bi-${c.pausada ? 'play' : 'pause'}"></i>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');
}

function renderTrabajos(trabajos) {
    const tbody = document.getElementById('tablaTrabajos');
    
    if (!trabajos.length) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3">No hay trabajos recientes</td></tr>';
        return;
    }
    
    tbody.innerHTML = trabajos.slice(0, 20).map(t => `
        <tr>
            <td><small class="font-monospace">${t.id?.slice(0, 8) || '-'}</small></td>
            <td><small>${t.cola}</small></td>
            <td>
                <a href="${CONFIG.adminUrl}/index.php?page=procesos&action=ver&id=${t.proceso_id}" class="text-decoration-none">
                    ${t.codigo || t.proceso_id}
                </a>
            </td>
            <td>
                <span class="badge bg-${getEstadoColor(t.estado)}">${t.estado}</span>
            </td>
            <td><small class="text-muted">${formatTiempo(t.fecha)}</small></td>
        </tr>
    `).join('');
}

function updateResumen(data) {
    document.getElementById('totalPendientes').textContent = data.total_pendientes || 0;
    document.getElementById('totalProcesando').textContent = data.total_procesando || 0;
    document.getElementById('totalCompletados').textContent = data.total_completados_hoy || 0;
    document.getElementById('totalFallidos').textContent = data.total_fallidos_hoy || 0;
}

function updateRedisInfo(info) {
    if (!info) {
        document.getElementById('redisEstado').className = 'badge bg-danger ms-2';
        document.getElementById('redisEstado').textContent = 'Desconectado';
        return;
    }
    
    document.getElementById('redisEstado').className = 'badge bg-success ms-2';
    document.getElementById('redisEstado').textContent = 'Conectado';
    document.getElementById('redisHost').textContent = info.host || '-';
    document.getElementById('redisMemoria').textContent = info.memoria || '-';
    document.getElementById('redisUptime').textContent = info.uptime || '-';
}

function getEstadoColor(estado) {
    const colores = {
        pendiente: 'secondary',
        procesando: 'warning',
        completado: 'success',
        fallido: 'danger',
        cancelado: 'dark'
    };
    return colores[estado] || 'secondary';
}

function formatTiempo(fecha) {
    if (!fecha) return '-';
    const diff = (Date.now() - new Date(fecha).getTime()) / 1000;
    if (diff < 60) return 'Hace ' + Math.floor(diff) + 's';
    if (diff < 3600) return 'Hace ' + Math.floor(diff / 60) + 'm';
    if (diff < 86400) return 'Hace ' + Math.floor(diff / 3600) + 'h';
    return new Date(fecha).toLocaleString('es-CO');
}

async function toggleCola(nombre) {
    try {
        const response = await fetch(`${CONFIG.apiUrl}/colas/${nombre}/toggle`, {
            method: 'POST',
            credentials: 'include'
        });
        if (!response.ok) throw new Error('Error');
        refreshColas();
    } catch (e) {
        showAlert('Error al cambiar estado de la cola', 'danger');
    }
}

async function pausarColas() {
    if (!confirm('¿Pausar todas las colas?')) return;
    try {
        await fetch(`${CONFIG.apiUrl}/colas/pausar-todas`, { method: 'POST', credentials: 'include' });
        showAlert('Colas pausadas', 'warning');
        refreshColas();
    } catch { showAlert('Error', 'danger'); }
}

async function reanudarColas() {
    try {
        await fetch(`${CONFIG.apiUrl}/colas/reanudar-todas`, { method: 'POST', credentials: 'include' });
        showAlert('Colas reanudadas', 'success');
        refreshColas();
    } catch { showAlert('Error', 'danger'); }
}

async function limpiarFallidos() {
    if (!confirm('¿Eliminar todos los trabajos fallidos?')) return;
    try {
        await fetch(`${CONFIG.apiUrl}/colas/limpiar-fallidos`, { method: 'POST', credentials: 'include' });
        showAlert('Trabajos fallidos eliminados', 'success');
        refreshColas();
    } catch { showAlert('Error', 'danger'); }
}

async function reintentarFallidos() {
    if (!confirm('¿Reintentar todos los trabajos fallidos?')) return;
    try {
        await fetch(`${CONFIG.apiUrl}/colas/reintentar-fallidos`, { method: 'POST', credentials: 'include' });
        showAlert('Trabajos re-encolados', 'success');
        refreshColas();
    } catch { showAlert('Error', 'danger'); }
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

