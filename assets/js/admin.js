/* admin.js — Lógica específica del panel de admin (complementa common.js)
 * Sidebar colapsable, active state, helpers comunes.
 */
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', () => {
    // Toggle sidebar
    const toggle = document.getElementById('by-sidebar-toggle');
    const shell = document.getElementById('app-shell');
    if (toggle && shell) {
      toggle.addEventListener('click', () => shell.classList.toggle('sidebar-collapsed'));
    }

    // Marcar item de menú activo por atributo data-page
    const currentPage = window.BYAPP_PAGE || document.body.getAttribute('data-page');
    if (currentPage) {
      document.querySelectorAll('.app-sidebar .nav-link').forEach((a) => {
        if (a.getAttribute('data-page') === currentPage) a.classList.add('active');
      });
    }
  });
})();