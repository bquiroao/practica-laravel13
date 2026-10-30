<?php

declare(strict_types=1);

// Autoload PSR-4 mínimo para las clases de un archivo, más los archivos que agrupan tipos pequeños.
spl_autoload_register(static function (string $clase): void {
    if (str_starts_with($clase, 'Qa\\')) {
        $archivo = __DIR__ . '/src/' . str_replace('\\', '/', substr($clase, 3)) . '.php';
        if (is_file($archivo)) {
            require $archivo;
        }
    }
});
require_once __DIR__ . '/src/Dominio/Evidencia/Tipos.php';
require_once __DIR__ . '/src/Dominio/Modelo.php';
require_once __DIR__ . '/src/Aplicacion/Aplicacion.php';
require_once __DIR__ . '/src/Adaptadores/EnMemoria.php';
