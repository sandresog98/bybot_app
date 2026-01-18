<?php
/**
 * Gestión de Plantillas de Pagaré
 */

$pageTitle = 'Plantillas de Pagaré';
$pageDescription = 'Configuración de plantillas para llenado de documentos';

include ADMIN_LAYOUTS . '/header.php';
?>

<!-- Breadcrumb -->
<nav aria-label="breadcrumb" class="mb-4">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= adminUrl('index.php') ?>">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="<?= adminUrl('index.php?page=configuracion') ?>">Configuración</a></li>
        <li class="breadcrumb-item active">Plantillas</li>
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
    <li class="nav-item">
        <a class="nav-link active" href="<?= adminUrl('index.php?page=configuracion&action=plantillas') ?>">
            <i class="bi bi-file-earmark-pdf me-1"></i>Plantillas
        </a>
    </li>
    <?php if (hasAccess('configuracion.colas')): ?>
    <li class="nav-item">
        <a class="nav-link" href="<?= adminUrl('index.php?page=configuracion&action=colas') ?>">
            <i class="bi bi-stack me-1"></i>Colas
        </a>
    </li>
    <?php endif; ?>
</ul>

<div class="row">
    <div class="col-lg-4">
        <!-- Lista de plantillas -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-file-earmark-pdf me-2"></i>Plantillas</span>
                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalNuevaPlantilla">
                    <i class="bi bi-plus"></i>
                </button>
            </div>
            <div class="list-group list-group-flush" id="listaPlantillas">
                <div class="text-center py-4">
                    <div class="spinner-border spinner-border-sm"></div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-8">
        <!-- Editor de plantilla -->
        <div class="card">
            <div class="card-header">
                <i class="bi bi-pencil me-2"></i>
                <span id="editorTitulo">Seleccione una plantilla</span>
            </div>
            <div class="card-body" id="editorContainer">
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-file-earmark-pdf fs-1 d-block mb-3"></i>
                    <p>Seleccione una plantilla de la lista para configurar las posiciones de los campos.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Nueva Plantilla -->
<div class="modal fade" id="modalNuevaPlantilla" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Nueva Plantilla</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formNuevaPlantilla" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Código <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="codigo" required 
                               pattern="[a-z_]+" placeholder="ej: pagare_cooperativa">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nombre <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="nombre" required 
                               placeholder="ej: Pagaré Cooperativa ABC">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Archivo PDF Base <span class="text-danger">*</span></label>
                        <input type="file" class="form-control" name="archivo" accept=".pdf" required>
                        <div class="form-text">Suba el PDF vacío que servirá como plantilla base</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Descripción</label>
                        <textarea class="form-control" name="descripcion" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Crear Plantilla</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$inlineJs = <<<'JS'
let plantillas = [];
let plantillaActual = null;

const camposDisponibles = [
    { key: 'deudor_nombre', label: 'Deudor - Nombre', tipo: 'texto' },
    { key: 'deudor_documento', label: 'Deudor - Documento', tipo: 'texto' },
    { key: 'deudor_direccion', label: 'Deudor - Dirección', tipo: 'texto' },
    { key: 'deudor_ciudad', label: 'Deudor - Ciudad', tipo: 'texto' },
    { key: 'deudor_telefono', label: 'Deudor - Teléfono', tipo: 'texto' },
    { key: 'codeudor_nombre', label: 'Codeudor - Nombre', tipo: 'texto' },
    { key: 'codeudor_documento', label: 'Codeudor - Documento', tipo: 'texto' },
    { key: 'codeudor_direccion', label: 'Codeudor - Dirección', tipo: 'texto' },
    { key: 'capital', label: 'Capital', tipo: 'moneda' },
    { key: 'capital_letras', label: 'Capital (letras)', tipo: 'texto' },
    { key: 'intereses', label: 'Intereses', tipo: 'moneda' },
    { key: 'total', label: 'Total', tipo: 'moneda' },
    { key: 'total_letras', label: 'Total (letras)', tipo: 'texto' },
    { key: 'tasa_interes', label: 'Tasa de Interés', tipo: 'porcentaje' },
    { key: 'fecha_actual', label: 'Fecha Actual', tipo: 'fecha' },
    { key: 'numero_credito', label: 'Número de Crédito', tipo: 'texto' }
];

document.addEventListener('DOMContentLoaded', function() {
    loadPlantillas();
    initFormNueva();
});

async function loadPlantillas() {
    try {
        const response = await fetch(`${CONFIG.apiUrl}/config/plantillas`, { credentials: 'include' });
        if (!response.ok) throw new Error('Error');
        
        const { data } = await response.json();
        plantillas = data || [];
        renderPlantillas();
        
    } catch (error) {
        document.getElementById('listaPlantillas').innerHTML = `
            <div class="text-center py-4 text-danger">${error.message}</div>
        `;
    }
}

function renderPlantillas() {
    const container = document.getElementById('listaPlantillas');
    
    if (!plantillas.length) {
        container.innerHTML = `<div class="text-center py-4 text-muted">No hay plantillas</div>`;
        return;
    }
    
    container.innerHTML = plantillas.map(p => `
        <a href="#" class="list-group-item list-group-item-action ${plantillaActual?.id === p.id ? 'active' : ''}"
           onclick="seleccionarPlantilla(${p.id}); return false;">
            <div class="d-flex justify-content-between">
                <div>
                    <strong>${escapeHtml(p.nombre)}</strong>
                    <br><small class="text-muted">${p.codigo}</small>
                </div>
                <span class="badge ${p.activa ? 'bg-success' : 'bg-secondary'}">${p.activa ? 'Activa' : 'Inactiva'}</span>
            </div>
        </a>
    `).join('');
}

