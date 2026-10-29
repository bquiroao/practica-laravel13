<?php
declare(strict_types=1);

// Entrada web (MVC) del Micro-HIS. Desde micro-his/:
//   php -S 127.0.0.1:8085 public/index.php
// y abrir http://127.0.0.1:8085/

use Admision\Application\Puertos\GeneradorUuidV4;
use Admision\Presentation\UsuariosFicticios;
use Admision\Presentation\Web\AdmisionController;
use Admision\Presentation\Web\Peticion;
use Admision\Presentation\Web\Respuesta;
use Admision\Presentation\Web\Router;
use Admision\Presentation\Web\Vista;

$crearServicios = require __DIR__ . '/../bootstrap.php';
try {
    $servicios = $crearServicios();
} catch (PDOException $e) {
    (new Respuesta(503, 'Base de datos no disponible (puerto 55443).', ['Content-Type' => 'text/plain; charset=utf-8']))->enviar();
    return;
}
$datos = require __DIR__ . '/../datos-ficticios.php';
$web = new AdmisionController($servicios, new UsuariosFicticios($datos['usuarios']), new GeneradorUuidV4(), new Vista(__DIR__ . '/../src/Presentation/Web/vistas'));

$router = new Router();
$router->agregar('GET', '/', fn (Peticion $p) => $web->formulario($p));
$router->agregar('GET', '/camas', fn (Peticion $p) => $web->camas($p));
$router->agregar('POST', '/admisiones', fn (Peticion $p) => $web->registrar($p));
$router->despachar(Peticion::desdeGlobales())->enviar();
