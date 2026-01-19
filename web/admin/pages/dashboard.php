<?php
/**
 * Dashboard Principal
 */

$pageTitle = 'Dashboard';
$pageDescription = 'Resumen y estadísticas del sistema';

include ADMIN_LAYOUTS . '/header.php';
?>

<!-- Tarjetas de Estadísticas -->
<div class="row g-4 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-value" id="statTotalProcesos">-</div>
                        <div class="stat-label">Total Procesos</div>
                    </div>
                    <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                        <i class="bi bi-folder2-open"></i>
                    </div>
                </div>
                <div class="stat-change positive mt-2" id="statProcesosHoy">
                    <i class="bi bi-arrow-up"></i> - hoy
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-value" id="statCompletados">-</div>
                        <div class="stat-label">Completados (Semana)</div>
                    </div>
                    <div class="stat-icon bg-success bg-opacity-10 text-success">
                        <i class="bi bi-check-circle"></i>
                    </div>
                </div>
                <div class="stat-change positive mt-2" id="statTasaExito">
                    <i class="bi bi-graph-up"></i> -% tasa de éxito
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-value" id="statEnProceso">-</div>
                        <div class="stat-label">En Proceso</div>
                    </div>
                    <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                        <i class="bi bi-hourglass-split"></i>
                    </div>
                </div>
                <div class="stat-change mt-2" id="statEnCola">
                    <i class="bi bi-stack"></i> - en cola
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-value" id="statTiempoPromedio">-</div>
                        <div class="stat-label">Tiempo Promedio</div>
                    </div>
                    <div class="stat-icon bg-info bg-opacity-10 text-info">
                        <i class="bi bi-clock-history"></i>
                    </div>
                </div>
                <div class="stat-change mt-2 text-muted">
                    <i class="bi bi-clock"></i> horas por proceso
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Gráfico de Procesos por Estado -->
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-bar-chart me-2"></i>Procesos por Estado</span>
                <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-secondary active" data-chart-range="week">Semana</button>
                    <button class="btn btn-outline-secondary" data-chart-range="month">Mes</button>
                </div>
            </div>
            <div class="card-body" style="height: 300px; position: relative;">
                <canvas id="chartProcesos"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Distribución por Estado -->
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header">
                <i class="bi bi-pie-chart me-2"></i>Distribución Actual
            </div>
            <div class="card-body" style="height: 300px; position: relative;">
                <canvas id="chartDistribucion"></canvas>
                <div class="mt-3" id="legendaEstados"></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mt-0">
    <!-- Actividad Reciente -->
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-activity me-2"></i>Actividad Reciente</span>
                <a href="<?= adminUrl('index.php?page=actividad') ?>" class="btn btn-sm btn-outline-primary">
                    Ver Más
                </a>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush" id="actividadReciente">
                    <div class="text-center py-4">
                        <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                        <span class="ms-2 text-muted">Cargando...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Estado de Colas -->
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-stack me-2"></i>Estado de Colas</span>
                <span class="badge bg-success" id="redisStatus">Conectado</span>
            </div>
            <div class="card-body">
                <div class="row g-3" id="colasStatus">
                    <div class="col-md-4">
                        <div class="p-3 bg-light rounded text-center">
                            <div class="fs-3 fw-bold text-primary" id="colaAnalisis">-</div>
                            <small class="text-muted">Análisis</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3 bg-light rounded text-center">
                            <div class="fs-3 fw-bold text-warning" id="colaLlenado">-</div>
                            <small class="text-muted">Llenado</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3 bg-light rounded text-center">
                            <div class="fs-3 fw-bold text-info" id="colaNotify">-</div>
                            <small class="text-muted">Notificaciones</small>
                        </div>
                    </div>
                </div>
                
                <hr>
                
                <h6 class="mb-3">Trabajos Recientes</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Cola</th>
                                <th>Estado</th>
                                <th>Proceso</th>
                                <th>Tiempo</th>
                            </tr>
                        </thead>
                        <tbody id="trabajosRecientes">
                            <tr>
                                <td colspan="4" class="text-center text-muted py-3">
                                    <small>No hay trabajos recientes</small>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Procesos Pendientes de Validación -->
