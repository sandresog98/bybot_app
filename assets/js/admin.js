/**
 * ByBot Admin - JavaScript Principal
 * Funciones globales para el panel administrativo
 */

// Configuración global
const CONFIG = {
    apiUrl: window.APP_CONFIG?.apiUrl || '/bybot/web/api/v1',
    adminUrl: window.APP_CONFIG?.adminUrl || '/bybot/web/admin',
    wsUrl: window.APP_CONFIG?.wsUrl || 'ws://localhost:8080',
    debug: window.APP_CONFIG?.debug || false
};

// Estado global
const AppState = {
    user: null,
    notifications: [],
    wsConnected: false
};

/**
 * Inicialización cuando el DOM está listo
 */
document.addEventListener('DOMContentLoaded', function() {
    initSidebar();
    initTooltips();
    initDropdowns();
    initNotifications();
    
    if (CONFIG.debug) {
        console.log('ByBot Admin initialized', CONFIG);
    }
});

/**
 * Inicializar sidebar toggle
 */
function initSidebar() {
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('mainContent');
    
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('collapsed');
            if (mainContent) {
                mainContent.classList.toggle('expanded');
            }
            
            // Guardar preferencia
            localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
        });
        
        // Restaurar preferencia
        if (localStorage.getItem('sidebarCollapsed') === 'true') {
            sidebar.classList.add('collapsed');
            if (mainContent) mainContent.classList.add('expanded');
        }
    }
}

/**
 * Inicializar tooltips de Bootstrap
 */
function initTooltips() {
    const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    tooltipTriggerList.forEach(el => new bootstrap.Tooltip(el));
}

/**
 * Inicializar dropdowns
 */
function initDropdowns() {
    // Los dropdowns de Bootstrap se inicializan automáticamente
}

/**
 * Sistema de notificaciones
 */
function initNotifications() {
    // Cargar notificaciones iniciales
    loadNotifications();
    
    // Actualizar periódicamente
    setInterval(loadNotifications, 60000); // cada minuto
}

async function loadNotifications() {
    try {
        const response = await fetch(`${CONFIG.apiUrl}/notificaciones/pendientes`, {
            credentials: 'include'
        });
        
        if (!response.ok) return;
        
        const { data } = await response.json();
        AppState.notifications = data || [];
        updateNotificationBadge();
        
    } catch (e) {
        // Silenciar errores de notificaciones
    }
}

function updateNotificationBadge() {
    const badge = document.getElementById('notificationCount');
    if (badge) {
        const count = AppState.notifications.length;
        badge.textContent = count > 99 ? '99+' : count;
        badge.style.display = count > 0 ? '' : 'none';
    }
}

/**
 * Mostrar alerta/toast
 */
function showAlert(message, type = 'info', duration = 5000) {
    const container = document.getElementById('alertContainer') || createAlertContainer();
    
    const alertId = 'alert-' + Date.now();
    const alert = document.createElement('div');
    alert.id = alertId;
    alert.className = `alert alert-${type} alert-dismissible fade show`;
    alert.setAttribute('role', 'alert');
    
    const icon = {
        success: 'check-circle',
        danger: 'exclamation-triangle',
        warning: 'exclamation-circle',
        info: 'info-circle'
    }[type] || 'info-circle';
    
    alert.innerHTML = `
        <i class="bi bi-${icon} me-2"></i>
        ${escapeHtml(message)}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;
    
    container.prepend(alert);
    
    // Auto-dismiss
    if (duration > 0) {
        setTimeout(() => {
            const el = document.getElementById(alertId);
            if (el) {
                el.classList.remove('show');
                setTimeout(() => el.remove(), 150);
            }
        }, duration);
    }
    
    return alertId;
}

function createAlertContainer() {
    const container = document.createElement('div');
    container.id = 'alertContainer';
    container.className = 'position-fixed top-0 end-0 p-3';
    container.style.zIndex = '1100';
    document.body.appendChild(container);
    return container;
}

/**
 * Mostrar toast (notificación flotante)
 */
function showToast(title, message, type = 'info') {
    const container = document.getElementById('toastContainer') || createToastContainer();
    
    const toast = document.createElement('div');
    toast.className = `toast show border-${type}`;
    toast.setAttribute('role', 'alert');
    
    toast.innerHTML = `
        <div class="toast-header">
            <strong class="me-auto text-${type}">${escapeHtml(title)}</strong>
            <small>Ahora</small>
            <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
        </div>
        <div class="toast-body">
            ${escapeHtml(message)}
        </div>
    `;
    
    container.appendChild(toast);
    
    // Auto-hide después de 5 segundos
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 150);
    }, 5000);
}

function createToastContainer() {
    const container = document.createElement('div');
    container.id = 'toastContainer';
    container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
    container.style.zIndex = '1100';
    document.body.appendChild(container);
    return container;
}

/**
 * Modal de confirmación
 */
function confirmAction(message, title = 'Confirmar') {
    return new Promise((resolve) => {
        const modal = document.createElement('div');
        modal.className = 'modal fade';
        modal.innerHTML = `
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">${escapeHtml(title)}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>${escapeHtml(message)}</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="button" class="btn btn-primary" id="confirmBtn">Confirmar</button>
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        const bsModal = new bootstrap.Modal(modal);
        
        modal.querySelector('#confirmBtn').addEventListener('click', () => {
            resolve(true);
            bsModal.hide();
        });
        
        modal.addEventListener('hidden.bs.modal', () => {
            resolve(false);
            modal.remove();
        });
        
        bsModal.show();
    });
}

