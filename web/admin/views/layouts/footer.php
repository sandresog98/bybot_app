        </div><!-- /.page-content -->
    </div><!-- /.main-content -->
    
    <!-- Bootstrap 5.3 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Chart.js (para gráficos) -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    
    <!-- Custom JS -->
    <script src="<?= assetUrl('js/common.js') ?>"></script>
    <script src="<?= assetUrl('js/admin.js') ?>"></script>
    
    <script>
        // Función helper para normalizar URLs (remover barras duplicadas)
        function normalizeUrl(base, endpoint) {
            // Remover barras finales del base
            base = base.replace(/\/+$/, '');
            // Remover barras iniciales del endpoint
            endpoint = endpoint.replace(/^\/+/, '');
            // Construir URL sin barras duplicadas
            return base + '/' + endpoint;
        }
        
        // Configuración global (solo si no existe ya)
        if (typeof CONFIG === 'undefined') {
            var CONFIG = {
                apiUrl: '<?= rtrim(API_URL, '/') ?>',
                adminUrl: '<?= rtrim(ADMIN_URL, '/') ?>',
                csrfToken: '<?= generateCsrfToken() ?>'
            };
        } else {
            // Actualizar valores si CONFIG ya existe
            CONFIG.apiUrl = '<?= rtrim(API_URL, '/') ?>';
            CONFIG.adminUrl = '<?= rtrim(ADMIN_URL, '/') ?>';
            CONFIG.csrfToken = '<?= generateCsrfToken() ?>';
        }
        
        // Agregar función helper al CONFIG
        CONFIG.apiUrlFor = function(endpoint) {
            return normalizeUrl(this.apiUrl, endpoint);
        };
        
        // Inicialización
        document.addEventListener('DOMContentLoaded', function() {
            // Toggle sidebar en móvil
            const sidebarToggle = document.getElementById('sidebarToggle');
            const sidebar = document.getElementById('sidebar');
            
            if (sidebarToggle && sidebar) {
                sidebarToggle.addEventListener('click', () => {
                    sidebar.classList.toggle('show');
                });
            }
            
            // Actualizar hora del servidor
            function updateServerTime() {
                const now = new Date();
                const timeStr = now.toLocaleTimeString('es-CO', { 
                    hour: '2-digit', 
                    minute: '2-digit' 
                });
                const elem = document.getElementById('serverTime');
                if (elem) elem.textContent = timeStr;
            }
            updateServerTime();
            setInterval(updateServerTime, 60000);
            
            // Cargar estado de colas
            loadQueueStatus();
            setInterval(loadQueueStatus, 30000);
        });
        
        // Cargar estado de colas para el sidebar
        async function loadQueueStatus() {
            try {
                const response = await fetch(CONFIG.apiUrlFor('colas/estado'), {
                    credentials: 'include'
                });
                if (response.ok) {
                    const data = await response.json();
                    const total = Object.values(data.data.colas || {}).reduce((a, b) => a + (b.pendientes || 0), 0);
                    const badge = document.getElementById('queueCount');
                    if (badge) {
                        badge.textContent = total;
                        badge.className = total > 0 ? 'badge bg-warning text-dark ms-auto' : 'badge bg-light text-dark ms-auto';
                    }
                }
            } catch (e) {
                console.log('Error cargando colas:', e);
            }
        }
    </script>
    
    <?php if (isset($extraJs)): ?>
        <?php foreach ($extraJs as $js): ?>
            <script src="<?= assetUrl($js) ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
    
    <?php if (isset($inlineJs)): ?>
        <script><?= $inlineJs ?></script>
    <?php endif; ?>
</body>
</html>

