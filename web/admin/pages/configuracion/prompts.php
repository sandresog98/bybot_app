<?php
/**
 * Configuración de Prompts de IA
 */

$pageTitle = 'Prompts IA';
$pageDescription = 'Gestión de prompts para el análisis de documentos';

include ADMIN_LAYOUTS . '/header.php';
?>

<!-- Breadcrumb -->
<nav aria-label="breadcrumb" class="mb-4">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= adminUrl('index.php') ?>">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="<?= adminUrl('index.php?page=configuracion') ?>">Configuración</a></li>
        <li class="breadcrumb-item active">Prompts IA</li>
    </ol>
</nav>

<!-- Tabs de configuración -->
<ul class="nav nav-tabs mb-4">
    <li class="nav-item">
        <a class="nav-link" href="<?= adminUrl('index.php?page=configuracion') ?>">
            <i class="bi bi-gear me-1"></i>General
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link active" href="<?= adminUrl('index.php?page=configuracion&action=prompts') ?>">
            <i class="bi bi-robot me-1"></i>Prompts IA
        </a>
    </li>
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
    <div class="col-lg-4">
        <!-- Lista de Prompts -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-list me-2"></i>Prompts</span>
                <button class="btn btn-sm btn-primary" onclick="nuevoPrompt()">
                    <i class="bi bi-plus"></i>
                </button>
            </div>
            <div class="list-group list-group-flush" id="listaPrompts">
                <div class="text-center py-4">
                    <div class="spinner-border spinner-border-sm"></div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-8">
        <!-- Editor de Prompt -->
        <div class="card">
            <div class="card-header">
                <i class="bi bi-pencil me-2"></i>
                <span id="editorTitulo">Seleccione un prompt</span>
            </div>
            <div class="card-body">
                <form id="formPrompt" style="display: none;">
                    <input type="hidden" name="id" id="promptId">
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Código <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="codigo" id="promptCodigo" required 
                                   pattern="[a-z_]+" title="Solo letras minúsculas y guiones bajos">
                            <div class="form-text">Identificador único (ej: analisis_estado_cuenta)</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Nombre <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="nombre" id="promptNombre" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Descripción</label>
                            <input type="text" class="form-control" name="descripcion" id="promptDescripcion">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Contenido del Prompt <span class="text-danger">*</span></label>
                            <textarea class="form-control font-monospace" name="contenido" id="promptContenido" 
                                      rows="15" required style="font-size: 0.85rem;"></textarea>
                            <div class="form-text">
                                <strong>Variables disponibles:</strong> 
                                <code>{documento}</code>, <code>{tipo}</code>, <code>{contexto}</code>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="activo" id="promptActivo" checked>
                                <label class="form-check-label" for="promptActivo">Activo</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <hr>
                            <div class="d-flex gap-2 justify-content-between">
                                <button type="button" class="btn btn-outline-danger" onclick="eliminarPrompt()" id="btnEliminar" style="display:none;">
                                    <i class="bi bi-trash me-1"></i>Eliminar
                                </button>
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-outline-secondary" onclick="cancelarEdicion()">
                                        Cancelar
                                    </button>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-save me-1"></i>Guardar
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
                
                <div id="placeholderEditor" class="text-center py-5 text-muted">
                    <i class="bi bi-chat-left-text fs-1 d-block mb-3"></i>
                    <p>Seleccione un prompt de la lista para editarlo o cree uno nuevo.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$inlineJs = <<<'JS'
let prompts = [];
let promptActual = null;

document.addEventListener('DOMContentLoaded', function() {
    loadPrompts();
    
    document.getElementById('formPrompt').addEventListener('submit', async (e) => {
        e.preventDefault();
        await guardarPrompt();
    });
});

async function loadPrompts() {
    try {
        const response = await fetch(`${CONFIG.apiUrl}/config/prompts`, { credentials: 'include' });
        if (!response.ok) throw new Error('Error cargando prompts');
        
        const { data } = await response.json();
        prompts = data || [];
        renderPrompts();
        
    } catch (error) {
        document.getElementById('listaPrompts').innerHTML = `
            <div class="text-center py-4 text-danger">${error.message}</div>
        `;
    }
}