/**
 * Utilidades de formateo
 */
function formatDate(dateStr, options = {}) {
    if (!dateStr) return '-';
    
    const defaultOptions = {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    };
    
    return new Date(dateStr).toLocaleDateString('es-CO', { ...defaultOptions, ...options });
}

function formatCurrency(amount, currency = 'COP') {
    if (amount === null || amount === undefined) return '-';
    return new Intl.NumberFormat('es-CO', {
        style: 'currency',
        currency: currency,
        minimumFractionDigits: 0
    }).format(amount);
}

function formatNumber(num) {
    if (num === null || num === undefined) return '-';
    return new Intl.NumberFormat('es-CO').format(num);
}

function formatFileSize(bytes) {
    if (!bytes) return '-';
    const units = ['B', 'KB', 'MB', 'GB'];
    let i = 0;
    while (bytes >= 1024 && i < units.length - 1) {
        bytes /= 1024;
        i++;
    }
    return bytes.toFixed(1) + ' ' + units[i];
}

function formatTimeAgo(dateStr) {
    if (!dateStr) return '-';
    const seconds = Math.floor((Date.now() - new Date(dateStr).getTime()) / 1000);
    
    const intervals = [
        { label: 'año', seconds: 31536000 },
        { label: 'mes', seconds: 2592000 },
        { label: 'semana', seconds: 604800 },
        { label: 'día', seconds: 86400 },
        { label: 'hora', seconds: 3600 },
        { label: 'minuto', seconds: 60 },
        { label: 'segundo', seconds: 1 }
    ];
    
    for (const interval of intervals) {
        const count = Math.floor(seconds / interval.seconds);
        if (count >= 1) {
            const plural = count !== 1 ? (interval.label === 'mes' ? 'es' : 's') : '';
            return `Hace ${count} ${interval.label}${plural}`;
        }
    }
    
    return 'Ahora';
}

/**
 * Escape HTML para prevenir XSS
 */
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Debounce para búsquedas
 */
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

/**
 * Throttle para eventos frecuentes
 */
function throttle(func, limit) {
    let inThrottle;
    return function(...args) {
        if (!inThrottle) {
            func.apply(this, args);
            inThrottle = true;
            setTimeout(() => inThrottle = false, limit);
        }
    };
}

/**
 * API fetch wrapper con manejo de errores
 */
async function apiRequest(endpoint, options = {}) {
    const defaultOptions = {
        credentials: 'include',
        headers: {
            'Content-Type': 'application/json',
            ...options.headers
        }
    };
    
    const config = { ...defaultOptions, ...options };
    
    // Si hay body y es objeto, serializar
    if (config.body && typeof config.body === 'object') {
        config.body = JSON.stringify(config.body);
    }
    
    try {
        const response = await fetch(`${CONFIG.apiUrl}${endpoint}`, config);
        const data = await response.json();
        
        if (!response.ok) {
            throw new ApiError(data.message || 'Error en la solicitud', response.status, data);
        }
        
        return data;
        
    } catch (error) {
        if (error instanceof ApiError) throw error;
        throw new ApiError('Error de conexión', 0, { original: error.message });
    }
}

class ApiError extends Error {
    constructor(message, status, data) {
        super(message);
        this.name = 'ApiError';
        this.status = status;
        this.data = data;
    }
}

/**
 * Cargar contenido dinámico
 */
async function loadContent(url, containerId) {
    const container = document.getElementById(containerId);
    if (!container) return;
    
    container.innerHTML = `
        <div class="text-center py-5">
            <div class="spinner-border text-primary"></div>
        </div>
    `;
    
    try {
        const response = await fetch(url, { credentials: 'include' });
        if (!response.ok) throw new Error('Error cargando contenido');
        container.innerHTML = await response.text();
    } catch (error) {
        container.innerHTML = `
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle me-2"></i>
                Error al cargar el contenido
            </div>
        `;
    }
}

