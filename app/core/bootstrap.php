<?php
declare(strict_types=1);

/**
 * bootstrap.php — Autoloader + inicialización del núcleo.
 *
 * Convención PSR-4:
 *   Core\            -> app/core/
 *   Modules\<X>\     -> app/admin/modules/<x>/   (si se usa namespacing futuro)
 *
 * Carga Environ (que lee .env) y configura zona horaria.
 */

spl_autoload_register(function (string $class): void {
    $prefixes = [
        'Core\\' => __DIR__ . '/',
    ];
    foreach ($prefixes as $prefix => $base) {
        $len = strlen($prefix);
        if (strncmp($class, $prefix, $len) !== 0) {
            continue;
        }
        $rel = substr($class, $len);
        $file = $base . str_replace('\\', '/', $rel) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

require __DIR__ . '/Environ.php';

date_default_timezone_set(\Core\Environ::get('APP_TIMEZONE', 'America/Bogota'));
mb_internal_encoding('UTF-8');