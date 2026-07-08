<?php
declare(strict_types=1);
/**
 * Layout footer — cierra el main y carga asset JS.
 */
?>
    </main>
</div><!-- /.app-main -->

</div><!-- /.app-shell -->

<script>
    window.BYAPP_APP_URL = <?= json_encode(by_url('')) ?>;
    window.BYAPP_PAGE    = <?= json_encode($_GET['page'] ?? '') ?>;
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= by_asset_url('js/common.js') ?>"></script>
<script src="<?= by_asset_url('js/admin.js') ?>"></script>
</body>
</html>