<?php
/**
 * Crear Nuevo Proceso
 */

$pageTitle = 'Nuevo Proceso';
$pageDescription = 'Crear un nuevo proceso de cobranza';

include ADMIN_LAYOUTS . '/header.php';
?>

<!-- Breadcrumb -->
<nav aria-label="breadcrumb" class="mb-4">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= adminUrl('index.php') ?>">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="<?= adminUrl('index.php?page=procesos') ?>">Procesos</a></li>
        <li class="breadcrumb-item active">Nuevo</li>
    </ol>
</nav>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-plus-circle me-2"></i>Información del Proceso
            </div>
            <div class="card-body">
                <form id="formCrearProceso">
                    <?= csrfField() ?>
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Tipo de Proceso <span class="text-danger">*</span></label>
                            <select class="form-select" name="tipo" required>
                                <option value="cobranza" selected>Cobranza</option>
                                <option value="demanda">Demanda</option>
                                <option value="otro">Otro</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Prioridad</label>
                            <select class="form-select" name="prioridad">
                                <option value="1">1 - Urgente</option>
                                <option value="2">2</option>
                                <option value="3">3 - Alta</option>
                                <option value="4">4</option>
                                <option value="5" selected>5 - Normal</option>
                                <option value="6">6</option>
                                <option value="7">7 - Baja</option>
                                <option value="8">8</option>
                                <option value="9">9</option>
                                <option value="10">10 - Mínima</option>
                            </select>
                            <div class="form-text">1 = Máxima prioridad, 10 = Mínima</div>
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label">Notas (opcional)</label>
                            <textarea class="form-control" name="notas" rows="3" 
                                      placeholder="Observaciones adicionales sobre el proceso..."></textarea>
                        </div>
                    </div>
                    
                    <hr class="my-4">
                    
                    <!-- Archivos -->
                    <h6 class="mb-3"><i class="bi bi-paperclip me-2"></i>Archivos del Proceso</h6>
                    
                    <div class="row g-3">
                        <!-- Pagaré Original -->
                        <div class="col-md-6">
                            <label class="form-label">Pagaré Original <span class="text-danger">*</span></label>
                            <div class="upload-zone" id="zonePagare" data-tipo="pagare_original">
                                <input type="file" class="d-none" id="filePagare" name="pagare" accept=".pdf" required>
                                <i class="bi bi-file-earmark-pdf"></i>
                                <p class="mb-1">Arrastra el archivo aquí o haz clic para seleccionar</p>
                                <small class="text-muted">Solo PDF, máx. 10MB</small>
                            </div>
                            <div class="file-preview mt-2 d-none" id="previewPagare"></div>
                        </div>
                        
                        <!-- Estado de Cuenta -->
                        <div class="col-md-6">
                            <label class="form-label">Estado de Cuenta <span class="text-danger">*</span></label>
                            <div class="upload-zone" id="zoneEstadoCuenta" data-tipo="estado_cuenta">
                                <input type="file" class="d-none" id="fileEstadoCuenta" name="estado_cuenta" accept=".pdf" required>
                                <i class="bi bi-file-earmark-text"></i>
                                <p class="mb-1">Arrastra el archivo aquí o haz clic para seleccionar</p>
                                <small class="text-muted">Solo PDF, máx. 10MB</small>
                            </div>
                            <div class="file-preview mt-2 d-none" id="previewEstadoCuenta"></div>
                        </div>
                        
                        <!-- Anexos adicionales -->
                        <div class="col-12">
                            <label class="form-label">Anexos Adicionales (opcional)</label>
                            <div class="upload-zone" id="zoneAnexos" data-tipo="anexo">
                                <input type="file" class="d-none" id="fileAnexos" name="anexos[]" accept=".pdf,.jpg,.jpeg,.png" multiple>
                                <i class="bi bi-files"></i>
                                <p class="mb-1">Arrastra los archivos aquí o haz clic para seleccionar</p>
                                <small class="text-muted">PDF o imágenes, máx. 10MB c/u</small>
                            </div>
                            <div class="file-list mt-2" id="listaAnexos"></div>
                        </div>
                    </div>
                    
                    <hr class="my-4">
                    
                    <!-- Acciones -->
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary" id="btnGuardar">
                            <i class="bi bi-save me-1"></i> Crear Proceso
                        </button>
                        <button type="button" class="btn btn-success" id="btnGuardarEncolar" disabled>
                            <i class="bi bi-play-fill me-1"></i> Crear y Encolar para Análisis
                        </button>
                        <a href="<?= adminUrl('index.php?page=procesos') ?>" class="btn btn-outline-secondary">
                            Cancelar
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Sidebar de ayuda -->
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-question-circle me-2"></i>Ayuda
            </div>
            <div class="card-body">
                <h6>Tipos de Proceso</h6>
                <ul class="small">
                    <li><strong>Cobranza:</strong> Proceso estándar de cobro</li>
                    <li><strong>Demanda:</strong> Proceso judicial</li>
                    <li><strong>Otro:</strong> Procesos especiales</li>
                </ul>
                
                <h6 class="mt-3">Archivos Requeridos</h6>
                <ul class="small">
                    <li><strong>Pagaré:</strong> Documento original del pagaré en PDF</li>
                    <li><strong>Estado de Cuenta:</strong> Documento con los datos financieros</li>
                </ul>
                
                <h6 class="mt-3">Flujo del Proceso</h6>
                <ol class="small">
                    <li>Crear proceso con archivos</li>
                    <li>Encolar para análisis IA</li>
                    <li>Validar datos extraídos</li>
                    <li>Generar pagaré llenado</li>
                </ol>
            </div>
        </div>
        
        <div class="card mt-3">
            <div class="card-header">
                <i class="bi bi-info-circle me-2"></i>Estado del Proceso
            </div>
            <div class="card-body">
                <div class="process-steps flex-column">
                    <div class="d-flex align-items-center mb-2">
                        <span class="process-step-icon active me-2" style="width:30px;height:30px;">
                            <i class="bi bi-1-circle-fill"></i>
                        </span>
                        <span>Crear proceso</span>
                    </div>
                    <div class="d-flex align-items-center mb-2 text-muted">
                        <span class="process-step-icon me-2" style="width:30px;height:30px;">
                            <i class="bi bi-2-circle"></i>
                        </span>
                        <span>Análisis IA</span>
                    </div>
                    <div class="d-flex align-items-center mb-2 text-muted">
                        <span class="process-step-icon me-2" style="width:30px;height:30px;">
                            <i class="bi bi-3-circle"></i>
                        </span>
                        <span>Validación</span>
                    </div>
                    <div class="d-flex align-items-center text-muted">
                        <span class="process-step-icon me-2" style="width:30px;height:30px;">
                            <i class="bi bi-4-circle"></i>
                        </span>
                        <span>Llenado</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$inlineJs = <<<'JS'
