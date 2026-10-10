<?php

declare(strict_types=1);

// Autoload PSR-4 mínimo, sin Composer.
spl_autoload_register(static function (string $clase): void {
    $raiz = dirname(__DIR__);
    $prefijos = [
        'Rbac\\Pruebas\\' => $raiz . '/tests/',
        'Rbac\\' => $raiz . '/src/',
    ];

    foreach ($prefijos as $prefijo => $directorio) {
        if (str_starts_with($clase, $prefijo)) {
            $archivo = $directorio . str_replace('\\', '/', substr($clase, strlen($prefijo))) . '.php';
            if (is_file($archivo)) {
                require $archivo;
            }

            return;
        }
    }
});
