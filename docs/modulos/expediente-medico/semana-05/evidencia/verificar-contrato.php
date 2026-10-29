<?php
declare(strict_types=1);

// Prueba de contrato: cada escenario pasa por las rutas y el controlador reales del Micro-HIS, y su respuesta debe estar
// documentada en api/openapi.yaml (operación, código HTTP y código de error). Uso: php verificar-contrato.php <openapi.json>
// El JSON sale de: npx @redocly/cli@1.34.3 bundle api/openapi.yaml --ext json -o <openapi.json>. Datos ficticios.
$raiz = __DIR__ . '/../../micro-his-expediente';
if (!is_file("$raiz/src/Presentacion/Rutas.php")) { echo "El Micro-HIS de esta rama no tiene la entrada MVC: llega con la semana 4 (PR #349).\n"; exit(1); }
require "$raiz/bootstrap.php";

use MicroHis\Expediente\Aplicacion\{IndiceCentral, Reloj};
use MicroHis\Expediente\Persistencia\{CentralEnMemoria, HospitalEnMemoria};
use MicroHis\Expediente\Presentacion\Rutas;
use function MicroHis\Expediente\crearControlador;

$api = json_decode((string) file_get_contents($argv[1] ?? ''), true) ?? exit("Falta el OpenAPI en JSON (ver el encabezado).\n");
$errores = $api['components']['schemas']['Error']['properties']['error']['enum'];
$operaciones = [];   // "MÉTODO /ruta/{param}" => códigos documentados
foreach ($api['paths'] as $ruta => $metodos) foreach ($metodos as $m => $op) $operaciones[strtoupper($m) . " $ruta"] = array_map('intval', array_keys($op['responses']));   // PHP vuelve enteras las claves «201»

$fallos = 0;
function escenario(string $nombre, bool $ok, string $detalle): void { global $fallos; $fallos += $ok ? 0 : 1; printf("%s %s: %s\n", $ok ? 'OK   ' : 'FALLA', $nombre, $detalle); }

// p1 activo y vinculado al MPI con consentimiento; p2 activo sin vínculo ni expediente; p3 inactivo; p4 vinculado sin consentimiento. m1 atiende a p1, p2 y p4.
$reloj = new class implements Reloj { public function ahora(): int { return 1000; } };
$hospital = new HospitalEnMemoria(['p1' => ['g1', 'active', 'linked'], 'p2' => ['g2', 'active', 'pending'], 'p3' => ['g3', 'inactive', 'linked'], 'p4' => ['g4', 'active', 'linked']],
                                  [['p1', 'm1'], ['p2', 'm1'], ['p4', 'm1']]);
$app = crearControlador($hospital, new CentralEnMemoria(['g1|h1' => ['desde' => 0, 'hasta' => null]], [['g1', 'h2', 'EXP-H2-77']]), $reloj, 'h1');
$caida = new class implements IndiceCentral {
    public function consentimiento(string $g, string $h): ?array { throw new MicroHis\Expediente\Aplicacion\ErrorPersistencia('CENTRAL caída'); }
    public function expedientesExternos(string $g, string $h): array { return []; }
};
$sinCentral = crearControlador($hospital, $caida, $reloj, 'h1');
// [nombre, método, ruta real, plantilla documentada, profesional, controlador]
$casos = [['abrir expediente', 'POST', '/patients/p1/record', '/patients/{id}/record', 'admision1', $app],
    ['abrir otra vez (R-01)', 'POST', '/patients/p1/record', '/patients/{id}/record', 'admision1', $app],
    ['abrir a un paciente inactivo (R-03)', 'POST', '/patients/p3/record', '/patients/{id}/record', 'admision1', $app],
    ['consulta local con asignación', 'GET', '/patients/p1/record', '/patients/{id}/record', 'm1', $app],
    ['consulta sin asignación activa (R-05)', 'GET', '/patients/p1/record', '/patients/{id}/record', 'm2', $app],
    ['consulta de un paciente sin expediente', 'GET', '/patients/p2/record', '/patients/{id}/record', 'm1', $app],
    ['historia externa con consentimiento', 'GET', '/patients/p1/record/external', '/patients/{id}/record/external', 'm1', $app],
    ['historia externa sin consentimiento (R-06)', 'GET', '/patients/p4/record/external', '/patients/{id}/record/external', 'm1', $app],
    ['historia externa con CENTRAL caída', 'GET', '/patients/p1/record/external', '/patients/{id}/record/external', 'm1', $sinCentral]];
$app->atender('abrir', ['paciente' => 'p4', 'profesional' => 'admision1']);   // p4 necesita expediente para llegar al consentimiento
foreach ($casos as [$nombre, $metodo, $ruta, $plantilla, $profesional, $controlador]) {
    [$accion, $parametros] = Rutas::resolver($metodo, $ruta);
    $r = $controlador->atender($accion, $parametros + ['profesional' => $profesional]);
    $documentados = $operaciones["$metodo $plantilla"] ?? [];
    escenario($nombre, in_array($r['status'], $documentados, true) && (!isset($r['error']) || in_array($r['error'], $errores, true)),
              "$metodo $ruta -> " . rtrim("{$r['status']} " . ($r['error'] ?? '')) . " (documentados: " . implode(', ', $documentados) . ')');
}
// Cada operación documentada tiene su acción en las rutas, y cada acción del controlador está documentada.
$acciones = array_map(fn (string $op) => Rutas::resolver(...explode(' ', preg_replace('/\{[^}]+\}/', 'p1', $op)))[0], array_keys($operaciones));
preg_match_all("/case '(\w+)'/", file_get_contents("$raiz/src/Presentacion/ControladorExpediente.php"), $delControlador);
$faltan = array_diff($delControlador[1], $acciones);
escenario('operaciones documentadas = acciones del controlador', !in_array('', $acciones, true) && $faltan === [],
          implode(', ', $acciones) . ($faltan ? '; sin documentar: ' . implode(', ', $faltan) : ''));
escenario('ruta no documentada', Rutas::resolver('DELETE', '/patients/p1/record')[0] === '' && $app->atender('', [])['status'] === 404, 'DELETE /patients/p1/record -> 404');

echo $fallos === 0 ? "RESULTADO: el Micro-HIS cumple el contrato\n" : "RESULTADO: $fallos escenario(s) fuera del contrato\n";
exit($fallos === 0 ? 0 : 1);
