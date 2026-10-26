<?php

declare(strict_types=1);

// Front controller. Ejecutar con:  php -S 127.0.0.1:8080 -t public
// Sin base de datos:                NOTASSOAP_ALMACEN=memoria php -S 127.0.0.1:8080 -t public
// Sin base y conservando datos:     agregar NOTASSOAP_MEMORIA_ARCHIVO=/ruta/temporal/notas.ser

use NotasSoap\Dominio\Excepciones\ErrorPersistencia;
use NotasSoap\Presentacion\Http\Respuesta;
use NotasSoap\Presentacion\Http\Solicitud;

require dirname(__DIR__) . '/bootstrap/contenedor.php';

try {
    $kernel = crearKernel(require dirname(__DIR__) . '/config/config.php');
} catch (ErrorPersistencia) {
    Respuesta::error(503, 'servicio_no_disponible', 'No hay conexión con la base de datos.')->conCabecera('Retry-After', '5')->enviar();
    exit;
}

$kernel->manejar(Solicitud::desdeGlobales())->enviar();
