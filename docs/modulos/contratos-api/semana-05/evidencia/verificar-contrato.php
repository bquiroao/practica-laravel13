<?php
declare(strict_types=1);

// Prueba de contrato: cada escenario pasa por las rutas y el controlador reales del Micro-HIS, y su respuesta debe estar
// documentada en api/openapi.yaml (operación, código HTTP y código de error). Uso: php verificar-contrato.php <openapi.json>
// El JSON sale de: npx @redocly/cli@1.34.3 bundle api/openapi.yaml --ext json -o <openapi.json>. Datos ficticios.
require __DIR__ . '/../../micro-his-contratos-api/bootstrap.php';

use MicroHis\ContratosApi\Persistencia\{RepositorioEnMemoria, RepositorioPdo};
use MicroHis\ContratosApi\Presentacion\Rutas;
use function MicroHis\ContratosApi\crearControlador;

$api = json_decode((string) file_get_contents($argv[1] ?? ''), true) ?? exit("Falta el OpenAPI en JSON (ver el encabezado).\n");
$errores = $api['components']['schemas']['Error']['properties']['error']['enum'];
$operaciones = [];   // "MÉTODO /ruta/{param}" => códigos documentados
foreach ($api['paths'] as $ruta => $metodos) foreach ($metodos as $m => $op) $operaciones[strtoupper($m) . " $ruta"] = array_map('intval', array_keys($op['responses']));   // PHP vuelve enteras las claves «201»

$fallos = 0;
function escenario(string $nombre, bool $ok, string $detalle): void { global $fallos; $fallos += $ok ? 0 : 1; printf("%s %s: %s\n", $ok ? 'OK   ' : 'FALLA', $nombre, $detalle); }

$app = crearControlador(new RepositorioEnMemoria());
$rota = new PDO('sqlite::memory:');   // sin tablas: cualquier consulta falla y debe responder 503
$version = fn (string $v, array $aud = ['medico', 'enfermeria']) => ['tipo' => 'manual', 'version' => $v, 'audiencia' => $aud, 'usuario' => 'ana'];
$lector = fn (string ...$roles) => ['roles' => $roles];
// [nombre, método, ruta real, plantilla documentada, datos (cuerpo y sesión), controlador]
$casos = [['enviar versión', 'POST', '/documentos/guia-citas/versiones', '/documentos/{slug}/versiones', $version('1.0.0'), $app],
    ['versión sin formato x.y.z (R-01)', 'POST', '/documentos/guia-citas/versiones', '/documentos/{slug}/versiones', $version('1.0'), $app],
    ['versión repetida (R-02)', 'POST', '/documentos/guia-citas/versiones', '/documentos/{slug}/versiones', $version('1.0.0'), $app],
    ['aprobar lo propio (R-05)', 'POST', '/versiones/1/aprobacion', '/versiones/{id}/aprobacion', ['usuario' => 'ana'], $app],
    ['aprobar', 'POST', '/versiones/1/aprobacion', '/versiones/{id}/aprobacion', ['usuario' => 'luis'], $app],
    ['aprobar otra vez (R-03)', 'POST', '/versiones/1/aprobacion', '/versiones/{id}/aprobacion', ['usuario' => 'luis'], $app],
    ['consultar vigente', 'GET', '/documentos/guia-citas', '/documentos/{slug}', $lector('medico'), $app],
    ['rol fuera de la audiencia (R-07)', 'GET', '/documentos/guia-citas', '/documentos/{slug}', $lector('farmacia'), $app],
    ['enviar la 1.1.0', 'POST', '/documentos/guia-citas/versiones', '/documentos/{slug}/versiones', $version('1.1.0'), $app],
    ['rechazar sin motivo', 'POST', '/versiones/2/rechazo', '/versiones/{id}/rechazo', ['usuario' => 'luis', 'motivo' => ' '], $app],
    ['rechazar con motivo', 'POST', '/versiones/2/rechazo', '/versiones/{id}/rechazo', ['usuario' => 'luis', 'motivo' => 'falta un ejemplo'], $app],
    ['marcar obsoleta (R-08)', 'POST', '/versiones/1/obsolescencia', '/versiones/{id}/obsolescencia', ['usuario' => 'luis'], $app],
    ['marcar obsoleta otra vez', 'POST', '/versiones/1/obsolescencia', '/versiones/{id}/obsolescencia', ['usuario' => 'luis'], $app],
    ['consultar la obsoleta, con aviso', 'GET', '/documentos/guia-citas/versiones/1.0.0', '/documentos/{slug}/versiones/{version}', $lector('medico'), $app],
    ['versión inexistente', 'POST', '/versiones/99/aprobacion', '/versiones/{id}/aprobacion', ['usuario' => 'luis'], $app],
    ['base caída', 'POST', '/documentos/guia-citas/versiones', '/documentos/{slug}/versiones', $version('2.0.0'), crearControlador(new RepositorioPdo($rota))]];
foreach ($casos as [$nombre, $metodo, $ruta, $plantilla, $datos, $controlador]) {
    [$accion, $parametros] = Rutas::resolver($metodo, $ruta);
    $r = $controlador->atender($accion, $parametros + $datos);
    $documentados = $operaciones["$metodo $plantilla"] ?? [];
    escenario($nombre, in_array($r['status'], $documentados, true) && (!isset($r['error']) || in_array($r['error'], $errores, true)),
              "$metodo $ruta -> " . rtrim("{$r['status']} " . ($r['error'] ?? '')) . " (documentados: " . implode(', ', $documentados) . ')');
}
// Cada operación documentada tiene su acción en las rutas, y cada acción del controlador está documentada.
$muestra = fn (array $p) => $p[1] === 'version' ? '1.0.0' : '1';
$acciones = array_map(fn (string $op) => Rutas::resolver(...explode(' ', preg_replace_callback('/\{(\w+)\}/', $muestra, $op)))[0], array_keys($operaciones));
preg_match_all("/case '(\w+)'/", file_get_contents(__DIR__ . '/../../micro-his-contratos-api/src/Presentacion/ControladorDocumentacion.php'), $delControlador);
$faltan = array_diff($delControlador[1], $acciones);
escenario('operaciones documentadas = acciones del controlador', !in_array('', $acciones, true) && $faltan === [],
          implode(', ', $acciones) . ($faltan ? '; sin documentar: ' . implode(', ', $faltan) : ''));
escenario('ruta no documentada', Rutas::resolver('DELETE', '/documentos/guia-citas')[0] === '' && $app->atender('', [])['status'] === 404, 'DELETE /documentos/guia-citas -> 404');

echo $fallos === 0 ? "RESULTADO: el Micro-HIS cumple el contrato\n" : "RESULTADO: $fallos escenario(s) fuera del contrato\n";
exit($fallos === 0 ? 0 : 1);
