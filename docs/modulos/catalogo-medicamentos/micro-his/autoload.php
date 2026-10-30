<?php
declare(strict_types=1);

// Autocarga PSR-4 mínima, sin Composer: Catalogo\X\Y -> src/X/Y.php
spl_autoload_register(static function (string $clase): void {
    $prefijo = 'Catalogo\\';
    if (str_starts_with($clase, $prefijo)) {
        $archivo = __DIR__ . '/src/' . str_replace('\\', '/', substr($clase, strlen($prefijo))) . '.php';
        if (is_file($archivo)) {
            require $archivo;
        }
    }
});
