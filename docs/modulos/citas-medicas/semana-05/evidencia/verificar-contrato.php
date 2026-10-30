<?php
declare(strict_types=1);

// Prueba de contrato: cada escenario pasa por las rutas y el controlador reales del Micro-HIS, y su respuesta debe estar
// documentada en api/openapi.yaml (operación, código HTTP y código de error). Uso: php verificar-contrato.php <openapi.json>
// El JSON sale de: npx @redocly/cli@1.34.3 bundle api/openapi.yaml --ext json -o <openapi.json>. Datos ficticios.
require __DIR__ . '/../../micro-his-citas/bootstrap.php';

use MicroHis\Citas\Aplicacion\Reloj;
use MicroHis\Citas\Persistencia\{RepositorioEnMemoria, RepositorioPdo};
use MicroHis\Citas\Presentacion\Rutas;
use function MicroHis\Citas\crearControlador;

$api = json_decode((string) file_get_contents($argv[1] ?? ''), true) ?? exit("Falta el OpenAPI en JSON (ver el encabezado).\n");
$errores = $api['components']['schemas']['Error']['properties']['error']['enum'];
$operaciones = [];   // "MÉTODO /ruta/{param}" => códigos documentados
foreach ($api['paths'] as $ruta => $metodos) foreach ($metodos as $m => $op) $operaciones[strtoupper($m) . " $ruta"] = array_map('intval', array_keys($op['responses']));   // PHP vuelve enteras las claves «201»

$fallos = 0;
function escenario(string $nombre, bool $ok, string $detalle): void { global $fallos; $fallos += $ok ? 0 : 1; printf("%s %s: %s\n", $ok ? 'OK   ' : 'FALLA', $nombre, $detalle); }

$reloj = new class implements Reloj { public function ahora(): int { return 1000; } };
$app = crearControlador(new RepositorioEnMemoria([['m1', 'cardio'], ['m2', 'cardio']]), $reloj);
$rota = new PDO('sqlite::memory:');   // sin tablas: cualquier consulta falla y debe responder 503
$cita = fn (string $m, string $p, int $i) => ['medico' => $m, 'paciente' => $p, 'especialidad' => 'cardio', 'inicio' => $i, 'fin' => $i + 1800, 'motivo' => 'control'];
// [nombre, método, ruta real, plantilla documentada, cuerpo, controlador]
$casos = [['solicitar cita', 'POST', '/citas', '/citas', $cita('m1', 'p1', 2000), $app],
    ['médico ocupado (R-02)', 'POST', '/citas', '/citas', $cita('m1', 'p2', 2000), $app],
    ['paciente ocupado (R-03)', 'POST', '/citas', '/citas', $cita('m2', 'p1', 2000), $app],
    ['cita en el pasado (R-01)', 'POST', '/citas', '/citas', $cita('m2', 'p3', 500), $app],
    ['médico sin especialidad (R-04)', 'POST', '/citas', '/citas', $cita('m3', 'p3', 5000), $app],
    ['confirmar', 'POST', '/citas/1/confirmacion', '/citas/{id}/confirmacion', [], $app],
    ['cancelar con motivo', 'POST', '/citas/1/cancelacion', '/citas/{id}/cancelacion', ['motivo' => 'viaje'], $app],
    ['cancelar otra vez (R-07)', 'POST', '/citas/1/cancelacion', '/citas/{id}/cancelacion', ['motivo' => 'otra vez'], $app],
    ['cita inexistente', 'POST', '/citas/99/confirmacion', '/citas/{id}/confirmacion', [], $app],
    ['base caída', 'POST', '/citas', '/citas', $cita('m1', 'p9', 8000), crearControlador(new RepositorioPdo($rota), $reloj)]];
foreach ($casos as [$nombre, $metodo, $ruta, $plantilla, $cuerpo, $controlador]) {
    [$accion, $parametros] = Rutas::resolver($metodo, $ruta);
    $r = $controlador->atender($accion, $parametros + $cuerpo);
    $documentados = $operaciones["$metodo $plantilla"] ?? [];
    escenario($nombre, in_array($r['status'], $documentados, true) && (!isset($r['error']) || in_array($r['error'], $errores, true)),
              "$metodo $ruta -> " . rtrim("{$r['status']} " . ($r['error'] ?? '')) . " (documentados: " . implode(', ', $documentados) . ')');
}
// Cada operación documentada tiene su acción en las rutas, y cada acción del controlador está documentada.
$acciones = array_map(fn (string $op) => Rutas::resolver(...explode(' ', preg_replace('/\{[^}]+\}/', '1', $op)))[0], array_keys($operaciones));
preg_match_all("/case '(\w+)'/", file_get_contents(__DIR__ . '/../../micro-his-citas/src/Presentacion/ControladorCitas.php'), $delControlador);
$faltan = array_diff($delControlador[1], $acciones);
escenario('operaciones documentadas = acciones del controlador', !in_array('', $acciones, true) && $faltan === [],
          implode(', ', $acciones) . ($faltan ? '; sin documentar: ' . implode(', ', $faltan) : ''));
escenario('ruta no documentada', Rutas::resolver('GET', '/citas')[0] === '' && $app->atender('', [])['status'] === 404, 'GET /citas -> 404');

echo $fallos === 0 ? "RESULTADO: el Micro-HIS cumple el contrato\n" : "RESULTADO: $fallos escenario(s) fuera del contrato\n";
exit($fallos === 0 ? 0 : 1);
