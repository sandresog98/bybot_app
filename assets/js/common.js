/**
 * JavaScript Común - ByBot v2.0
 */

// =============================================
// CONFIGURACIÓN GLOBAL
// =============================================
const ByBot = {
    config: {
        // apiBase se detecta automáticamente desde window.location o se inyecta desde PHP
        apiBase: window.APP_CONFIG?.apiBase || (() => {
            // Detectar automáticamente desde la URL actual
            const path = window.location.pathname;
            // Si estamos en /web/admin o /web/api, extraer el base
            const match = path.match(/^(\/.+?)\/web\/(admin|api)/);
            if (match) {
                return match[1] + '/web/api/v1';
            }
            // Fallback: intentar detectar desde la estructura común
            return '/web/api/v1';
        })(),
        wsUrl: null, // Se configura dinámicamente
        debug: true
    },
    
    // =============================================
    // UTILIDADES
    // =============================================
    utils: {
        /**
         * Formatear fecha
         */
        formatDate(date, format = 'DD/MM/YYYY') {
            if (!date) return '-';
            const d = new Date(date);
            const day = String(d.getDate()).padStart(2, '0');
            const month = String(d.getMonth() + 1).padStart(2, '0');
            const year = d.getFullYear();
            const hours = String(d.getHours()).padStart(2, '0');
            const minutes = String(d.getMinutes()).padStart(2, '0');
            
            return format
                .replace('DD', day)
                .replace('MM', month)
                .replace('YYYY', year)
                .replace('HH', hours)
                .replace('mm', minutes);
        },
        
        /**
         * Formatear moneda
         */
        formatCurrency(value, currency = 'COP') {
            if (value === null || value === undefined) return '-';
            return new Intl.NumberFormat('es-CO', {
                style: 'currency',
                currency: currency,
                minimumFractionDigits: 0
            }).format(value);
        },
        
        /**
         * Formatear número
         */
        formatNumber(value, decimals = 0) {
            if (value === null || value === undefined) return '-';
            return new Intl.NumberFormat('es-CO', {
                minimumFractionDigits: decimals,
                maximumFractionDigits: decimals
            }).format(value);
        },
        
        /**
         * Truncar texto
         */
        truncate(text, length = 50) {
            if (!text) return '';
            return text.length > length ? text.substring(0, length) + '...' : text;
        },
        
        /**
         * Debounce
         */
        debounce(func, wait = 300) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        },
        
        /**
         * Copiar al portapapeles
         */
        async copyToClipboard(text) {
            try {
                await navigator.clipboard.writeText(text);
                ByBot.toast.success('Copiado al portapapeles');
                return true;
            } catch (err) {
                ByBot.toast.error('Error al copiar');
                return false;
            }
        }
    },
    
    // =============================================
    // API CLIENT
    // =============================================
    api: {
        /**
         * Realizar petición fetch
         */
        async request(endpoint, options = {}) {
            const url = ByBot.config.apiBase + endpoint;
            
            const defaultOptions = {
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                }
            };
            
            const finalOptions = {
                ...defaultOptions,
                ...options,
                headers: {
                    ...defaultOptions.headers,
                    ...options.headers
                }
            };
            
            if (finalOptions.body && typeof finalOptions.body === 'object') {
                finalOptions.body = JSON.stringify(finalOptions.body);
            }
            
            try {
                const response = await fetch(url, finalOptions);
                const data = await response.json();
                
                if (!response.ok) {
                    throw new Error(data.message || 'Error en la petición');
                }
                
                return data;
            } catch (error) {
                if (ByBot.config.debug) {
                    console.error('API Error:', error);
                }
                throw error;
            }
        },
        
        get(endpoint) {
            return this.request(endpoint, { method: 'GET' });
        },
        
        post(endpoint, data) {
            return this.request(endpoint, { method: 'POST', body: data });
        },
        
        put(endpoint, data) {
            return this.request(endpoint, { method: 'PUT', body: data });
        },
        
        delete(endpoint) {
            return this.request(endpoint, { method: 'DELETE' });
        }
    },
    
    // =============================================
    // TOASTS (Notificaciones)
    // =============================================
    toast: {
        container: null,
        
        init() {
            if (!this.container) {
                this.container = document.createElement('div');
                this.container.className = 'toast-container';
                document.body.appendChild(this.container);
            }
        },
        
        show(message, type = 'info', title = null, duration = 5000) {
            this.init();
            
            const icons = {
                success: 'fas fa-check-circle',
                error: 'fas fa-times-circle',
                warning: 'fas fa-exclamation-triangle',
                info: 'fas fa-info-circle'
            };
            
            const titles = {
                success: 'Éxito',
                error: 'Error',
                warning: 'Advertencia',
                info: 'Información'
            };
            
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.innerHTML = `
                <div class="toast-icon">
                    <i class="${icons[type]}"></i>
                </div>
                <div class="toast-content">
                    <div class="toast-title">${title || titles[type]}</div>
                    <div class="toast-message">${message}</div>
                </div>
                <button class="toast-close" onclick="this.parentElement.remove()">
                    <i class="fas fa-times"></i>
                </button>
            `;
            
            this.container.appendChild(toast);
            
            if (duration > 0) {
                setTimeout(() => {
                    toast.style.animation = 'slideIn 0.3s ease reverse';
                    setTimeout(() => toast.remove(), 300);
                }, duration);
            }
            
            return toast;
        },
        
        success(message, title = null) {
            return this.show(message, 'success', title);
        },
        
        error(message, title = null) {
            return this.show(message, 'error', title);
        },
        
        warning(message, title = null) {
            return this.show(message, 'warning', title);
        },
        
        info(message, title = null) {
            return this.show(message, 'info', title);
        }
    },
    
    // =============================================
    // MODALES
    // =============================================
    modal: {
        /**
         * Confirmar acción
         */
        confirm(message, title = 'Confirmar', options = {}) {
            return new Promise((resolve) => {
                const modal = document.createElement('div');
                modal.className = 'modal-backdrop';
                modal.innerHTML = `
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">${title}</h5>
                                <button type="button" class="btn-close" data-action="cancel">&times;</button>
                            </div>
                            <div class="modal-body">
                                <p>${message}</p>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-action="cancel">
                                    ${options.cancelText || 'Cancelar'}
                                </button>
                                <button type="button" class="btn btn-${options.confirmClass || 'primary'}" data-action="confirm">
                                    ${options.confirmText || 'Confirmar'}
                                </button>
                            </div>
                        </div>
                    </div>
                `;
                
                document.body.appendChild(modal);
                
                modal.addEventListener('click', (e) => {
                    const action = e.target.dataset.action;
                    if (action === 'confirm') {
                        resolve(true);
                        modal.remove();
                    } else if (action === 'cancel' || e.target === modal) {
                        resolve(false);
                        modal.remove();
                    }
                });
            });
        },
        
        /**
         * Alerta simple
         */
        alert(message, title = 'Aviso') {
            return this.confirm(message, title, {
                confirmText: 'Aceptar',
                cancelText: null
            });
        }
    },
    
    // =============================================
    // FORMULARIOS
    // =============================================
    forms: {
        /**
         * Serializar formulario a objeto
         */
        serialize(form) {
            const formData = new FormData(form);
            const data = {};
            formData.forEach((value, key) => {
                if (data[key]) {
                    if (!Array.isArray(data[key])) {
                        data[key] = [data[key]];
                    }
                    data[key].push(value);
                } else {
                    data[key] = value;
                }
            });
            return data;
        },
        
        /**
         * Validar formulario
         */
        validate(form) {
            const inputs = form.querySelectorAll('[required]');
            let isValid = true;
            
            inputs.forEach(input => {
                if (!input.value.trim()) {
                    isValid = false;
                    input.classList.add('is-invalid');
                } else {
                    input.classList.remove('is-invalid');
                }
            });
            
            return isValid;
        },
        
        /**
         * Resetear estados de validación
         */
        resetValidation(form) {
            form.querySelectorAll('.is-invalid').forEach(el => {
                el.classList.remove('is-invalid');
            });
            form.querySelectorAll('.invalid-feedback').forEach(el => {
                el.textContent = '';
            });
        },
        
        /**
         * Mostrar errores de validación
         */
        showErrors(form, errors) {
            this.resetValidation(form);
            
            Object.entries(errors).forEach(([field, message]) => {
                const input = form.querySelector(`[name="${field}"]`);
                if (input) {
                    input.classList.add('is-invalid');
                    const feedback = input.parentElement.querySelector('.invalid-feedback');
                    if (feedback) {
                        feedback.textContent = message;
                    }
                }
            });
        }
    },
    
    // =============================================
    // LOADING STATES
    // =============================================
    loading: {
        /**
         * Mostrar loading en botón
         */
        button(button, show = true) {
            if (show) {
                button.disabled = true;
                button.dataset.originalText = button.innerHTML;
                button.innerHTML = '<span class="spinner spinner-sm"></span> Cargando...';
            } else {
                button.disabled = false;
                button.innerHTML = button.dataset.originalText || button.innerHTML;
            }
        },
        
        /**
         * Mostrar loading en contenedor
         */
        container(container, show = true) {
            if (show) {
                const overlay = document.createElement('div');
                overlay.className = 'loading-overlay';
                overlay.innerHTML = '<div class="spinner spinner-lg"></div>';
                container.style.position = 'relative';
                container.appendChild(overlay);
            } else {
                const overlay = container.querySelector('.loading-overlay');
                if (overlay) overlay.remove();
            }
        }
    },
    
    // =============================================
    // INICIALIZACIÓN
    // =============================================
    init() {
        // Inicializar tooltips de Bootstrap si existen
        if (typeof bootstrap !== 'undefined') {
            document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
                new bootstrap.Tooltip(el);
            });
        }
        
        // Manejar errores globales
        window.addEventListener('unhandledrejection', (event) => {
            if (this.config.debug) {
                console.error('Unhandled promise rejection:', event.reason);
            }
        });
        
        console.log('🤖 ByBot v2.0 inicializado');
    }
};

// Auto-inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', () => ByBot.init());

// Exportar para uso global
window.ByBot = ByBot;