/**
 * Manejar formularios con AJAX
 */
function handleAjaxForm(formId, options = {}) {
    const form = document.getElementById(formId);
    if (!form) return;
    
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const submitBtn = form.querySelector('[type="submit"]');
        const originalText = submitBtn?.innerHTML;
        
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Procesando...';
        }
        
        try {
            const formData = new FormData(form);
            const data = Object.fromEntries(formData);
            
            const response = await apiRequest(options.endpoint || form.action, {
                method: options.method || form.method || 'POST',
                body: data
            });
            
            if (options.onSuccess) {
                options.onSuccess(response);
            } else {
                showAlert(response.message || 'Operación exitosa', 'success');
            }
            
            if (options.resetOnSuccess) {
                form.reset();
            }
            
        } catch (error) {
            if (options.onError) {
                options.onError(error);
            } else {
                showAlert(error.message, 'danger');
            }
        } finally {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        }
    });
}

/**
 * Paginación
 */
function renderPagination(containerId, pagination, onPageChange) {
    const container = document.getElementById(containerId);
    if (!container || !pagination) return;
    
    const { current_page, total_pages } = pagination;
    if (total_pages <= 1) {
        container.innerHTML = '';
        return;
    }
    
    let html = `
        <li class="page-item ${current_page <= 1 ? 'disabled' : ''}">
            <a class="page-link" href="#" data-page="${current_page - 1}">
                <i class="bi bi-chevron-left"></i>
            </a>
        </li>
    `;
    
    for (let i = 1; i <= total_pages; i++) {
        if (i === 1 || i === total_pages || (i >= current_page - 2 && i <= current_page + 2)) {
            html += `
                <li class="page-item ${i === current_page ? 'active' : ''}">
                    <a class="page-link" href="#" data-page="${i}">${i}</a>
                </li>
            `;
        } else if (i === current_page - 3 || i === current_page + 3) {
            html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
        }
    }
    
    html += `
        <li class="page-item ${current_page >= total_pages ? 'disabled' : ''}">
            <a class="page-link" href="#" data-page="${current_page + 1}">
                <i class="bi bi-chevron-right"></i>
            </a>
        </li>
    `;
    
    container.innerHTML = html;
    
    container.querySelectorAll('.page-link[data-page]').forEach(link => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            const page = parseInt(link.dataset.page);
            if (page >= 1 && page <= total_pages && page !== current_page) {
                onPageChange(page);
            }
        });
    });
}

/**
 * Estados de procesos
 */
const ESTADOS_PROCESO = {
    creado: { label: 'Creado', class: 'secondary', icon: 'plus-circle' },
    en_cola_analisis: { label: 'En Cola Análisis', class: 'info', icon: 'hourglass-split' },
    analizando: { label: 'Analizando', class: 'warning', icon: 'robot' },
    analizado: { label: 'Analizado', class: 'primary', icon: 'check-circle' },
    validado: { label: 'Validado', class: 'success', icon: 'check-circle-fill' },
    en_cola_llenado: { label: 'En Cola Llenado', class: 'info', icon: 'hourglass-split' },
    llenando: { label: 'Llenando', class: 'warning', icon: 'file-earmark-pdf' },
    completado: { label: 'Completado', class: 'success', icon: 'flag-fill' },
    error_analisis: { label: 'Error Análisis', class: 'danger', icon: 'exclamation-triangle' },
    error_llenado: { label: 'Error Llenado', class: 'danger', icon: 'exclamation-triangle' },
    cancelado: { label: 'Cancelado', class: 'dark', icon: 'x-circle' }
};

function getEstadoInfo(estado) {
    return ESTADOS_PROCESO[estado] || { label: estado, class: 'secondary', icon: 'question' };
}

function renderEstadoBadge(estado) {
    const info = getEstadoInfo(estado);
    return `<span class="badge bg-${info.class}"><i class="bi bi-${info.icon} me-1"></i>${info.label}</span>`;
}

// Exportar para uso global
window.CONFIG = CONFIG;
window.AppState = AppState;
window.showAlert = showAlert;
window.showToast = showToast;
window.confirmAction = confirmAction;
window.formatDate = formatDate;
window.formatCurrency = formatCurrency;
window.formatNumber = formatNumber;
window.formatFileSize = formatFileSize;
window.formatTimeAgo = formatTimeAgo;
window.escapeHtml = escapeHtml;
window.debounce = debounce;
window.throttle = throttle;
window.apiRequest = apiRequest;
window.loadContent = loadContent;
window.handleAjaxForm = handleAjaxForm;
window.renderPagination = renderPagination;
window.getEstadoInfo = getEstadoInfo;
window.renderEstadoBadge = renderEstadoBadge;

