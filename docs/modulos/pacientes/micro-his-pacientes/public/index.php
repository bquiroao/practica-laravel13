<?php

declare(strict_types=1);

// Front controller. Ejecutar con:  php -S 127.0.0.1:8081 -t public

use Pacientes\Dominio\Excepciones\ErrorPersistencia;
use Pacientes\Presentacion\Http\Respuesta;
use Pacientes\Presentacion\Http\Solicitud;

require dirname(__DIR__) . '/bootstrap/contenedor.php';

try {
    $kernel = crearKernel(require dirname(__DIR__) . '/config/config.php');
} catch (ErrorPersistencia) {
    Respuesta::error(503, 'SERVICIO_NO_DISPONIBLE')->enviar();
    exit;
} catch (InvalidArgumentException) {
    // Faltan las claves de protección de documentos: no se arranca sin ellas.
    Respuesta::error(503, 'CONFIGURACION_INCOMPLETA')->enviar();
    exit;
}

$kernel->manejar(Solicitud::desdeGlobales())->enviar();
