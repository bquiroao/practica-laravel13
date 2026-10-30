<?php
/**
 * Semana 15 · Defectos REGISTRADOS y abiertos. Cada prueba afirma el comportamiento ACTUAL (defectuoso) para
 * documentarlo sin romper la suite; al corregir el defecto, la prueba fallará y debe invertirse.
 * D-01 y D-02 se corrigieron en la semana 3 y sus pruebas de regresión están en unidad/*.
 * @var Pruebas $t
 */
declare(strict_types=1);

require_once __DIR__ . '/../dobles/RepositorioDoble.php';
require_once __DIR__ . '/../../bootstrap.php';

use AlertasCriticas\Persistence\{NotificadorSimulado, RelojFijo};
use AlertasCriticas\Presentation\Web\{AlertaController, Peticion, Router, Rutas, Vista};

$t->prueba('[DEFECTO D-03] la web MVC deja cerrar a enfermería (RF-08); debería responder 403', function () use ($t) {
    $router = new Router();
    Rutas::registrar($router, new AlertaController(contenedorAlertas(['reloj' => new RelojFijo('2026-10-29 10:00:00'),
        'repositorio' => new RepositorioDoble(), 'notificador' => new NotificadorSimulado()]), new Vista(__DIR__ . '/../../plantillas')));
    $r = $router->despachar(new Peticion('POST', '/alertas', [], ['codigo_paciente' => 'PAC-00451', 'servicio' => 'MED-INT',
        'origen' => 'lab', 'id_origen' => 'L1', 'prioridad' => 'CRITICA', 'descripcion' => 'x']));
    $id = preg_replace('#^/alertas/([^?]+).*$#', '$1', $r->cabeceras['Location']);
    $enf = ['usuario_demo' => 'enf.turno01'];
    $router->despachar(new Peticion('POST', "/alertas/$id/acuse", [], [], $enf));
    $cierre = $router->despachar(new Peticion('POST', "/alertas/$id/cierre", [], ['nota' => 'x'], $enf));
    $t->igual(303, $cierre->estado, 'Comportamiento actual: cierra (303)');
});

$t->prueba('[DEFECTO D-04] con la base caída al arrancar, /api/* responde texto plano y no el JSON del contrato', function () use ($t) {
    $envoltura = tempnam(sys_get_temp_dir(), 'd04') . '.php';
    file_put_contents($envoltura, '<?php $_SERVER["REQUEST_URI"]="/api/v1/alertas"; $_SERVER["REQUEST_METHOD"]="GET"; require '
        . var_export(realpath(__DIR__ . '/../../public/index.php'), true) . ';');
    putenv('ALERTAS_DB_PUERTO=1'); // puerto sin servidor; putenv funciona igual en Windows y Linux
    $salida = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($envoltura) . ' 2>&1');
    putenv('ALERTAS_DB_PUERTO');
    unlink($envoltura);
    $t->verdadero(json_decode((string) $salida, true) === null && str_contains((string) $salida, 'Base de datos no disponible'),
        'Comportamiento actual: texto plano. Salida: ' . $salida);
});

$t->prueba('[DEFECTO D-05] la bitácora del notificador usa la hora real, no el Reloj inyectado', function () use ($t) {
    $archivo = tempnam(sys_get_temp_dir(), 'd05');
    (new NotificadorSimulado([], $archivo))->enviar('enf.turno01', 'm');
    $linea = (string) file_get_contents($archivo);
    unlink($archivo);
    $t->verdadero(!str_starts_with($linea, '2026-10-29T10:00'), 'Comportamiento actual: no usa el reloj fijo. Línea: ' . $linea);
});
