<?php

declare(strict_types=1);

// Autoload PSR-4 mínimo, sin Composer: Rbac\Extension\ → extension/, Rbac\ → src/.
spl_autoload_register(static function (string $clase): void {
    $prefijos = [
        'Rbac\\Extension\\' => __DIR__ . '/extension/',
        'Rbac\\' => __DIR__ . '/src/',
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