function seleccionarPlantilla(id) {
    const plantilla = plantillas.find(p => p.id === id);
    if (!plantilla) return;
    
    plantillaActual = plantilla;
    document.getElementById('editorTitulo').textContent = 'Configurar: ' + plantilla.nombre;
    
    renderEditor(plantilla);
    renderPlantillas();
}

function renderEditor(plantilla) {
    const container = document.getElementById('editorContainer');
    const posiciones = plantilla.posiciones || {};
    
    container.innerHTML = `
        <form id="formPosiciones">
            <div class="mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="mb-0">Posiciones de Campos</h6>
                    <a href="${CONFIG.apiUrl}/config/plantillas/${plantilla.id}/preview" target="_blank" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-eye me-1"></i>Vista Previa
                    </a>
                </div>
                <p class="text-muted small">
                    Configure las coordenadas X, Y para cada campo en el PDF. Las coordenadas se miden desde la esquina inferior izquierda.
                </p>
            </div>
            
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Campo</th>
                            <th style="width:80px;">Página</th>
                            <th style="width:80px;">X</th>
                            <th style="width:80px;">Y</th>
                            <th style="width:80px;">Tamaño</th>
                            <th style="width:60px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        ${camposDisponibles.map(campo => {
                            const pos = posiciones[campo.key] || {};
                            return `
                                <tr>
                                    <td>
                                        <small>${campo.label}</small>
                                        <br><code class="small">${campo.key}</code>
                                    </td>
                                    <td>
                                        <input type="number" class="form-control form-control-sm" 
                                               name="${campo.key}_pagina" value="${pos.pagina || 1}" min="1">
                                    </td>
                                    <td>
                                        <input type="number" class="form-control form-control-sm" 
                                               name="${campo.key}_x" value="${pos.x || ''}" step="0.1">
                                    </td>
                                    <td>
                                        <input type="number" class="form-control form-control-sm" 
                                               name="${campo.key}_y" value="${pos.y || ''}" step="0.1">
                                    </td>
                                    <td>
                                        <input type="number" class="form-control form-control-sm" 
                                               name="${campo.key}_size" value="${pos.size || 10}" min="6" max="24">
                                    </td>
                                    <td>
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" 
                                                   name="${campo.key}_activo" ${pos.x ? 'checked' : ''}>
                                        </div>
                                    </td>
                                </tr>
                            `;
                        }).join('')}
                    </tbody>
                </table>
            </div>
            
            <hr>
            
            <div class="d-flex justify-content-between">
                <button type="button" class="btn btn-outline-danger" onclick="eliminarPlantilla()">
                    <i class="bi bi-trash me-1"></i>Eliminar
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-1"></i>Guardar Posiciones
                </button>
            </div>
        </form>
    `;
    
    document.getElementById('formPosiciones').addEventListener('submit', guardarPosiciones);
}

async function guardarPosiciones(e) {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    const posiciones = {};
    
    camposDisponibles.forEach(campo => {
        const activo = formData.get(`${campo.key}_activo`) === 'on';
        if (activo) {
            posiciones[campo.key] = {
                pagina: parseInt(formData.get(`${campo.key}_pagina`)) || 1,
                x: parseFloat(formData.get(`${campo.key}_x`)) || 0,
                y: parseFloat(formData.get(`${campo.key}_y`)) || 0,
                size: parseInt(formData.get(`${campo.key}_size`)) || 10
            };
        }
    });
    
    try {
        const response = await fetch(`${CONFIG.apiUrl}/config/plantillas/${plantillaActual.id}/posiciones`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ posiciones })
        });
        
        if (!response.ok) throw new Error('Error al guardar');
        
        showAlert('Posiciones guardadas correctamente', 'success');
        loadPlantillas();
        
    } catch (error) {
        showAlert(error.message, 'danger');
    }
}

function initFormNueva() {
    document.getElementById('formNuevaPlantilla').addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const formData = new FormData(e.target);
        
        try {
            const response = await fetch(`${CONFIG.apiUrl}/config/plantillas`, {
                method: 'POST',
                credentials: 'include',
                body: formData
            });
            
            if (!response.ok) {
                const error = await response.json();
                throw new Error(error.message || 'Error al crear');
            }
            
            bootstrap.Modal.getInstance(document.getElementById('modalNuevaPlantilla')).hide();
            e.target.reset();
            showAlert('Plantilla creada correctamente', 'success');
            loadPlantillas();
            
        } catch (error) {
            showAlert(error.message, 'danger');
        }
    });
}

async function eliminarPlantilla() {
    if (!plantillaActual) return;
    if (!confirm(`¿Eliminar la plantilla "${plantillaActual.nombre}"?`)) return;
    
    try {
        const response = await fetch(`${CONFIG.apiUrl}/config/plantillas/${plantillaActual.id}`, {
            method: 'DELETE',
            credentials: 'include'
        });
        
        if (!response.ok) throw new Error('Error al eliminar');
        
        plantillaActual = null;
        document.getElementById('editorTitulo').textContent = 'Seleccione una plantilla';
        document.getElementById('editorContainer').innerHTML = `
            <div class="text-center py-5 text-muted">
                <i class="bi bi-file-earmark-pdf fs-1 d-block mb-3"></i>
                <p>Seleccione una plantilla de la lista.</p>
            </div>
        `;
        
        showAlert('Plantilla eliminada', 'success');
        loadPlantillas();
        
    } catch (error) {
        showAlert(error.message, 'danger');
    }
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
    setTimeout(() => { alert.classList.remove('show'); setTimeout(() => alert.remove(), 150); }, 4000);
}
JS;

include ADMIN_LAYOUTS . '/footer.php';

