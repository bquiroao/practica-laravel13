<?php

declare(strict_types=1);

// Front controller. Ejecutar con:  php -S 127.0.0.1:8080 -t public

use Rbac\Dominio\Excepciones\ErrorPersistencia;
use Rbac\Presentacion\Http\Respuesta;
use Rbac\Presentacion\Http\Solicitud;

require dirname(__DIR__) . '/bootstrap/contenedor.php';

try {
    $kernel = crearKernel(require dirname(__DIR__) . '/config/config.php');
} catch (ErrorPersistencia) {
    Respuesta::json(503, ['mensaje' => 'Servicio no disponible: no hay conexión con la base de datos.'])->enviar();
    exit;
}

$kernel->manejar(Solicitud::desdeGlobales())->enviar();
