<?php
declare(strict_types=1);

// Autocarga sin Composer: MicroHis\Domain\Filtros -> src/Domain/Filtros.php
spl_autoload_register(static function (string $clase): void {
    $prefijo = 'MicroHis\\';
    if (!str_starts_with($clase, $prefijo)) {
        return;
    }
    $ruta = __DIR__ . '/' . str_replace('\\', '/', substr($clase, strlen($prefijo))) . '.php';
    if (is_file($ruta)) {
        require $ruta;
    }
});
