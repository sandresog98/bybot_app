<?php
declare(strict_types=1);

require_once __DIR__ . '/../core/bootstrap.php';

use Admin\Controllers\AuthController;

require __DIR__ . '/controllers/AuthController.php';

(new AuthController())->login();