let filePagare = null;
let fileEstadoCuenta = null;
let filesAnexos = [];
let procesoCreado = null;

document.addEventListener('DOMContentLoaded', function() {
    initUploadZones();
    initFormSubmit();
});

function initUploadZones() {
    // Pagaré
    setupUploadZone('zonePagare', 'filePagare', 'previewPagare', false, (file) => {
        filePagare = file;
        checkFilesReady();
    });
    
    // Estado de cuenta
    setupUploadZone('zoneEstadoCuenta', 'fileEstadoCuenta', 'previewEstadoCuenta', false, (file) => {
        fileEstadoCuenta = file;
        checkFilesReady();
    });
    
    // Anexos
    setupUploadZone('zoneAnexos', 'fileAnexos', 'listaAnexos', true, (files) => {
        filesAnexos = Array.from(files);
    });
}

function setupUploadZone(zoneId, inputId, previewId, multiple, callback) {
    const zone = document.getElementById(zoneId);
    const input = document.getElementById(inputId);
    const preview = document.getElementById(previewId);
    
    // Click para seleccionar
    zone.addEventListener('click', () => input.click());
    
    // Drag & drop
    zone.addEventListener('dragover', (e) => {
        e.preventDefault();
        zone.classList.add('dragover');
    });
    
    zone.addEventListener('dragleave', () => {
        zone.classList.remove('dragover');
    });
    
    zone.addEventListener('drop', (e) => {
        e.preventDefault();
        zone.classList.remove('dragover');
        const files = e.dataTransfer.files;
        if (files.length) {
            input.files = files;
            handleFiles(files, preview, multiple, callback);
        }
    });
    
    // Input change
    input.addEventListener('change', () => {
        if (input.files.length) {
            handleFiles(input.files, preview, multiple, callback);
        }
    });
}

