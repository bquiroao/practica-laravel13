<?php
declare(strict_types=1);

// Entrada por consola del Micro-HIS. Ejemplo:
//   php consola.php camas --usuario=usr-adm-01 --servicio=MEDICINA_INTERNA --tipo=GENERAL

use Admision\Application\Puertos\GeneradorUuidV4;
use Admision\Presentation\Consola\AplicacionConsola;
use Admision\Presentation\UsuariosFicticios;

$crearServicios = require __DIR__ . '/bootstrap.php';
try {
    $servicios = $crearServicios();
} catch (PDOException $e) {
    fwrite(STDERR, 'No hay conexión con PostgreSQL en el puerto 55443. Revise docker compose ps.' . PHP_EOL);
    exit(2);
}
$datos = require __DIR__ . '/datos-ficticios.php';
$consola = new AplicacionConsola($servicios, new UsuariosFicticios($datos['usuarios']), new GeneradorUuidV4(), function (string $linea): void {
    echo $linea . PHP_EOL;
});
exit($consola->ejecutar($argv));
