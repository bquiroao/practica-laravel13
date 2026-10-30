<?php

declare(strict_types=1);

// Front controller. Uso: php -S 127.0.0.1:8093 -t public   (desde micro-his-qa/)

require dirname(__DIR__) . '/bootstrap/contenedor.php';

use Qa\Presentacion\Http\Respuesta;
use Qa\Presentacion\Http\Solicitud;

try {
    $kernel = crearKernel(require dirname(__DIR__) . '/config/config.php');
    $kernel->manejar(Solicitud::desdeGlobales())->enviar();
} catch (\Throwable) {
    // Sin conexión a la base al arrancar: 503 genérico, sin el DSN ni la contraseña.
    Respuesta::json(503, ['error' => 'SERVICIO_NO_DISPONIBLE'])->enviar();
}
