<?php
/**
 * Ver Detalle de Proceso
 */

$procesoId = $_GET['id'] ?? null;

if (!$procesoId) {
    setFlashMessage('ID de proceso no especificado', 'danger');
    redirect('index.php?page=procesos');
}

$pageTitle = 'Detalle del Proceso';
$pageDescription = 'Información completa del proceso';

include ADMIN_LAYOUTS . '/header.php';
?>

<!-- Breadcrumb -->
<nav aria-label="breadcrumb" class="mb-4">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= adminUrl('index.php') ?>">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="<?= adminUrl('index.php?page=procesos') ?>">Procesos</a></li>
        <li class="breadcrumb-item active" id="breadcrumbCodigo">Cargando...</li>
    </ol>
</nav>

<!-- Contenido Principal -->
<div class="row" id="contenidoProceso">
    <div class="col-12 text-center py-5">
        <div class="spinner-border text-primary" role="status"></div>
        <p class="mt-2 text-muted">Cargando información del proceso...</p>
    </div>
</div>

<!-- Modal para editar datos -->
<div class="modal fade" id="modalEditarDatos" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Editar Datos</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="modalEditarBody">
                <!-- Contenido dinámico -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btnGuardarDatos">Guardar Cambios</button>
            </div>
        </div>
    </div>
</div>

<?php
$inlineJs = <<<'JS'
const procesoId = <?= json_encode($procesoId) ?>;
let procesoData = null;

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

document.addEventListener('DOMContentLoaded', function() {
    loadProceso();
});

async function loadProceso() {
    try {
        const response = await fetch(`${CONFIG.apiUrl}/casos/${procesoId}`, {
            credentials: 'include'
        });
        
        if (!response.ok) {
            if (response.status === 404) {
                throw new Error('Proceso no encontrado');
            }
            throw new Error('Error al cargar el proceso');
        }
        
        const { data } = await response.json();
        procesoData = data;
        
        document.getElementById('breadcrumbCodigo').textContent = data.codigo;
        document.title = `${data.codigo} - ByBot`;
        
        renderProceso(data);
        
    } catch (error) {
        document.getElementById('contenidoProceso').innerHTML = `
            <div class="col-12">
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    ${error.message}
                </div>
                <a href="${CONFIG.adminUrl}/index.php?page=procesos" class="btn btn-primary">
                    <i class="bi bi-arrow-left me-1"></i> Volver a la lista
                </a>
            </div>
        `;
    }
}

