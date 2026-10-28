<?php

declare(strict_types=1);

// Autoload PSR-4 mínimo, sin Composer: Pacientes\Pruebas\ → tests/, Pacientes\ → src/.
spl_autoload_register(static function (string $clase): void {
    $prefijos = [
        'Pacientes\\Pruebas\\' => dirname(__DIR__) . '/tests/',
        'Pacientes\\' => dirname(__DIR__) . '/src/',
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