function handleFiles(files, preview, multiple, callback) {
    preview.classList.remove('d-none');
    
    if (multiple) {
        preview.innerHTML = Array.from(files).map((file, i) => `
            <div class="d-flex align-items-center p-2 bg-light rounded mb-2">
                <i class="bi ${file.type === 'application/pdf' ? 'bi-file-pdf text-danger' : 'bi-file-image text-primary'} me-2"></i>
                <span class="flex-grow-1 text-truncate">${file.name}</span>
                <small class="text-muted">${formatFileSize(file.size)}</small>
                <button type="button" class="btn btn-sm btn-link text-danger" onclick="removeAnexo(${i})">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
        `).join('');
    } else {
        const file = files[0];
        preview.innerHTML = `
            <div class="d-flex align-items-center p-2 bg-success bg-opacity-10 border border-success rounded">
                <i class="bi bi-check-circle text-success me-2"></i>
                <span class="flex-grow-1 text-truncate">${file.name}</span>
                <small class="text-muted">${formatFileSize(file.size)}</small>
            </div>
        `;
    }
    
    callback(files);
}

function formatFileSize(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
}

function checkFilesReady() {
    const ready = filePagare && fileEstadoCuenta;
    document.getElementById('btnGuardarEncolar').disabled = !ready;
}

function initFormSubmit() {
    const form = document.getElementById('formCrearProceso');
    const btnGuardar = document.getElementById('btnGuardar');
    const btnEncolar = document.getElementById('btnGuardarEncolar');
    
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        await crearProceso(false);
    });
    
    btnEncolar.addEventListener('click', async () => {
        await crearProceso(true);
    });
}

async function crearProceso(encolar = false) {
    const form = document.getElementById('formCrearProceso');
    const btnGuardar = document.getElementById('btnGuardar');
    const btnEncolar = document.getElementById('btnGuardarEncolar');
    
    if (!filePagare || !fileEstadoCuenta) {
        showAlert('Por favor sube el pagaré y el estado de cuenta', 'warning');
        return;
    }
    
    // Deshabilitar botones
    btnGuardar.disabled = true;
    btnEncolar.disabled = true;
    btnGuardar.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Creando...';
    
    try {
        // 1. Crear proceso
        const formData = new FormData(form);
        const datos = {
            tipo: formData.get('tipo'),
            prioridad: parseInt(formData.get('prioridad')),
            notas: formData.get('notas')
        };
        
        const responseCrear = await fetch(`${CONFIG.apiUrl}/procesos`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            credentials: 'include',
            body: JSON.stringify(datos)
        });
        
        if (!responseCrear.ok) {
            const error = await responseCrear.json();
            throw new Error(error.message || 'Error al crear proceso');
        }
        
        const { data: proceso } = await responseCrear.json();
        procesoCreado = proceso;
        
        // 2. Subir archivos
        btnGuardar.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Subiendo archivos...';
        
        // Subir pagaré
        await subirArchivo(proceso.id, filePagare, 'pagare_original');
        
        // Subir estado de cuenta
        await subirArchivo(proceso.id, fileEstadoCuenta, 'estado_cuenta');
        
        // Subir anexos
        for (const anexo of filesAnexos) {
            await subirArchivo(proceso.id, anexo, 'anexo');
        }
        
        // 3. Encolar si se solicitó
        if (encolar) {
            btnGuardar.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Encolando...';
            
            const responseEncolar = await fetch(`${CONFIG.apiUrl}/procesos/${proceso.id}/encolar-analisis`, {
                method: 'POST',
                credentials: 'include'
            });
            
            if (!responseEncolar.ok) {
                throw new Error('Proceso creado pero hubo error al encolar');
            }
        }
        
        // Éxito
        showAlert(`Proceso ${proceso.codigo} creado exitosamente`, 'success');
        
        setTimeout(() => {
            window.location.href = `${CONFIG.adminUrl}/index.php?page=procesos&action=ver&id=${proceso.id}`;
        }, 1000);
        
    } catch (error) {
        console.error('Error:', error);
        showAlert(error.message, 'danger');
        
        btnGuardar.disabled = false;
        btnGuardar.innerHTML = '<i class="bi bi-save me-1"></i> Crear Proceso';
        checkFilesReady();
    }
}

async function subirArchivo(procesoId, file, tipo) {
    const formData = new FormData();
    formData.append('proceso_id', procesoId);
    formData.append('tipo', tipo);
    formData.append('archivo', file);
    
    const response = await fetch(`${CONFIG.apiUrl}/archivos/subir`, {
        method: 'POST',
        credentials: 'include',
        body: formData
    });
    
    if (!response.ok) {
        const error = await response.json();
        throw new Error(error.message || `Error al subir ${tipo}`);
    }
    
    return response.json();
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
}
JS;

include ADMIN_LAYOUTS . '/footer.php';

