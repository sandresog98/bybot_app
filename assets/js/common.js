/* common.js — Utilidades globales de cliente (ByBot App)
 * Depende de Bootstrap 5 cargado.
 * Expone window.ByApp con helpers.
 */
(function () {
  'use strict';

  const ByApp = {
    config: {
      appUrl: window.BYAPP_APP_URL || '',
      apiBase: (window.BYAPP_APP_URL || '') + 'app/api/v1',
    },

    /** Fetch con auth de sesión (cookies). Devuelve JSON parseado. */
    async api(path, opts = {}) {
      const url = path.startsWith('http') ? path : ByApp.config.apiBase + path;
      const cfg = Object.assign({
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
      }, opts);
      if (cfg.body && typeof cfg.body === 'object' && !(cfg.body instanceof FormData)) {
        cfg.headers = Object.assign({ 'Content-Type': 'application/json' }, cfg.headers || {});
        cfg.body = JSON.stringify(cfg.body);
      }
      const resp = await fetch(url, cfg);
      let payload = null;
      try { payload = await resp.json(); } catch (_) { payload = null; }
      if (!resp.ok || (payload && payload.success === false)) {
        const msg = (payload && payload.message) || ('HTTP ' + resp.status);
        ByApp.toast(msg, 'error');
        throw new Error(msg);
      }
      return payload;
    },

    /** Toast simple (pendiente de integrar con toasts de Bootstrap). */
    toast(message, type) {
      const colors = { ok: 'var(--by-ok)', info: 'var(--by-info)', warn: 'var(--by-warn)', error: 'var(--by-err)' };
      const t = document.createElement('div');
      t.className = 'toast-by';
      t.textContent = message;
      t.style.background = colors[type] || colors.info;
      document.body.appendChild(t);
      setTimeout(() => t.remove(), 3500);
    },

    /** Escape HTML rápido. */
    esc(s) {
      return String(s == null ? '' : s)
        .replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;').replaceAll("'", '&#39;');
    },

    /** Convierte un formulario a objeto {campo: valor}. */
    formToObj(form) {
      const data = {};
      new FormData(form).forEach((v, k) => { data[k] = v; });
      return data;
    },
  };

  window.ByApp = ByApp;

  // Confirmar antes de acciones data-confirm="..."
  document.addEventListener('click', (e) => {
    const el = e.target.closest('[data-confirm]');
    if (!el) return;
    if (!confirm(el.getAttribute('data-confirm') || '¿Confirmar acción?')) {
      e.preventDefault();
      e.stopPropagation();
    }
  });

  // Drag & drop básico para inputs tipo file
  document.addEventListener('dragover', (e) => {
    const dz = e.target.closest('.dropzone');
    if (dz) { e.preventDefault(); dz.classList.add('dragover'); }
  });
  document.addEventListener('dragleave', (e) => {
    const dz = e.target.closest('.dropzone');
    if (dz) dz.classList.remove('dragover');
  });
  document.addEventListener('drop', (e) => {
    const dz = e.target.closest('.dropzone');
    if (dz) {
      e.preventDefault();
      dz.classList.remove('dragover');
      const input = dz.querySelector('input[type=file]');
      if (input && e.dataTransfer.files.length) {
        input.files = e.dataTransfer.files;
        input.dispatchEvent(new Event('change'));
      }
    }
  });
})();