<?php if (hasAccess('procesos.validar_ia')): ?>
<div class="row g-4 mt-0">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-clipboard-check me-2"></i>Pendientes de Validación</span>
                <span class="badge bg-warning text-dark" id="countPendientes">0</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Estado</th>
                                <th>Creado</th>
                                <th>Prioridad</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="tablaPendientes">
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">
                                    <div class="spinner-border spinner-border-sm" role="status"></div>
                                    <span class="ms-2">Cargando...</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
$inlineJs = <<<'JS'
// Variables globales
let chartProcesos = null;
let chartDistribucion = null;

// Colores de estados
const coloresEstados = {
    creado: '#6c757d',
    en_cola_analisis: '#17a2b8',
    analizando: '#007bff',
    analizado: '#55A5C8',
    validado: '#9AD082',
    en_cola_llenado: '#fd7e14',
    llenando: '#e83e8c',
    completado: '#28a745',
    error_analisis: '#dc3545',
    error_llenado: '#dc3545',
    cancelado: '#6c757d'
};

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

// Cargar dashboard
document.addEventListener('DOMContentLoaded', function() {
    loadDashboardStats();
    loadColasStatus();
    loadActividadReciente();
    loadPendientesValidacion();
    
    // Actualizar cada 30 segundos
    setInterval(loadDashboardStats, 30000);
    setInterval(loadColasStatus, 15000);
});

