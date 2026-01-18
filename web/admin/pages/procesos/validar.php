<?php
/**
 * Validar Datos de IA de un Proceso
 */

$procesoId = $_GET['id'] ?? null;

if (!$procesoId) {
    setFlashMessage('ID de proceso no especificado', 'danger');
    redirect('index.php?page=procesos');
}

$pageTitle = 'Validar Datos IA';
$pageDescription = 'Revisar y validar los datos extraídos por la IA';

include ADMIN_LAYOUTS . '/header.php';
?>

<!-- Breadcrumb -->
<nav aria-label="breadcrumb" class="mb-4">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= adminUrl('index.php') ?>">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="<?= adminUrl('index.php?page=procesos') ?>">Procesos</a></li>
        <li class="breadcrumb-item"><a href="<?= adminUrl('index.php?page=procesos&action=ver&id=' . $procesoId) ?>" id="breadcrumbCodigo">Proceso</a></li>
        <li class="breadcrumb-item active">Validar</li>
    </ol>
</nav>

<div class="row" id="contenidoValidacion">
    <div class="col-12 text-center py-5">
        <div class="spinner-border text-primary" role="status"></div>
        <p class="mt-2 text-muted">Cargando datos para validación...</p>
    </div>
</div>

<?php
$inlineJs = <<<'JS'
const procesoId = <?= json_encode($procesoId) ?>;
let procesoData = null;
let datosEditados = {};

// Definición de campos por sección
const camposPorSeccion = {
    estado_cuenta: [
        { key: 'capital', label: 'Capital', type: 'currency' },
        { key: 'intereses_corrientes', label: 'Intereses Corrientes', type: 'currency' },
        { key: 'intereses_mora', label: 'Intereses Mora', type: 'currency' },
        { key: 'tasa_interes', label: 'Tasa de Interés', type: 'percentage' },
        { key: 'tasa_mora', label: 'Tasa de Mora', type: 'percentage' },
        { key: 'honorarios', label: 'Honorarios', type: 'currency' },
        { key: 'gastos', label: 'Gastos', type: 'currency' },
        { key: 'total', label: 'Total', type: 'currency' },
        { key: 'fecha_corte', label: 'Fecha de Corte', type: 'date' },
        { key: 'numero_credito', label: 'Número de Crédito', type: 'text' }
    ],
    deudor: [
        { key: 'nombre_completo', label: 'Nombre Completo', type: 'text' },
        { key: 'tipo_documento', label: 'Tipo de Documento', type: 'select', options: ['CC', 'CE', 'NIT', 'PA', 'TI'] },
        { key: 'numero_documento', label: 'Número de Documento', type: 'text' },
        { key: 'direccion', label: 'Dirección', type: 'text' },
        { key: 'ciudad', label: 'Ciudad', type: 'text' },
        { key: 'departamento', label: 'Departamento', type: 'text' },
        { key: 'telefono', label: 'Teléfono', type: 'text' },
        { key: 'email', label: 'Email', type: 'email' }
    ],
    codeudor: [
        { key: 'nombre_completo', label: 'Nombre Completo', type: 'text' },
        { key: 'tipo_documento', label: 'Tipo de Documento', type: 'select', options: ['CC', 'CE', 'NIT', 'PA', 'TI'] },
        { key: 'numero_documento', label: 'Número de Documento', type: 'text' },
        { key: 'direccion', label: 'Dirección', type: 'text' },
        { key: 'ciudad', label: 'Ciudad', type: 'text' },
        { key: 'departamento', label: 'Departamento', type: 'text' },
        { key: 'telefono', label: 'Teléfono', type: 'text' },
        { key: 'email', label: 'Email', type: 'email' }
    ]
};

document.addEventListener('DOMContentLoaded', function() {
    loadProceso();
});

