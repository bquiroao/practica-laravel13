<?php
declare(strict_types=1);

// Prueba de contrato: cada escenario pasa por las rutas y el controlador reales del Micro-HIS, y su respuesta debe estar
// documentada en api/openapi.yaml (operación, código HTTP y código de error). Uso: php verificar-contrato.php <openapi.json>
// El JSON sale de: npx @redocly/cli@1.34.3 bundle api/openapi.yaml --ext json -o <openapi.json>. Datos ficticios.
require __DIR__ . '/../../micro-his-catalogo-camas/bootstrap.php';

use MicroHis\Catalogo\Persistencia\{RepositorioEnMemoria, RepositorioPdo};
use MicroHis\Catalogo\Presentacion\Rutas;
use function MicroHis\Catalogo\crearControlador;

$api = json_decode((string) file_get_contents($argv[1] ?? ''), true) ?? exit("Falta el OpenAPI en JSON (ver el encabezado).\n");
$errores = $api['components']['schemas']['Error']['properties']['error']['enum'];
$operaciones = [];   // "MÉTODO /ruta/{param}" => códigos documentados
foreach ($api['paths'] as $ruta => $metodos) foreach ($metodos as $m => $op) $operaciones[strtoupper($m) . " $ruta"] = array_map('intval', array_keys($op['responses']));   // PHP vuelve enteras las claves «201»

$fallos = 0;
function escenario(string $nombre, bool $ok, string $detalle): void { global $fallos; $fallos += $ok ? 0 : 1; printf("%s %s: %s\n", $ok ? 'OK   ' : 'FALLA', $nombre, $detalle); }

$app = crearControlador(new RepositorioEnMemoria());
$rota = new PDO('sqlite::memory:');   // sin tablas: cualquier consulta falla y debe responder 503
// [nombre, método, ruta real, plantilla documentada, cuerpo, controlador]
$casos = [['alta de sala', 'POST', '/salas', '/salas', ['codigo' => 'A', 'nombre' => 'Medicina interna'], $app],
    ['sala repetida (R-01)', 'POST', '/salas', '/salas', ['codigo' => 'A', 'nombre' => 'Otra'], $app],
    ['sala sin nombre', 'POST', '/salas', '/salas', ['codigo' => 'B', 'nombre' => ''], $app],
    ['alta de cama', 'POST', '/salas/A/camas', '/salas/{sala}/camas', ['codigo' => '101'], $app],
    ['cama en sala inexistente (R-03)', 'POST', '/salas/Z/camas', '/salas/{sala}/camas', ['codigo' => '1'], $app],
    ['cama a mantenimiento', 'PATCH', '/salas/A/camas/101/estado', '/salas/{sala}/camas/{cama}/estado', ['estado' => 'maintenance', 'motivo' => 'limpieza'], $app],
    ['transición no permitida (R-05)', 'PATCH', '/salas/A/camas/101/estado', '/salas/{sala}/camas/{cama}/estado', ['estado' => 'maintenance', 'motivo' => 'x'], $app],
    ['cama inexistente', 'PATCH', '/salas/A/camas/999/estado', '/salas/{sala}/camas/{cama}/estado', ['estado' => 'inactive', 'motivo' => 'x'], $app],
    ['base caída', 'POST', '/salas', '/salas', ['codigo' => 'C', 'nombre' => 'Cirugía'], crearControlador(new RepositorioPdo($rota))]];
foreach ($casos as [$nombre, $metodo, $ruta, $plantilla, $cuerpo, $controlador]) {
    [$accion, $parametros] = Rutas::resolver($metodo, $ruta);
    $r = $controlador->atender($accion, $parametros + $cuerpo);
    $documentados = $operaciones["$metodo $plantilla"] ?? [];
    escenario($nombre, in_array($r['status'], $documentados, true) && (!isset($r['error']) || in_array($r['error'], $errores, true)),
              "$metodo $ruta -> " . rtrim("{$r['status']} " . ($r['error'] ?? '')) . " (documentados: " . implode(', ', $documentados) . ')');
}
// Cada operación documentada tiene su acción en las rutas, y cada acción de las rutas está documentada.
$acciones = array_map(fn (string $op) => Rutas::resolver(...explode(' ', preg_replace('/\{[^}]+\}/', 'x', $op)))[0], array_keys($operaciones));
preg_match_all("/case '(\w+)'/", file_get_contents(__DIR__ . '/../../micro-his-catalogo-camas/src/Presentacion/ControladorCatalogo.php'), $delControlador);
$faltan = array_diff($delControlador[1], $acciones);
escenario('operaciones documentadas = acciones del controlador', !in_array('', $acciones, true) && $faltan === [],
          implode(', ', $acciones) . ($faltan ? '; sin documentar: ' . implode(', ', $faltan) : ''));
$noDocumentada = Rutas::resolver('GET', '/salas');
escenario('ruta no documentada', $noDocumentada[0] === '' && $app->atender('', [])['status'] === 404, 'GET /salas -> 404');

echo $fallos === 0 ? "RESULTADO: el Micro-HIS cumple el contrato\n" : "RESULTADO: $fallos escenario(s) fuera del contrato\n";
exit($fallos === 0 ? 0 : 1);