async function loadDashboardStats() {
    try {
        const response = await fetch(CONFIG.apiUrl + '/stats/dashboard', {
            credentials: 'include'
        });
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: Error cargando estadísticas`);
        }
        
        const data = await response.json();
        
        // Validar estructura de respuesta
        if (!data || !data.data) {
            console.error('Respuesta inválida:', data);
            throw new Error('Estructura de respuesta inválida');
        }
        
        const stats = data.data || {};
        
        // Actualizar tarjetas
        document.getElementById('statTotalProcesos').textContent = stats.total_procesos || 0;
        document.getElementById('statProcesosHoy').innerHTML = 
            `<i class="bi bi-arrow-up"></i> ${stats.procesos_hoy || 0} hoy`;
        
        document.getElementById('statCompletados').textContent = stats.completados_semana || 0;
        document.getElementById('statTasaExito').innerHTML = 
            `<i class="bi bi-graph-up"></i> ${stats.tasa_exito || 0}% tasa de éxito`;
        
        // Calcular en proceso
        const estadosEnProceso = ['en_cola_analisis', 'analizando', 'analizado', 'en_cola_llenado', 'llenando'];
        let enProceso = 0;
        estadosEnProceso.forEach(e => {
            enProceso += stats.procesos_por_estado?.[e] || 0;
        });
        document.getElementById('statEnProceso').textContent = enProceso;
        
        const enCola = (stats.colas?.analyze || 0) + (stats.colas?.fill || 0);
        document.getElementById('statEnCola').innerHTML = 
            `<i class="bi bi-stack"></i> ${enCola} en cola`;
        
        document.getElementById('statTiempoPromedio').textContent = 
            stats.tiempo_promedio_horas ? `${stats.tiempo_promedio_horas}h` : '-';
        
        // Actualizar gráficos
        updateChartDistribucion(stats.procesos_por_estado || {});
        updateChartProcesos(stats.procesos_por_estado || {});
        
    } catch (error) {
        console.error('Error:', error);
    }
}

function updateChartProcesos(porEstado) {
    const ctx = document.getElementById('chartProcesos');
    if (!ctx) return;
    
    const labels = [];
    const datos = [];
    const colores = [];
    
    // Ordenar por cantidad descendente
    const sorted = Object.entries(porEstado)
        .filter(([estado, cantidad]) => cantidad > 0)
        .sort((a, b) => b[1] - a[1]);
    
    sorted.forEach(([estado, cantidad]) => {
        labels.push(nombresEstados[estado] || estado);
        datos.push(cantidad);
        colores.push(coloresEstados[estado] || '#ccc');
    });
    
    if (chartProcesos) {
        chartProcesos.data.labels = labels;
        chartProcesos.data.datasets[0].data = datos;
        chartProcesos.data.datasets[0].backgroundColor = colores;
        chartProcesos.update();
    } else {
        chartProcesos = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Procesos',
                    data: datos,
                    backgroundColor: colores,
                    borderColor: colores.map(c => c + '80'),
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                aspectRatio: 2,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.parsed.y + ' procesos';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
    }
}

function updateChartDistribucion(porEstado) {
    const ctx = document.getElementById('chartDistribucion');
    if (!ctx) return;
    
    const labels = [];
    const datos = [];
    const colores = [];
    
    Object.entries(porEstado).forEach(([estado, cantidad]) => {
        if (cantidad > 0) {
            labels.push(nombresEstados[estado] || estado);
            datos.push(cantidad);
            colores.push(coloresEstados[estado] || '#ccc');
        }
    });
    
    if (chartDistribucion) {
        chartDistribucion.data.labels = labels;
        chartDistribucion.data.datasets[0].data = datos;
        chartDistribucion.data.datasets[0].backgroundColor = colores;
        chartDistribucion.update();
    } else {
        chartDistribucion = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: datos,
                    backgroundColor: colores,
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                aspectRatio: 1,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                cutout: '70%'
            }
        });
    }
    
    // Actualizar leyenda
    let legendaHtml = '<div class="row g-2">';
    labels.forEach((label, i) => {
        legendaHtml += `
            <div class="col-6">
                <small>
                    <span class="d-inline-block rounded-circle me-1" style="width:10px;height:10px;background:${colores[i]}"></span>
                    ${label}: <strong>${datos[i]}</strong>
                </small>
            </div>
        `;
    });
    legendaHtml += '</div>';
    document.getElementById('legendaEstados').innerHTML = legendaHtml;
}

async function loadColasStatus() {
    try {
        const response = await fetch(CONFIG.apiUrl + '/colas/estado', {
            credentials: 'include'
        });
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: Error cargando colas`);
        }
        
        const data = await response.json();
        
        // Validar estructura de respuesta
        if (!data || !data.data) {
            console.error('Respuesta inválida:', data);
            throw new Error('Estructura de respuesta inválida');
        }
        
        const colas = data.data || {};
        
        // Estado de Redis
        const redisStatus = document.getElementById('redisStatus');
        if (colas.redis_conectado) {
            redisStatus.className = 'badge bg-success';
            redisStatus.textContent = 'Conectado';
        } else {
            redisStatus.className = 'badge bg-danger';
            redisStatus.textContent = 'Desconectado';
        }
        
        // Cantidades en colas
        document.getElementById('colaAnalisis').textContent = colas.colas?.['bybot:analyze']?.pendientes || 0;
        document.getElementById('colaLlenado').textContent = colas.colas?.['bybot:fill']?.pendientes || 0;
        document.getElementById('colaNotify').textContent = colas.colas?.['bybot:notify']?.pendientes || 0;
        
        // Trabajos en progreso
        const tbody = document.getElementById('trabajosRecientes');
        if (colas.en_progreso && colas.en_progreso.length > 0) {
            tbody.innerHTML = colas.en_progreso.slice(0, 5).map(trabajo => `
                <tr>
                    <td><span class="badge bg-secondary">${trabajo.cola?.replace('bybot:', '') || '-'}</span></td>
                    <td><span class="badge badge-estado-${trabajo.estado}">${trabajo.estado}</span></td>
                    <td>${trabajo.proceso_id || '-'}</td>
                    <td><small class="text-muted">${formatTimeAgo(trabajo.fecha_creacion)}</small></td>
                </tr>
            `).join('');
        }
        
    } catch (error) {
        document.getElementById('redisStatus').className = 'badge bg-warning';
        document.getElementById('redisStatus').textContent = 'Error';
    }
}

