<?php
// Autocarga PSR-4 mínima: AlertasCriticas\X\Y -> src/X/Y.php (sin Composer).
declare(strict_types=1);

spl_autoload_register(function (string $clase): void {
    $prefijo = 'AlertasCriticas\\';
    if (str_starts_with($clase, $prefijo)) {
        $ruta = __DIR__ . '/src/' . str_replace('\\', '/', substr($clase, strlen($prefijo))) . '.php';
        if (is_file($ruta)) {
            require $ruta;
        }
    }
});
