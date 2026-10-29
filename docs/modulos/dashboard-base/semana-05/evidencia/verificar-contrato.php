<?php
declare(strict_types=1);

// Prueba de contrato: cada escenario pasa por las rutas y el controlador reales del Micro-HIS, y su respuesta debe estar
// documentada en api/openapi.yaml (operación, código HTTP y código de error). Uso: php verificar-contrato.php <openapi.json>
// El JSON sale de: npx @redocly/cli@1.34.3 bundle api/openapi.yaml --ext json -o <openapi.json>. Datos ficticios.
require __DIR__ . '/../../micro-his-dashboard-base/bootstrap.php';

use MicroHis\DashboardBase\Aplicacion\Reloj;
use MicroHis\DashboardBase\Persistencia\{RepositorioEnMemoria, RepositorioPdo};
use MicroHis\DashboardBase\Presentacion\Rutas;
use function MicroHis\DashboardBase\crearControlador;

$api = json_decode((string) file_get_contents($argv[1] ?? ''), true) ?? exit("Falta el OpenAPI en JSON (ver el encabezado).\n");
$errores = $api['components']['schemas']['Error']['properties']['error']['enum'];
$operaciones = [];   // "MÉTODO /ruta/{param}" => códigos documentados
foreach ($api['paths'] as $ruta => $metodos) foreach ($metodos as $m => $op) $operaciones[strtoupper($m) . " $ruta"] = array_map('intval', array_keys($op['responses']));   // PHP vuelve enteras las claves «201»

$fallos = 0;
function escenario(string $nombre, bool $ok, string $detalle): void { global $fallos; $fallos += $ok ? 0 : 1; printf("%s %s: %s\n", $ok ? 'OK   ' : 'FALLA', $nombre, $detalle); }

$reloj = new class implements Reloj { public function hoy(): string { return '2026-10-29'; } };
$app = crearControlador(new RepositorioEnMemoria(['active', 'inactive'], [['2026-10-29 08:00', 'scheduled']], ['maintenance']), $reloj);
$rota = crearControlador(new RepositorioPdo(new PDO('sqlite::memory:')), $reloj);   // sin tablas: la fuente de la tarjeta falla
$sesion = fn (string $hospital, string ...$permisos) => ['hospital' => $hospital, 'permisos' => $permisos];
$todos = $sesion('h1', 'pacientes.ver', 'citas.ver', 'camas.ver');
// [nombre, método, ruta real, plantilla documentada, sesión, controlador]
$casos = [['inicio del rol', 'GET', '/dashboard', '/dashboard', $todos, $app],
    ['sin hospital seleccionado (R-01)', 'GET', '/dashboard', '/dashboard', $sesion('', 'citas.ver'), $app],
    ['tarjeta con resumen', 'GET', '/dashboard/tarjetas/citas_hoy', '/dashboard/tarjetas/{id}', $todos, $app],
    ['tarjeta vacía, no error (R-05)', 'GET', '/dashboard/tarjetas/camas_disponibles', '/dashboard/tarjetas/{id}', $todos, $app],
    ['tarjeta sin permiso (R-07)', 'GET', '/dashboard/tarjetas/pacientes', '/dashboard/tarjetas/{id}', $sesion('h1', 'citas.ver'), $app],
    ['tarjeta desconocida (H-03)', 'GET', '/dashboard/tarjetas/otra', '/dashboard/tarjetas/{id}', $todos, $app],
    ['tarjeta sin sesión', 'GET', '/dashboard/tarjetas/pacientes', '/dashboard/tarjetas/{id}', $sesion('', 'pacientes.ver'), $app],
    ['fuente de la tarjeta caída (R-03)', 'GET', '/dashboard/tarjetas/pacientes', '/dashboard/tarjetas/{id}', $todos, $rota]];
foreach ($casos as [$nombre, $metodo, $ruta, $plantilla, $datos, $controlador]) {
    [$accion, $parametros] = Rutas::resolver($metodo, $ruta);
    $r = $controlador->atender($accion, $parametros + $datos);
    $documentados = $operaciones["$metodo $plantilla"] ?? [];
    escenario($nombre, in_array($r['status'], $documentados, true) && (!isset($r['error']) || in_array($r['error'], $errores, true)),
              "$metodo $ruta -> " . rtrim("{$r['status']} " . ($r['error'] ?? '')) . (isset($r['estado']) ? " «{$r['estado']}»" : '') . " (documentados: " . implode(', ', $documentados) . ')');
}
// Cada operación documentada tiene su acción en las rutas, y cada acción del controlador está documentada.
$acciones = array_map(fn (string $op) => Rutas::resolver(...explode(' ', preg_replace('/\{[^}]+\}/', 'pacientes', $op)))[0], array_keys($operaciones));
preg_match_all("/case '(\w+)'/", file_get_contents(__DIR__ . '/../../micro-his-dashboard-base/src/Presentacion/ControladorDashboard.php'), $delControlador);
$faltan = array_diff($delControlador[1], $acciones);
escenario('operaciones documentadas = acciones del controlador', !in_array('', $acciones, true) && $faltan === [],
          implode(', ', $acciones) . ($faltan ? '; sin documentar: ' . implode(', ', $faltan) : ''));
escenario('ruta no documentada', Rutas::resolver('GET', '/pacientes')[0] === '' && $app->atender('', [])['status'] === 404, 'GET /pacientes -> 404');

echo $fallos === 0 ? "RESULTADO: el Micro-HIS cumple el contrato\n" : "RESULTADO: $fallos escenario(s) fuera del contrato\n";
exit($fallos === 0 ? 0 : 1);
