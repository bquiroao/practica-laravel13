<?php

declare(strict_types=1);

// Front controller. Ejecutar con:  php -S 127.0.0.1:8080 -t public

use Rbac\Dominio\Excepciones\ErrorPersistencia;
use Rbac\Presentacion\Http\Solicitud;
use Rbac\Presentacion\Http\Vistas\ErrorVista;

require dirname(__DIR__) . '/bootstrap/contenedor.php';

try {
    $kernel = crearKernel(require dirname(__DIR__) . '/config/config.php');
} catch (ErrorPersistencia) {
    ErrorVista::servicioNoDisponible()->enviar();
    exit;
}

$kernel->manejar(Solicitud::desdeGlobales())->enviar();