function renderPrompts() {
    const container = document.getElementById('listaPrompts');
    
    if (!prompts.length) {
        container.innerHTML = `<div class="text-center py-4 text-muted">No hay prompts</div>`;
        return;
    }
    
    container.innerHTML = prompts.map(p => `
        <a href="#" class="list-group-item list-group-item-action ${promptActual?.id === p.id ? 'active' : ''}" 
           onclick="seleccionarPrompt(${p.id}); return false;">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <strong>${escapeHtml(p.nombre)}</strong>
                    <br><small class="text-muted">${p.codigo}</small>
                </div>
                <span class="badge ${p.activo ? 'bg-success' : 'bg-secondary'}">${p.activo ? 'Activo' : 'Inactivo'}</span>
            </div>
        </a>
    `).join('');
}

function seleccionarPrompt(id) {
    const prompt = prompts.find(p => p.id === id);
    if (!prompt) return;
    
    promptActual = prompt;
    
    document.getElementById('placeholderEditor').style.display = 'none';
    document.getElementById('formPrompt').style.display = 'block';
    document.getElementById('btnEliminar').style.display = '';
    
    document.getElementById('editorTitulo').textContent = 'Editar: ' + prompt.nombre;
    document.getElementById('promptId').value = prompt.id;
    document.getElementById('promptCodigo').value = prompt.codigo;
    document.getElementById('promptCodigo').readOnly = true;
    document.getElementById('promptNombre').value = prompt.nombre;
    document.getElementById('promptDescripcion').value = prompt.descripcion || '';
    document.getElementById('promptContenido').value = prompt.contenido;
    document.getElementById('promptActivo').checked = prompt.activo;
    
    renderPrompts();
}

function nuevoPrompt() {
    promptActual = null;
    
    document.getElementById('placeholderEditor').style.display = 'none';
    document.getElementById('formPrompt').style.display = 'block';
    document.getElementById('btnEliminar').style.display = 'none';
    
    document.getElementById('editorTitulo').textContent = 'Nuevo Prompt';
    document.getElementById('formPrompt').reset();
    document.getElementById('promptId').value = '';
    document.getElementById('promptCodigo').readOnly = false;
    document.getElementById('promptActivo').checked = true;
    
    renderPrompts();
}

function cancelarEdicion() {
    promptActual = null;
    document.getElementById('placeholderEditor').style.display = 'block';
    document.getElementById('formPrompt').style.display = 'none';
    document.getElementById('editorTitulo').textContent = 'Seleccione un prompt';
    renderPrompts();
}

async function guardarPrompt() {
    const form = document.getElementById('formPrompt');
    const formData = new FormData(form);
    const id = formData.get('id');
    
    const data = {
        codigo: formData.get('codigo'),
        nombre: formData.get('nombre'),
        descripcion: formData.get('descripcion'),
        contenido: formData.get('contenido'),
        activo: document.getElementById('promptActivo').checked ? 1 : 0
    };
    
    try {
        const url = id ? `${CONFIG.apiUrl}/config/prompts/${id}` : `${CONFIG.apiUrl}/config/prompts`;
        const method = id ? 'PUT' : 'POST';
        
        const response = await fetch(url, {
            method,
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify(data)
        });
        
        if (!response.ok) {
            const error = await response.json();
            throw new Error(error.message || 'Error al guardar');
        }
        
        showAlert(id ? 'Prompt actualizado' : 'Prompt creado', 'success');
        loadPrompts();
        
        if (!id) cancelarEdicion();
        
    } catch (error) {
        showAlert(error.message, 'danger');
    }
}

async function eliminarPrompt() {
    if (!promptActual) return;
    
    if (!confirm(`¿Está seguro de eliminar el prompt "${promptActual.nombre}"?`)) return;
    
    try {
        const response = await fetch(`${CONFIG.apiUrl}/config/prompts/${promptActual.id}`, {
            method: 'DELETE',
            credentials: 'include'
        });
        
        if (!response.ok) throw new Error('Error al eliminar');
        
        showAlert('Prompt eliminado', 'success');
        cancelarEdicion();
        loadPrompts();
        
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

