<?php
declare(strict_types=1);

// Autoload PSR-4 mínimo: Alergias\X\Y -> src/X/Y.php (sin Composer).
spl_autoload_register(static function (string $clase): void {
    $prefijo = 'Alergias\\';
    if (str_starts_with($clase, $prefijo)) {
        $ruta = __DIR__ . '/src/' . str_replace('\\', '/', substr($clase, strlen($prefijo))) . '.php';
        if (is_file($ruta)) {
            require $ruta;
        }
    }
});