async function loadActividadReciente() {
    try {
        const response = await fetch(CONFIG.apiUrl + '/stats/actividad?limit=8', {
            credentials: 'include'
        });
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: Error cargando actividad`);
        }
        
        const data = await response.json();
        
        // Validar estructura de respuesta
        if (!data || !data.data) {
            console.error('Respuesta inválida:', data);
            const container = document.getElementById('actividadReciente');
            if (container) {
                container.innerHTML = '<div class="text-center text-muted py-4"><small>Error cargando actividad</small></div>';
            }
            return;
        }
        
        const actividad = data.data || [];
        const container = document.getElementById('actividadReciente');
        
        if (!container) return;
        
        if (actividad.length === 0) {
            container.innerHTML = '<div class="text-center text-muted py-4"><small>No hay actividad reciente</small></div>';
            return;
        }
        
        container.innerHTML = actividad.map(item => `
            <div class="list-group-item list-group-item-action">
                <div class="d-flex w-100 justify-content-between align-items-start">
                    <div>
                        <span class="badge badge-estado-${item.estado_nuevo || item.accion} me-2">${item.accion}</span>
                        <small>${item.descripcion || '-'}</small>
                        ${item.proceso_codigo ? `<a href="${CONFIG.adminUrl}/index.php?page=procesos&action=ver&id=${item.proceso_id}" class="ms-2 text-primary">${item.proceso_codigo}</a>` : ''}
                    </div>
                    <small class="text-muted text-nowrap">${formatTimeAgo(item.fecha)}</small>
                </div>
                <small class="text-muted">${item.usuario || 'Sistema'}</small>
            </div>
        `).join('');
        
    } catch (error) {
        console.error('Error cargando actividad:', error);
    }
}

async function loadPendientesValidacion() {
    const tbody = document.getElementById('tablaPendientes');
    if (!tbody) return;
    
    try {
        // Usar URL sin barra diagonal al final para evitar problemas de parsing
        // Intentar primero sin barra diagonal
        let url = CONFIG.apiUrl + '/procesos?estado=analizado&per_page=5';
        let response = await fetch(url, {
            credentials: 'include',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            }
        });
        
        // Si falla con 403, puede ser problema de Apache bloqueando
        // Intentar con la URL completa incluyendo el path base
        if (!response.ok && response.status === 403) {
            console.warn('Error 403 detectado, puede ser problema de configuración de Apache');
            // Mostrar mensaje informativo
            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-warning py-4"><small>No se pueden cargar los procesos pendientes. Verifica la configuración del servidor.</small></td></tr>';
            return;
        }
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }
        
        const data = await response.json();
        
        // Verificar estructura de respuesta
        if (!data || !data.data) {
            throw new Error('Respuesta inválida');
        }
        
        const procesos = data.data || [];
        
        document.getElementById('countPendientes').textContent = data.pagination?.total || 0;
        
        if (procesos.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">No hay procesos pendientes de validación</td></tr>';
            return;
        }
        
        tbody.innerHTML = procesos.map(p => `
            <tr>
                <td><a href="${CONFIG.adminUrl}/index.php?page=procesos&action=ver&id=${p.id}">${p.codigo}</a></td>
                <td><span class="badge badge-estado-${p.estado}">${nombresEstados[p.estado] || p.estado}</span></td>
                <td>${formatTimeAgo(p.fecha_creacion)}</td>
                <td>
                    <span class="badge ${p.prioridad <= 3 ? 'bg-danger' : p.prioridad <= 6 ? 'bg-warning text-dark' : 'bg-secondary'}">
                        ${p.prioridad}
                    </span>
                </td>
                <td class="text-end">
                    <a href="${CONFIG.adminUrl}/index.php?page=procesos&action=validar&id=${p.id}" class="btn btn-sm btn-primary">
                        <i class="bi bi-check-lg"></i> Validar
                    </a>
                </td>
            </tr>
        `).join('');
        
    } catch (error) {
        console.error('Error cargando pendientes de validación:', error);
        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-danger py-4"><small>Error cargando datos. Intenta recargar la página.</small></td></tr>';
    }
}

function formatTimeAgo(dateStr) {
    if (!dateStr) return '-';
    const date = new Date(dateStr);
    const now = new Date();
    const diff = Math.floor((now - date) / 1000);
    
    if (diff < 60) return 'Hace un momento';
    if (diff < 3600) return `Hace ${Math.floor(diff / 60)} min`;
    if (diff < 86400) return `Hace ${Math.floor(diff / 3600)} h`;
    if (diff < 604800) return `Hace ${Math.floor(diff / 86400)} días`;
    return date.toLocaleDateString('es-CO');
}
JS;

include ADMIN_LAYOUTS . '/footer.php';

