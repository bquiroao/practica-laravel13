<?php

declare(strict_types=1);

// Front controller. Ejecutar con:  php -S 127.0.0.1:8081 -t public

use Pacientes\Dominio\Excepciones\ErrorPersistencia;
use Pacientes\Presentacion\Http\Solicitud;
use Pacientes\Presentacion\Http\Vistas\ErrorVista;

require dirname(__DIR__) . '/bootstrap/contenedor.php';

try {
    $kernel = crearKernel(require dirname(__DIR__) . '/config/config.php');
} catch (ErrorPersistencia) {
    ErrorVista::servicioNoDisponible()->enviar();
    exit;
} catch (InvalidArgumentException) {
    // Faltan las claves de protección de documentos o el modo de persistencia es inválido.
    ErrorVista::servicioNoDisponible('CONFIGURACION_INCOMPLETA')->enviar();
    exit;
}

$kernel->manejar(Solicitud::desdeGlobales())->enviar();
