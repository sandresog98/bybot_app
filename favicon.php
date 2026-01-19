<?php
/**
 * Favicon Handler
 * Sirve el favicon desde assets/favicons/ o genera uno simple si no existe
 */

$faviconPath = __DIR__ . '/assets/favicons/favicon.ico';

// Si existe el favicon en assets, servirlo
if (file_exists($faviconPath)) {
    header('Content-Type: image/x-icon');
    header('Cache-Control: public, max-age=31536000'); // Cache por 1 año
    readfile($faviconPath);
    exit;
}

// Si no existe, generar un favicon simple (16x16 PNG como ICO)
// Crear una imagen simple con las iniciales "BB" (ByBot)
$size = 16;
$image = imagecreatetruecolor($size, $size);
$bg = imagecolorallocate($image, 0, 46, 101); // Color primario #002e65
$text = imagecolorallocate($image, 255, 255, 255); // Blanco

imagefilledrectangle($image, 0, 0, $size, $size, $bg);

// Intentar escribir texto (requiere GD con FreeType)
if (function_exists('imagestring')) {
    imagestring($image, 2, 2, 2, 'BB', $text);
}

// Para navegadores modernos, PNG funciona bien como favicon
header('Content-Type: image/png');
header('Cache-Control: public, max-age=31536000');
imagepng($image);
imagedestroy($image);
exit;

