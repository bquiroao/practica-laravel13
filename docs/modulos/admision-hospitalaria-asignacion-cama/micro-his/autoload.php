<?php
declare(strict_types=1);

// Autocarga PSR-4 mínima, sin Composer: Admision\X\Y -> src/X/Y.php
spl_autoload_register(static function (string $clase): void {
    $prefijo = 'Admision\\';
    if (!str_starts_with($clase, $prefijo)) {
        return;
    }
    $ruta = __DIR__ . '/src/' . str_replace('\\', '/', substr($clase, strlen($prefijo))) . '.php';
    if (is_file($ruta)) {
        require $ruta;
    }
});
