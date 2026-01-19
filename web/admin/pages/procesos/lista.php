<?php
/**
 * Lista de Procesos
 */

$pageTitle = 'Procesos';
$pageDescription = 'Gestión de procesos de cobranza';

include ADMIN_LAYOUTS . '/header.php';
?>

<!-- Barra de acciones -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= adminUrl('index.php') ?>">Dashboard</a></li>
                <li class="breadcrumb-item active">Procesos</li>
            </ol>
        </nav>
    </div>
    <?php if (hasAccess('procesos.crear')): ?>
    <a href="<?= adminUrl('index.php?page=procesos&action=crear') ?>" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i> Nuevo Proceso
    </a>
    <?php endif; ?>
</div>

<!-- Filtros -->
<div class="card mb-4">
    <div class="card-body">
        <form id="formFiltros" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Código</label>
                <input type="text" class="form-control" name="codigo" placeholder="Buscar por código...">
            </div>
            <div class="col-md-2">
                <label class="form-label">Estado</label>
                <select class="form-select" name="estado">
                    <option value="">Todos</option>
                    <option value="creado">Creado</option>
                    <option value="en_cola_analisis">En Cola Análisis</option>
                    <option value="analizando">Analizando</option>
                    <option value="analizado">Analizado</option>
                    <option value="validado">Validado</option>
                    <option value="en_cola_llenado">En Cola Llenado</option>
                    <option value="llenando">Llenando</option>
                    <option value="completado">Completado</option>
                    <option value="error_analisis">Error Análisis</option>
                    <option value="error_llenado">Error Llenado</option>
                    <option value="cancelado">Cancelado</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Tipo</label>
                <select class="form-select" name="tipo">
                    <option value="">Todos</option>
                    <option value="cobranza">Cobranza</option>
                    <option value="demanda">Demanda</option>
                    <option value="otro">Otro</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Desde</label>
                <input type="date" class="form-control" name="fecha_desde">
            </div>
            <div class="col-md-2">
                <label class="form-label">Hasta</label>
                <input type="date" class="form-control" name="fecha_hasta">
            </div>
            <div class="col-md-1 d-flex align-items-end">
                <button type="submit" class="btn btn-outline-primary w-100">
                    <i class="bi bi-search"></i>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Tabla de Procesos -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-folder2-open me-2"></i>Lista de Procesos</span>
        <span class="badge bg-secondary" id="totalProcesos">0</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Estado</th>
                        <th>Tipo</th>
                        <th>Prioridad</th>
                        <th>Creado</th>
                        <th>Actualizado</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody id="tablaProcesos">
                    <tr>
                        <td colspan="7" class="text-center py-5">
                            <div class="spinner-border text-primary" role="status"></div>
                            <p class="mt-2 mb-0 text-muted">Cargando procesos...</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer">
        <nav aria-label="Paginación">
            <ul class="pagination pagination-sm justify-content-center mb-0" id="paginacion"></ul>
        </nav>
    </div>
</div>

<?php
$inlineJs = <<<'JS'
const nombresEstados = {
    creado: 'Creado',
    en_cola_analisis: 'En Cola Análisis',
    analizando: 'Analizando',
    analizado: 'Analizado',
    validado: 'Validado',
    en_cola_llenado: 'En Cola Llenado',
    llenando: 'Llenando',
    completado: 'Completado',
    error_analisis: 'Error Análisis',
    error_llenado: 'Error Llenado',
    cancelado: 'Cancelado'
};

let currentPage = 1;
let currentFilters = {};

document.addEventListener('DOMContentLoaded', function() {
    loadProcesos();
    
    // Filtros
    document.getElementById('formFiltros').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        currentFilters = {};
        formData.forEach((value, key) => {
            if (value) currentFilters[key] = value;
        });
        currentPage = 1;
        loadProcesos();
    });
});