function renderProceso(data) {
    const container = document.getElementById('contenidoProceso');
    
    container.innerHTML = `
        <div class="col-lg-8">
            <!-- Header del proceso -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h4 class="mb-1">${data.codigo}</h4>
                            <span class="badge badge-estado-${data.estado} fs-6">${nombresEstados[data.estado] || data.estado}</span>
                            <span class="badge bg-secondary ms-2">${data.tipo}</span>
                        </div>
                        <div class="btn-group">
                            ${renderAcciones(data)}
                        </div>
                    </div>
                    
                    <!-- Barra de progreso -->
                    <div class="mt-4">
                        ${renderProgressBar(data.estado)}
                    </div>
                </div>
            </div>
            
            <!-- Tabs -->
            <ul class="nav nav-tabs" role="tablist">
                <li class="nav-item">
                    <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabInfo">
                        <i class="bi bi-info-circle me-1"></i>Información
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabArchivos">
                        <i class="bi bi-paperclip me-1"></i>Archivos
                        <span class="badge bg-secondary ms-1">${data.anexos?.length || 0}</span>
                    </button>
                </li>
                ${data.datos_ia ? `
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabDatosIA">
                        <i class="bi bi-robot me-1"></i>Datos IA
                    </button>
                </li>
                ` : ''}
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabHistorial">
                        <i class="bi bi-clock-history me-1"></i>Historial
                    </button>
                </li>
            </ul>
            
            <div class="tab-content">
                <!-- Tab Info -->
                <div class="tab-pane fade show active" id="tabInfo">
                    <div class="card border-top-0 rounded-top-0">
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label text-muted small">Creado por</label>
                                    <p class="mb-0">${data.creador_nombre || 'N/A'}</p>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-muted small">Fecha de creación</label>
                                    <p class="mb-0">${formatDate(data.fecha_creacion)}</p>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-muted small">Prioridad</label>
                                    <p class="mb-0">
                                        <span class="badge ${getPrioridadClass(data.prioridad)}">${data.prioridad}</span>
                                        <small class="text-muted ms-1">(1=máxima, 10=mínima)</small>
                                    </p>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-muted small">Última actualización</label>
                                    <p class="mb-0">${data.fecha_actualizacion ? formatDate(data.fecha_actualizacion) : 'N/A'}</p>
                                </div>
                                ${data.notas ? `
                                <div class="col-12">
                                    <label class="form-label text-muted small">Notas</label>
                                    <p class="mb-0">${escapeHtml(data.notas)}</p>
                                </div>
                                ` : ''}
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Tab Archivos -->
                <div class="tab-pane fade" id="tabArchivos">
                    <div class="card border-top-0 rounded-top-0">
                        <div class="card-body">
                            ${renderArchivos(data.anexos || [])}
                        </div>
                    </div>
                </div>
                
                <!-- Tab Datos IA -->
                ${data.datos_ia ? `
                <div class="tab-pane fade" id="tabDatosIA">
                    <div class="card border-top-0 rounded-top-0">
                        <div class="card-body">
                            ${renderDatosIA(data.datos_ia)}
                        </div>
                    </div>
                </div>
                ` : ''}
                
                <!-- Tab Historial -->
                <div class="tab-pane fade" id="tabHistorial">
                    <div class="card border-top-0 rounded-top-0">
                        <div class="card-body">
                            ${renderHistorial(data.historial || [])}
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Acciones rápidas -->
            <div class="card mb-3">
                <div class="card-header">
                    <i class="bi bi-lightning me-2"></i>Acciones
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        ${renderAccionesRapidas(data)}
                    </div>
                </div>
            </div>
            
            <!-- Información adicional -->
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-gear me-2"></i>Detalles Técnicos
                </div>
                <div class="card-body small">
                    <div class="mb-2">
                        <strong>ID:</strong> ${data.id}
                    </div>
                    <div class="mb-2">
                        <strong>Intentos análisis:</strong> ${data.intentos_analisis || 0}/${data.max_intentos || 3}
                    </div>
                    <div class="mb-2">
                        <strong>Intentos llenado:</strong> ${data.intentos_llenado || 0}/${data.max_intentos || 3}
                    </div>
                    ${data.fecha_analisis ? `
                    <div class="mb-2">
                        <strong>Analizado:</strong> ${formatDate(data.fecha_analisis)}
                    </div>
                    ` : ''}
                    ${data.fecha_validacion ? `
                    <div class="mb-2">
                        <strong>Validado:</strong> ${formatDate(data.fecha_validacion)}
                    </div>
                    ` : ''}
                    ${data.fecha_completado ? `
                    <div class="mb-2">
                        <strong>Completado:</strong> ${formatDate(data.fecha_completado)}
                    </div>
                    ` : ''}
                </div>
            </div>
        </div>
    `;
}

function renderProgressBar(estado) {
    const pasos = [
        { key: 'creado', label: 'Creado', icon: 'plus-circle' },
        { key: 'analizado', label: 'Analizado', icon: 'robot' },
        { key: 'validado', label: 'Validado', icon: 'check-circle' },
        { key: 'completado', label: 'Completado', icon: 'flag' }
    ];
    
    const estadosPorPaso = {
        creado: ['creado', 'en_cola_analisis', 'analizando'],
        analizado: ['analizado'],
        validado: ['validado', 'en_cola_llenado', 'llenando'],
        completado: ['completado']
    };
    
    const errores = ['error_analisis', 'error_llenado', 'cancelado'];
    const esError = errores.includes(estado);
    
    let pasoActual = 0;
    pasos.forEach((paso, i) => {
        if (estadosPorPaso[paso.key]?.includes(estado)) pasoActual = i;
        if (paso.key === 'completado' && estado === 'completado') pasoActual = i;
    });
    
    return `
        <div class="d-flex justify-content-between position-relative">
            ${pasos.map((paso, i) => {
                const completado = i < pasoActual || (i === pasoActual && estado === 'completado');
                const activo = i === pasoActual && estado !== 'completado';
                const error = esError && i === pasoActual;
                
                return `
                    <div class="text-center flex-fill">
                        <div class="process-step-icon mx-auto mb-2 ${completado ? 'completed' : ''} ${activo ? 'active' : ''} ${error ? 'error' : ''}" 
                             style="width:40px;height:40px;">
                            <i class="bi bi-${error ? 'x-lg' : completado ? 'check-lg' : paso.icon}"></i>
                        </div>
                        <small class="${completado || activo ? 'fw-semibold' : 'text-muted'}">${paso.label}</small>
                    </div>
                `;
            }).join('')}
        </div>
    `;
}