async function loadProceso() {
    try {
        const response = await fetch(`${CONFIG.apiUrl}/procesos/${procesoId}`, {
            credentials: 'include'
        });
        
        if (!response.ok) throw new Error('Error al cargar el proceso');
        
        const { data } = await response.json();
        procesoData = data;
        
        document.getElementById('breadcrumbCodigo').textContent = data.codigo;
        
        if (!data.datos_ia) {
            throw new Error('Este proceso no tiene datos de IA para validar');
        }
        
        if (!['analizado'].includes(data.estado)) {
            throw new Error('Este proceso no está en estado para validación');
        }
        
        // Inicializar datos editados con los datos originales o validados
        datosEditados = JSON.parse(JSON.stringify(data.datos_ia.datos_validados || data.datos_ia.datos_originales));
        
        renderValidacion(data);
        
    } catch (error) {
        document.getElementById('contenidoValidacion').innerHTML = `
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

function renderValidacion(data) {
    const container = document.getElementById('contenidoValidacion');
    
    container.innerHTML = `
        <div class="col-lg-8">
            <!-- Header -->
            <div class="card mb-4 border-primary">
                <div class="card-header bg-primary text-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <span>
                            <i class="bi bi-robot me-2"></i>
                            Validación de Datos - ${data.codigo}
                        </span>
                        <span class="badge bg-light text-primary">
                            Versión ${data.datos_ia.version}
                        </span>
                    </div>
                </div>
                <div class="card-body">
                    <p class="mb-0">
                        <i class="bi bi-info-circle me-1"></i>
                        Revise y corrija los datos extraídos por la IA. Los campos modificados se marcarán automáticamente.
                    </p>
                </div>
            </div>
            
            <form id="formValidacion">
                <!-- Estado de Cuenta -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="bi bi-file-text me-2"></i>Estado de Cuenta
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            ${renderCamposSeccion('estado_cuenta')}
                        </div>
                    </div>
                </div>
                
                <!-- Deudor -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="bi bi-person me-2"></i>Deudor
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            ${renderCamposSeccion('deudor')}
                        </div>
                    </div>
                </div>
                
                <!-- Codeudor -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-people me-2"></i>Codeudor</span>
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" id="tieneCodeudor" 
                                   ${datosEditados.codeudor ? 'checked' : ''} onchange="toggleCodeudor()">
                            <label class="form-check-label" for="tieneCodeudor">Tiene codeudor</label>
                        </div>
                    </div>
                    <div class="card-body" id="seccionCodeudor" style="${datosEditados.codeudor ? '' : 'display:none'}">
                        <div class="row g-3">
                            ${renderCamposSeccion('codeudor')}
                        </div>
                    </div>
                </div>
                
                <!-- Acciones -->
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex gap-2 justify-content-between">
                            <div>
                                <button type="button" class="btn btn-outline-warning" onclick="solicitarReanalisis()">
                                    <i class="bi bi-arrow-repeat me-1"></i>Solicitar Re-análisis
                                </button>
                            </div>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-outline-secondary" onclick="guardarBorrador()">
                                    <i class="bi bi-save me-1"></i>Guardar Borrador
                                </button>
                                <button type="submit" class="btn btn-success">
                                    <i class="bi bi-check-lg me-1"></i>Confirmar Validación
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Sidebar con documentos -->
        <div class="col-lg-4">
            <div class="card sticky-top" style="top: 20px;">
                <div class="card-header">
                    <i class="bi bi-file-earmark-pdf me-2"></i>Documentos
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        ${(data.anexos || []).map(a => `
                            <a href="${CONFIG.apiUrl}/archivos/descargar/${a.id}" target="_blank" 
                               class="list-group-item list-group-item-action">
                                <i class="bi ${a.mime_type?.includes('pdf') ? 'bi-file-pdf text-danger' : 'bi-file-image text-primary'} me-2"></i>
                                <span class="text-truncate d-inline-block" style="max-width: 200px;">${escapeHtml(a.nombre_original)}</span>
                                <span class="badge bg-secondary float-end">${a.tipo}</span>
                            </a>
                        `).join('')}
                    </div>
                </div>
            </div>
            
            <div class="card mt-3">
                <div class="card-header">
                    <i class="bi bi-clock-history me-2"></i>Información
                </div>
                <div class="card-body small">
                    <p class="mb-2">
                        <strong>Analizado:</strong><br>
                        ${formatDate(data.datos_ia.fecha_analisis)}
                    </p>
                    ${data.datos_ia.fecha_validacion ? `
                    <p class="mb-2">
                        <strong>Última edición:</strong><br>
                        ${formatDate(data.datos_ia.fecha_validacion)}
                    </p>
                    ` : ''}
                    <p class="mb-0">
                        <strong>Confianza IA:</strong><br>
                        ${data.datos_ia.metadata?.confidence ? (data.datos_ia.metadata.confidence * 100).toFixed(0) + '%' : 'N/A'}
                    </p>
                </div>
            </div>
        </div>
    `;
    
    // Inicializar eventos
    initFormEvents();
}

function renderCamposSeccion(seccion) {
    const campos = camposPorSeccion[seccion] || [];
    const datos = datosEditados[seccion] || {};
    
    return campos.map(campo => {
        const valor = datos[campo.key] ?? '';
        const valorOriginal = procesoData.datos_ia.datos_originales?.[seccion]?.[campo.key] ?? '';
        const modificado = String(valor) !== String(valorOriginal);
        
        let inputHtml = '';
        
        switch (campo.type) {
            case 'select':
                inputHtml = `
                    <select class="form-select ${modificado ? 'border-warning' : ''}" 
                            name="${seccion}.${campo.key}" 
                            data-original="${escapeHtml(valorOriginal)}">
                        <option value="">Seleccione...</option>
                        ${campo.options.map(opt => `
                            <option value="${opt}" ${valor === opt ? 'selected' : ''}>${opt}</option>
                        `).join('')}
                    </select>
                `;
                break;
            case 'currency':
                inputHtml = `
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input type="number" step="0.01" class="form-control ${modificado ? 'border-warning' : ''}" 
                               name="${seccion}.${campo.key}" 
                               value="${valor}"
                               data-original="${valorOriginal}">
                    </div>
                `;
                break;
            case 'percentage':
                inputHtml = `
                    <div class="input-group">
                        <input type="number" step="0.01" class="form-control ${modificado ? 'border-warning' : ''}" 
                               name="${seccion}.${campo.key}" 
                               value="${valor}"
                               data-original="${valorOriginal}">
                        <span class="input-group-text">%</span>
                    </div>
                `;
                break;
            default:
                inputHtml = `
                    <input type="${campo.type}" class="form-control ${modificado ? 'border-warning' : ''}" 
                           name="${seccion}.${campo.key}" 
                           value="${escapeHtml(valor)}"
                           data-original="${escapeHtml(valorOriginal)}">
                `;
        }
        
        return `
            <div class="col-md-6">
                <label class="form-label">
                    ${campo.label}
                    ${modificado ? '<span class="badge bg-warning text-dark ms-1">Modificado</span>' : ''}
                </label>
                ${inputHtml}
                ${valorOriginal && modificado ? `<small class="text-muted">Original: ${formatOriginal(campo.type, valorOriginal)}</small>` : ''}
            </div>
        `;
    }).join('');
}

function formatOriginal(type, value) {
    if (!value) return '-';
    if (type === 'currency') return '$' + parseFloat(value).toLocaleString('es-CO');
    if (type === 'percentage') return value + '%';
    return escapeHtml(value);
}

function initFormEvents() {
    const form = document.getElementById('formValidacion');
    
    // Actualizar datosEditados al cambiar un campo
    form.querySelectorAll('input, select').forEach(input => {
        input.addEventListener('change', function() {
            const [seccion, campo] = this.name.split('.');
            if (!datosEditados[seccion]) datosEditados[seccion] = {};
            datosEditados[seccion][campo] = this.value;
            
            // Marcar si está modificado
            const original = this.dataset.original;
            if (this.value !== original) {
                this.classList.add('border-warning');
            } else {
                this.classList.remove('border-warning');
            }
        });
    });
    
    // Submit del form
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        await confirmarValidacion();
    });
}

function toggleCodeudor() {
    const seccion = document.getElementById('seccionCodeudor');
    const checked = document.getElementById('tieneCodeudor').checked;
    
    seccion.style.display = checked ? '' : 'none';
    
    if (!checked) {
        delete datosEditados.codeudor;
    } else if (!datosEditados.codeudor) {
        datosEditados.codeudor = {};
    }
}

async function guardarBorrador() {
    try {
        const response = await fetch(`${CONFIG.apiUrl}/validacion/${procesoId}/guardar`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ datos: datosEditados })
        });
        
        if (!response.ok) throw new Error('Error al guardar');
        
        showAlert('Borrador guardado correctamente', 'success');
        
    } catch (error) {
        showAlert(error.message, 'danger');
    }
}

async function confirmarValidacion() {
    if (!confirm('¿Está seguro de confirmar la validación? El proceso pasará al siguiente estado.')) {
        return;
    }
    
    try {
        const response = await fetch(`${CONFIG.apiUrl}/validacion/${procesoId}/confirmar`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ datos: datosEditados })
        });
        
        if (!response.ok) throw new Error('Error al confirmar validación');
        
        showAlert('Validación confirmada exitosamente', 'success');
        
        setTimeout(() => {
            window.location.href = `${CONFIG.adminUrl}/index.php?page=procesos&action=ver&id=${procesoId}`;
        }, 1500);
        
    } catch (error) {
        showAlert(error.message, 'danger');
    }
}

async function solicitarReanalisis() {
    if (!confirm('¿Desea solicitar un nuevo análisis por IA? Los datos actuales se mantendrán como referencia.')) {
        return;
    }
    
    try {
        const response = await fetch(`${CONFIG.apiUrl}/validacion/${procesoId}/reanalizar`, {
            method: 'POST',
            credentials: 'include'
        });
        
        if (!response.ok) throw new Error('Error al solicitar re-análisis');
        
        showAlert('Re-análisis solicitado. El proceso volverá a la cola.', 'info');
        
        setTimeout(() => {
            window.location.href = `${CONFIG.adminUrl}/index.php?page=procesos&action=ver&id=${procesoId}`;
        }, 1500);
        
    } catch (error) {
        showAlert(error.message, 'danger');
    }
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('es-CO', {
        day: '2-digit', month: 'short', year: 'numeric',
        hour: '2-digit', minute: '2-digit'
    });
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function showAlert(message, type = 'info') {
    const container = document.getElementById('alertContainer');
    const alert = document.createElement('div');
    alert.className = `alert alert-${type} alert-dismissible fade show`;
    alert.innerHTML = `${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
    container.prepend(alert);
}
JS;

include ADMIN_LAYOUTS . '/footer.php';

