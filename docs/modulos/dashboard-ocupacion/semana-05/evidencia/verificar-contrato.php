<?php
declare(strict_types=1);

// Prueba de contrato: cada escenario pasa por las rutas y el controlador reales del Micro-HIS, y su respuesta debe estar
// documentada en api/openapi.yaml (operación, código HTTP y código de error). Uso: php verificar-contrato.php <openapi.json>
// El JSON sale de: npx @redocly/cli@1.34.3 bundle api/openapi.yaml --ext json -o <openapi.json>. Datos ficticios.
require __DIR__ . '/../../micro-his-dashboard-ocupacion/bootstrap.php';

use MicroHis\DashboardOcupacion\Aplicacion\Reloj;
use MicroHis\DashboardOcupacion\Persistencia\{RepositorioEnMemoria, RepositorioPdo};
use MicroHis\DashboardOcupacion\Presentacion\Rutas;
use function MicroHis\DashboardOcupacion\crearControlador;

$api = json_decode((string) file_get_contents($argv[1] ?? ''), true) ?? exit("Falta el OpenAPI en JSON (ver el encabezado).\n");
$errores = $api['components']['schemas']['Error']['properties']['error']['enum'];
$operaciones = [];   // "MÉTODO /ruta/{param}" => códigos documentados
foreach ($api['paths'] as $ruta => $metodos) foreach ($metodos as $m => $op) $operaciones[strtoupper($m) . " $ruta"] = array_map('intval', array_keys($op['responses']));   // PHP vuelve enteras las claves «201»

$fallos = 0;
function escenario(string $nombre, bool $ok, string $detalle): void { global $fallos; $fallos += $ok ? 0 : 1; printf("%s %s: %s\n", $ok ? 'OK   ' : 'FALLA', $nombre, $detalle); }

$reloj = new class implements Reloj { public function ahora(): int { return 1000; } };
$app = crearControlador(new RepositorioEnMemoria(['A' => true, 'B' => false], [['A', 'A1', 'available', false], ['A', 'A2', 'occupied', true], ['B', 'B1', 'occupied', true]]), $reloj);
$rota = crearControlador(new RepositorioPdo(new PDO('sqlite::memory:')), $reloj);   // sin tablas: cualquier lectura falla
// [nombre, método, ruta real, plantilla documentada, filtro (?estado=), controlador]
$casos = [['consultar sin corte todavía', 'GET', '/ocupacion', '/ocupacion', [], $app],
    ['consolidar un corte (R-05)', 'POST', '/ocupacion/cortes', '/ocupacion/cortes', [], $app],
    ['consultar el último corte', 'GET', '/ocupacion', '/ocupacion', [], $app],
    ['filtrar por disponibles (CU-03)', 'GET', '/ocupacion', '/ocupacion', ['estado' => 'available'], $app],
    ['estado de cama desconocido', 'GET', '/ocupacion', '/ocupacion', ['estado' => 'libre'], $app],
    ['consolidar con la base caída (R-09)', 'POST', '/ocupacion/cortes', '/ocupacion/cortes', [], $rota],
    ['consultar con la base caída', 'GET', '/ocupacion', '/ocupacion', [], $rota]];
foreach ($casos as [$nombre, $metodo, $ruta, $plantilla, $filtro, $controlador]) {
    [$accion, $parametros] = Rutas::resolver($metodo, $ruta);
    $r = $controlador->atender($accion, $parametros + $filtro);
    $documentados = $operaciones["$metodo $plantilla"] ?? [];
    $detalle = isset($r['vacio']) ? ($r['vacio'] ? ' «vacío»' : ' salas: ' . implode(', ', array_keys($r['salas']))) : '';
    escenario($nombre, in_array($r['status'], $documentados, true) && (!isset($r['error']) || in_array($r['error'], $errores, true)),
              "$metodo $ruta" . ($filtro ? "?estado={$filtro['estado']}" : '') . ' -> ' . rtrim("{$r['status']} " . ($r['error'] ?? '')) . "$detalle (documentados: " . implode(', ', $documentados) . ')');
}
// Cada operación documentada tiene su acción en las rutas, y cada acción del controlador está documentada.
$acciones = array_map(fn (string $op) => Rutas::resolver(...explode(' ', $op))[0], array_keys($operaciones));
preg_match_all("/case '(\w+)'/", file_get_contents(__DIR__ . '/../../micro-his-dashboard-ocupacion/src/Presentacion/ControladorOcupacion.php'), $delControlador);
$faltan = array_diff($delControlador[1], $acciones);
escenario('operaciones documentadas = acciones del controlador', !in_array('', $acciones, true) && $faltan === [],
          implode(', ', $acciones) . ($faltan ? '; sin documentar: ' . implode(', ', $faltan) : ''));
escenario('ruta no documentada', Rutas::resolver('DELETE', '/ocupacion')[0] === '' && $app->atender('', [])['status'] === 404, 'DELETE /ocupacion -> 404');

echo $fallos === 0 ? "RESULTADO: el Micro-HIS cumple el contrato\n" : "RESULTADO: $fallos escenario(s) fuera del contrato\n";
exit($fallos === 0 ? 0 : 1);