async function loadProcesos(page = 1) {
    currentPage = page;
    const tbody = document.getElementById('tablaProcesos');
    
    tbody.innerHTML = `
        <tr>
            <td colspan="7" class="text-center py-5">
                <div class="spinner-border text-primary" role="status"></div>
            </td>
        </tr>
    `;
    
    try {
        const params = new URLSearchParams({
            page: page,
            per_page: 15,
            ...currentFilters
        });
        
        const response = await fetch(`${CONFIG.apiUrl}/casos?${params}`, {
            credentials: 'include'
        });
        
        if (!response.ok) throw new Error('Error cargando procesos');
        
        const data = await response.json();
        const procesos = data.data || [];
        const pagination = data.pagination || {};
        
        document.getElementById('totalProcesos').textContent = pagination.total || 0;
        
        if (procesos.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="7" class="text-center py-5 text-muted">
                        <i class="bi bi-folder2 fs-1 d-block mb-2"></i>
                        No se encontraron procesos
                    </td>
                </tr>
            `;
            document.getElementById('paginacion').innerHTML = '';
            return;
        }
        
        tbody.innerHTML = procesos.map(p => `
            <tr>
                <td>
                    <a href="${CONFIG.adminUrl}/index.php?page=procesos&action=ver&id=${p.id}" class="fw-semibold text-primary">
                        ${p.codigo}
                    </a>
                </td>
                <td>
                    <span class="badge badge-estado-${p.estado}">${nombresEstados[p.estado] || p.estado}</span>
                </td>
                <td><span class="text-capitalize">${p.tipo}</span></td>
                <td>
                    <span class="badge ${getPrioridadClass(p.prioridad)}">${p.prioridad}</span>
                </td>
                <td>
                    <small class="text-muted">${formatDate(p.fecha_creacion)}</small>
                </td>
                <td>
                    <small class="text-muted">${p.fecha_actualizacion ? formatDate(p.fecha_actualizacion) : '-'}</small>
                </td>
                <td class="text-end">
                    <div class="btn-group btn-group-sm">
                        <a href="${CONFIG.adminUrl}/index.php?page=procesos&action=ver&id=${p.id}" 
                           class="btn btn-outline-primary" title="Ver">
                            <i class="bi bi-eye"></i>
                        </a>
                        ${p.estado === 'analizado' ? `
                            <a href="${CONFIG.adminUrl}/index.php?page=procesos&action=validar&id=${p.id}" 
                               class="btn btn-outline-success" title="Validar">
                                <i class="bi bi-check-lg"></i>
                            </a>
                        ` : ''}
                        ${['creado', 'cancelado', 'error_analisis'].includes(p.estado) ? `
                            <button onclick="eliminarProceso(${p.id}, '${p.codigo}')" 
                                    class="btn btn-outline-danger" title="Eliminar">
                                <i class="bi bi-trash"></i>
                            </button>
                        ` : ''}
                    </div>
                </td>
            </tr>
        `).join('');
        
        // Paginación
        renderPagination(pagination);
        
    } catch (error) {
        console.error('Error:', error);
        tbody.innerHTML = `
            <tr>
                <td colspan="7" class="text-center py-5 text-danger">
                    <i class="bi bi-exclamation-triangle fs-1 d-block mb-2"></i>
                    Error al cargar los procesos
                </td>
            </tr>
        `;
    }
}

function renderPagination(pagination) {
    const container = document.getElementById('paginacion');
    if (!pagination || pagination.total_pages <= 1) {
        container.innerHTML = '';
        return;
    }
    
    let html = '';
    
    // Anterior
    html += `
        <li class="page-item ${pagination.current_page <= 1 ? 'disabled' : ''}">
            <a class="page-link" href="#" onclick="loadProcesos(${pagination.current_page - 1}); return false;">
                <i class="bi bi-chevron-left"></i>
            </a>
        </li>
    `;
    
    // Páginas
    for (let i = 1; i <= pagination.total_pages; i++) {
        if (i === 1 || i === pagination.total_pages || 
            (i >= pagination.current_page - 2 && i <= pagination.current_page + 2)) {
            html += `
                <li class="page-item ${i === pagination.current_page ? 'active' : ''}">
                    <a class="page-link" href="#" onclick="loadProcesos(${i}); return false;">${i}</a>
                </li>
            `;
        } else if (i === pagination.current_page - 3 || i === pagination.current_page + 3) {
            html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
        }
    }
    
    // Siguiente
    html += `
        <li class="page-item ${pagination.current_page >= pagination.total_pages ? 'disabled' : ''}">
            <a class="page-link" href="#" onclick="loadProcesos(${pagination.current_page + 1}); return false;">
                <i class="bi bi-chevron-right"></i>
            </a>
        </li>
    `;
    
    container.innerHTML = html;
}

function getPrioridadClass(prioridad) {
    if (prioridad <= 3) return 'bg-danger';
    if (prioridad <= 6) return 'bg-warning text-dark';
    return 'bg-secondary';
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    const date = new Date(dateStr);
    return date.toLocaleDateString('es-CO', { 
        day: '2-digit', 
        month: 'short', 
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

async function eliminarProceso(id, codigo) {
    if (!confirm(`¿Está seguro de eliminar el proceso ${codigo}?\n\nEsta acción no se puede deshacer.`)) {
        return;
    }
    
    try {
        const response = await fetch(`${CONFIG.apiUrl}/casos/${id}`, {
            method: 'DELETE',
            credentials: 'include'
        });
        
        if (!response.ok) {
            const data = await response.json();
            throw new Error(data.message || 'Error al eliminar');
        }
        
        showAlert('Proceso eliminado correctamente', 'success');
        loadProcesos(currentPage);
        
    } catch (error) {
        showAlert(error.message, 'danger');
    }
}

function showAlert(message, type = 'info') {
    const container = document.getElementById('alertContainer');
    const alert = document.createElement('div');
    alert.className = `alert alert-${type} alert-dismissible fade show`;
    alert.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    container.prepend(alert);
    
    setTimeout(() => {
        alert.classList.remove('show');
        setTimeout(() => alert.remove(), 150);
    }, 5000);
}
JS;

include ADMIN_LAYOUTS . '/footer.php';