function renderAcciones(data) {
    let html = '';
    
    if (['creado', 'error_analisis'].includes(data.estado)) {
        html += `<button class="btn btn-primary" onclick="encolarAnalisis()">
            <i class="bi bi-play-fill me-1"></i>Analizar
        </button>`;
    }
    
    if (data.estado === 'analizado') {
        html += `<a href="${CONFIG.adminUrl}/index.php?page=procesos&action=validar&id=${data.id}" class="btn btn-success">
            <i class="bi bi-check-lg me-1"></i>Validar
        </a>`;
    }
    
    if (['validado', 'error_llenado'].includes(data.estado)) {
        html += `<button class="btn btn-primary" onclick="encolarLlenado()">
            <i class="bi bi-file-earmark-pdf me-1"></i>Generar Pagaré
        </button>`;
    }
    
    return html;
}

function renderAccionesRapidas(data) {
    let html = '';
    
    if (['creado', 'error_analisis'].includes(data.estado)) {
        html += `<button class="btn btn-outline-primary" onclick="encolarAnalisis()">
            <i class="bi bi-robot me-1"></i>Encolar para Análisis
        </button>`;
    }
    
    if (data.estado === 'analizado') {
        html += `<a href="${CONFIG.adminUrl}/index.php?page=procesos&action=validar&id=${data.id}" class="btn btn-outline-success">
            <i class="bi bi-check-circle me-1"></i>Validar Datos
        </a>`;
    }
    
    if (['validado', 'error_llenado'].includes(data.estado)) {
        html += `<button class="btn btn-outline-primary" onclick="encolarLlenado()">
            <i class="bi bi-file-pdf me-1"></i>Generar Pagaré Llenado
        </button>`;
    }
    
    if (data.archivo_pagare_llenado) {
        html += `<a href="${CONFIG.apiUrl}/archivos/descargar/${getArchivoIdByTipo(data.anexos, 'pagare_llenado')}" class="btn btn-success" target="_blank">
            <i class="bi bi-download me-1"></i>Descargar Pagaré Llenado
        </a>`;
    }
    
    if (!['completado', 'cancelado'].includes(data.estado)) {
        html += `<button class="btn btn-outline-danger" onclick="cancelarProceso()">
            <i class="bi bi-x-circle me-1"></i>Cancelar Proceso
        </button>`;
    }
    
    return html || '<p class="text-muted text-center mb-0">No hay acciones disponibles</p>';
}

