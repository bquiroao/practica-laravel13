<?php
// Entrada web (MVC). Servidor de desarrollo, desde micro-his/:  php -S 127.0.0.1:8084 -t public
declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

use AlertasCriticas\Domain\ErrorPersistenciaException;
use AlertasCriticas\Presentation\Web\{AlertaController, Peticion, Respuesta, Router, Rutas, Vista};

try {
    $router = new Router();
    Rutas::registrar($router, new AlertaController(contenedorAlertas(), new Vista(__DIR__ . '/../plantillas')));
    $respuesta = $router->despachar(Peticion::desdeGlobales());
} catch (ErrorPersistenciaException) {
    $respuesta = new Respuesta(503, 'Base de datos no disponible. Revise: docker compose ps',
        ['Content-Type' => 'text/plain; charset=utf-8']);
}
$respuesta->enviar();