function renderArchivos(anexos) {
    if (!anexos.length) {
        return '<p class="text-muted text-center">No hay archivos</p>';
    }
    
    return `
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Archivo</th>
                        <th>Tipo</th>
                        <th>Tamaño</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    ${anexos.map(a => `
                        <tr>
                            <td>
                                <i class="bi ${a.mime_type?.includes('pdf') ? 'bi-file-pdf text-danger' : 'bi-file-image text-primary'} me-2"></i>
                                ${escapeHtml(a.nombre_original)}
                            </td>
                            <td><span class="badge bg-secondary">${a.tipo}</span></td>
                            <td>${formatFileSize(a.tamanio_bytes)}</td>
                            <td class="text-end">
                                <a href="${CONFIG.apiUrl}/archivos/descargar/${a.id}" class="btn btn-sm btn-outline-primary" target="_blank">
                                    <i class="bi bi-download"></i>
                                </a>
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>
    `;
}

function renderDatosIA(datosIA) {
    const datos = datosIA.datos_validados || datosIA.datos_originales;
    if (!datos) return '<p class="text-muted">No hay datos de IA</p>';
    
    let html = `
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <span class="badge bg-${datosIA.datos_validados ? 'success' : 'warning'}">
                    ${datosIA.datos_validados ? 'Datos Validados' : 'Datos Originales'}
                </span>
                <small class="text-muted ms-2">Versión ${datosIA.version}</small>
            </div>
            <small class="text-muted">Analizado: ${formatDate(datosIA.fecha_analisis)}</small>
        </div>
    `;
    
    // Estado de cuenta
    if (datos.estado_cuenta) {
        html += `
            <h6><i class="bi bi-file-text me-2"></i>Estado de Cuenta</h6>
            <div class="row g-2 mb-4">
                ${Object.entries(datos.estado_cuenta).map(([k, v]) => `
                    <div class="col-md-4">
                        <div class="p-2 bg-light rounded">
                            <small class="text-muted d-block">${formatFieldName(k)}</small>
                            <strong>${formatValue(k, v)}</strong>
                        </div>
                    </div>
                `).join('')}
            </div>
        `;
    }
    
    // Deudor
    if (datos.deudor) {
        html += `
            <h6><i class="bi bi-person me-2"></i>Deudor</h6>
            <div class="row g-2 mb-4">
                ${Object.entries(datos.deudor).map(([k, v]) => `
                    <div class="col-md-4">
                        <div class="p-2 bg-light rounded">
                            <small class="text-muted d-block">${formatFieldName(k)}</small>
                            <strong>${escapeHtml(v) || '-'}</strong>
                        </div>
                    </div>
                `).join('')}
            </div>
        `;
    }
    
    // Codeudor
    if (datos.codeudor) {
        html += `
            <h6><i class="bi bi-person me-2"></i>Codeudor</h6>
            <div class="row g-2">
                ${Object.entries(datos.codeudor).map(([k, v]) => `
                    <div class="col-md-4">
                        <div class="p-2 bg-light rounded">
                            <small class="text-muted d-block">${formatFieldName(k)}</small>
                            <strong>${escapeHtml(v) || '-'}</strong>
                        </div>
                    </div>
                `).join('')}
            </div>
        `;
    }
    
    return html;
}

function renderHistorial(historial) {
    if (!historial.length) {
        return '<p class="text-muted text-center">No hay historial</p>';
    }
    
    return `
        <div class="timeline">
            ${historial.map(h => `
                <div class="timeline-item ${h.accion.includes('error') ? 'danger' : h.accion === 'completado' ? 'success' : ''}">
                    <div class="timeline-time">${formatDate(h.fecha)}</div>
                    <div class="timeline-content">
                        <strong class="d-block">${h.accion}</strong>
                        <small class="text-muted">${escapeHtml(h.descripcion || '')}</small>
                        <div class="mt-1">
                            <small class="text-muted">
                                <i class="bi bi-person me-1"></i>${h.usuario_nombre || 'Sistema'}
                            </small>
                        </div>
                    </div>
                </div>
            `).join('')}
        </div>
    `;
}

// Utilidades
function formatDate(dateStr) {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('es-CO', {
        day: '2-digit', month: 'short', year: 'numeric',
        hour: '2-digit', minute: '2-digit'
    });
}

function formatFileSize(bytes) {
    if (!bytes) return '-';
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
}

function formatFieldName(name) {
    return name.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
}

function formatValue(key, value) {
    if (value === null || value === undefined) return '-';
    if (key.includes('capital') || key.includes('interes') || key.includes('total') || key.includes('honorarios') || key.includes('gastos')) {
        return '$' + parseFloat(value).toLocaleString('es-CO');
    }
    if (key.includes('tasa')) {
        return parseFloat(value).toFixed(2) + '%';
    }
    return escapeHtml(value);
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function getPrioridadClass(p) {
    if (p <= 3) return 'bg-danger';
    if (p <= 6) return 'bg-warning text-dark';
    return 'bg-secondary';
}

function getArchivoIdByTipo(anexos, tipo) {
    const archivo = anexos?.find(a => a.tipo === tipo);
    return archivo?.id;
}

// Acciones
async function encolarAnalisis() {
    if (!confirm('¿Desea encolar este proceso para análisis?')) return;
    
    try {
        const response = await fetch(`${CONFIG.apiUrl}/casos/${procesoId}/encolar-analisis`, {
            method: 'POST',
            credentials: 'include'
        });
        
        if (!response.ok) throw new Error('Error al encolar');
        
        showAlert('Proceso encolado para análisis', 'success');
        loadProceso();
        
    } catch (error) {
        showAlert(error.message, 'danger');
    }
}

async function encolarLlenado() {
    if (!confirm('¿Desea encolar este proceso para generar el pagaré?')) return;
    
    try {
        const response = await fetch(`${CONFIG.apiUrl}/casos/${procesoId}/encolar-llenado`, {
            method: 'POST',
            credentials: 'include'
        });
        
        if (!response.ok) throw new Error('Error al encolar');
        
        showAlert('Proceso encolado para llenado', 'success');
        loadProceso();
        
    } catch (error) {
        showAlert(error.message, 'danger');
    }
}

async function cancelarProceso() {
    const motivo = prompt('Indique el motivo de cancelación (opcional):');
    if (motivo === null) return;
    
    try {
        const response = await fetch(`${CONFIG.apiUrl}/casos/${procesoId}/cancelar`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ motivo })
        });
        
        if (!response.ok) throw new Error('Error al cancelar');
        
        showAlert('Proceso cancelado', 'warning');
        loadProceso();
        
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
    setTimeout(() => { alert.classList.remove('show'); setTimeout(() => alert.remove(), 150); }, 5000);
}
JS;

include ADMIN_LAYOUTS . '/footer.php';

