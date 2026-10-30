<?php
declare(strict_types=1);

// Semana 15: pruebas unitarias (U), de caja blanca (CB) y de caja negra por HTTP (CN) del Micro-HIS de Contratos API, trazadas a RF/RNF.
// No modifica el Micro-HIS. Uso: php pruebas-s15.php   (las CN levantan php -S en un puerto libre, con un SQLite temporal nuevo)
require __DIR__ . '/../../micro-his-contratos-api/bootstrap.php';

use MicroHis\ContratosApi\Aplicacion\{ErrorPersistencia, RepositorioDocumentos, ServicioConsulta, ServicioPublicacion, ServicioRevision};
use MicroHis\ContratosApi\Dominio\{Audiencia, EstadoVersion, ReglaDeDominio, VersionNueva};
use MicroHis\ContratosApi\Persistencia\{RepositorioEnMemoria, RepositorioPdo};
use MicroHis\ContratosApi\Presentacion\VistaJson;

const MICRO_HIS = __DIR__ . '/../../micro-his-contratos-api';
$total = $fallos = 0;
function prueba(string $id, string $nombre, bool $ok, string $obtenido): void
{
    global $total, $fallos; $total++; $fallos += $ok ? 0 : 1;
    echo ($ok ? 'OK    ' : 'FALLA ') . "$id $nombre" . ($ok ? '' : "\n      obtenido: $obtenido") . "\n";
}
/** Código del rechazo, u «ok» si el caso de uso terminó sin rechazo. */
function codigo(callable $f): string { try { $f(); return 'ok'; } catch (ReglaDeDominio $e) { return $e->codigo; } catch (ErrorPersistencia) { return 'E8'; } }

// Doble espía del puerto de revisión: versiones fijas (autor ana) y cuenta de escrituras; puede simular que otra persona ganó (E7).
$doble = new class implements RepositorioDocumentos {
    public bool $ganaOtro = false; public int $escrituras = 0;
    public function versiones(string $s): array { return []; }
    public function guardarEnRevision(VersionNueva $v, string $a): int { $this->escrituras++; return 1; }
    public function datosRevision(int $id): ?array { return [1 => ['autor' => 'ana', 'estado' => 'in_review'], 2 => ['autor' => 'ana', 'estado' => 'published'], 3 => ['autor' => 'ana', 'estado' => 'rejected']][$id] ?? null; }
    public function cambiarEstado(int $id, string $de, string $a, string $r, ?string $m): bool { $this->escrituras++; return !$this->ganaOtro; }
    public function consultables(string $s): array { return []; }
};

echo "== Unitarias\n";
$estados = ['in_review', 'published', 'rejected', 'obsolete'];
$esperado = ['in_review' => ['E7', 'ok', 'ok', 'E7'], 'published' => ['E7', 'E7', 'E7', 'ok'], 'rejected' => ['E7', 'E7', 'E7', 'E7'], 'obsolete' => ['E7', 'E7', 'E7', 'E7']];
$distintos = [];
foreach ($esperado as $de => $fila) {
    foreach ($estados as $i => $a) {
        $o = codigo(fn () => EstadoVersion::from($de)->validarCambioA(EstadoVersion::from($a)));
        if ($o !== $fila[$i]) $distintos[] = "$de -> $a = $o";
    }
}
prueba('U-01', 'matriz de estados 4 x 4: 3 permitidos y 13 E7 (R-03, R-08)', $distintos === [], implode(', ', $distintos));

$v = fn (string $tipo, string $ver, array $aud, array $existentes = ['1.0.9'], string $slug = 'api-citas') => codigo(fn () => VersionNueva::validar($slug, $tipo, $ver, $aud, $existentes));
$o = [$v('swagger', '1.1.0', ['medico']), $v('openapi', '1.1', ['medico']), $v('openapi', '1.1.0-beta', ['medico']), $v('openapi', '1.1.0', [' ', '']), $v('openapi', '1.1.0', ['medico'], [], ''),
      $v('openapi', '1.0.9', ['medico']), $v('openapi', '1.0.8', ['medico']), $v('postman', '1.0.10', ['medico'])];
prueba('U-02', 'metadatos: tipo, x.y.z, audiencia y slug = E2; igual o menor = E3; 1.0.10 > 1.0.9 por versión, no por texto (R-01, R-02)',
       $o === ['E2', 'E2', 'E2', 'E2', 'E2', 'E3', 'E3', 'ok'], implode(', ', $o));

$o = [Audiencia::permite(['medico'], []), Audiencia::permite(['medico'], ['enfermeria']), Audiencia::permite(['medico', 'admin'], ['enfermeria', 'admin'])];
prueba('U-03', 'audiencia: sin roles y sin rol común no ven; un rol común basta (R-06)', $o === [false, false, true], json_encode($o));

$r = new RepositorioEnMemoria(); $pub = new ServicioPublicacion($r); $rev = new ServicioRevision($r); $con = new ServicioConsulta($r);
foreach (['1.9.0', '1.10.0'] as $ver) $rev->aprobar($pub->enviar('api-citas', 'openapi', $ver, ['medico'], 'ana'), 'luis');
$rev->marcarObsoleta(1, 'luis');
$o = [$con->consultar('api-citas', null, ['medico'])['version'], $con->consultar('api-citas', '1.9.0', ['medico'])['obsoleta'],
      codigo(fn () => $con->consultar('api-citas', null, ['enfermeria'])), codigo(fn () => $con->consultar('no-existe', null, ['medico']))];
prueba('U-04', 'consulta: vigente = mayor publicada (1.10.0); la obsoleta avisa; ajeno e inexistente dan el mismo E6 (R-06 a R-08)',
       $o === ['1.10.0', true, 'E6', 'E6'], json_encode($o));

$db = new PDO('sqlite::memory:'); $db->exec(file_get_contents(MICRO_HIS . '/database/schema.sql')); $pdo = new RepositorioPdo($db);
$nueva = VersionNueva::validar('api-citas', 'openapi', '2.0.0', ['medico'], []);
$o = [codigo(fn () => (new ServicioPublicacion($pdo))->enviar('api-citas', 'openapi', '2.0.0', ['medico'], 'ana')), codigo(fn () => $pdo->guardarEnRevision($nueva, 'luis'))];
prueba('U-05', 'carrera de dos envíos de la 2.0.0: el segundo, que pasó la validación, debe dar E3 y no E8 (R-02, H-04 de la semana 13)', $o === ['ok', 'E3'], implode(', ', $o));

preg_match_all("/ReglaDeDominio\('(\w+)'/", file_get_contents(MICRO_HIS . '/src/Dominio/Documento.php') . file_get_contents(MICRO_HIS . '/src/Aplicacion/ServiciosDocumentacion.php'), $m);
$sinHttp = array_diff(array_unique($m[1]), array_keys((new ReflectionClassConstant(VistaJson::class, 'HTTP'))->getValue()));
prueba('U-06', 'cada código de rechazo (' . implode(', ', array_unique($m[1])) . ') tiene su HTTP fijo', $sinHttp === [], implode(', ', $sinHttp));

echo "== Caja blanca\n";
$rev = new ServicioRevision($doble);
$cambio = function (callable $f, bool $gana = false) use ($doble) { [$doble->ganaOtro, $antes] = [$gana, $doble->escrituras]; return codigo($f) . '/' . ($doble->escrituras - $antes); };
$o = [$cambio(fn () => $rev->aprobar(9, 'luis')), $cambio(fn () => $rev->aprobar(3, 'luis')), $cambio(fn () => $rev->aprobar(1, 'ana')),
      $cambio(fn () => $rev->aprobar(1, 'luis'), true), $cambio(fn () => $rev->aprobar(1, 'luis'))];
prueba('CB-01', 'cambiar(): 5 caminos (V(G) = 4 más la salida del dominio): NF, E7 del dominio, E5, E7 de la carrera y éxito; solo los dos últimos escriben',
       $o === ['NF/0', 'E7/0', 'E5/0', 'E7/1', 'ok/1'], implode(', ', $o));

$o = [$cambio(fn () => $rev->aprobar(1, 'ana')), $cambio(fn () => $rev->aprobar(1, 'luis')), $cambio(fn () => $rev->marcarObsoleta(2, 'ana')),
      $cambio(fn () => $rev->rechazar(1, 'luis', ' ')), $cambio(fn () => $rev->rechazar(1, 'luis', 'faltan errores'))];
prueba('CB-02', 'MC/DC de «publicada && autor = revisor» (V,V), (V,F), (F,V) y la condición del motivo en rechazar',
       $o === ['E5/0', 'ok/1', 'ok/1', 'E2/0', 'ok/1'], implode(', ', $o));

echo "== Caja negra (HTTP)\n";
function servidor(string $dsn): array
{
    $libre = stream_socket_server('tcp://127.0.0.1:0'); $puerto = (int) substr(strrchr(stream_socket_get_name($libre, false), ':'), 1); fclose($libre);
    $log = sys_get_temp_dir() . '/s15-contratos-servidor.log';
    $p = proc_open([PHP_BINARY, '-S', "127.0.0.1:$puerto", '-t', MICRO_HIS . '/public'], [['pipe', 'r'], ['file', $log, 'w'], ['file', $log, 'a']],
                   $tubos, null, ['CONTRATOS_DSN' => $dsn] + getenv());
    for ($i = 0; $i < 50 && !@fsockopen('127.0.0.1', $puerto); $i++) usleep(100000);
    return [$p, $puerto];
}
/** Respuesta como «status código»; el cuerpo crudo queda en $GLOBALS['cuerpo']. */
function http(int $puerto, string $ruta, ?array $datos, string $usuario = '', string $roles = '', string $metodo = 'POST', ?string $crudo = null): string
{
    $ctx = stream_context_create(['http' => ['method' => $metodo, 'content' => $crudo ?? ($datos === null ? '' : json_encode($datos)), 'ignore_errors' => true,
        'header' => "Content-Type: application/json\r\nX-Usuario: $usuario\r\nX-Roles: $roles"]]);
    $b = $GLOBALS['cuerpo'] = (string) file_get_contents("http://127.0.0.1:$puerto$ruta", false, $ctx);
    preg_match('#HTTP/\S+ (\d+)#', $http_response_header[0], $m);
    return trim($m[1] . ' ' . (json_decode($b, true)['error'] ?? ''));
}
$base = sys_get_temp_dir() . '/s15-contratos-' . getmypid() . '.sqlite';
[$proceso, $p] = servidor("sqlite:$base");
$enviar = fn (string $tipo, string $ver, array $aud, string $usuario = 'ana') => http($p, '/documentos/api-citas/versiones', ['tipo' => $tipo, 'version' => $ver, 'audiencia' => $aud], $usuario);

$o = [$enviar('openapi', '1.0.0', ['medico']), $enviar('swagger', '1.1.0', ['medico']), $enviar('openapi', '1.1', ['medico']), $enviar('openapi', '1.1.0', [])];
prueba('CN-01', 'equivalencia del envío: válido; tipo desconocido; versión x.y; audiencia vacía (R-01)', $o === ['201', '422 E2', '422 E2', '422 E2'], implode(', ', $o));

$o = [http($p, '/versiones/1/aprobacion', null, 'ana'), http($p, '/versiones/1/aprobacion', null, 'luis'), http($p, '/versiones/1/aprobacion', null, 'luis'),
      $enviar('openapi', '1.0.0', ['medico']), $enviar('openapi', '0.9.9', ['medico']), $enviar('openapi', '1.0.1', ['medico']),
      http($p, '/versiones/2/rechazo', ['motivo' => ''], 'luis'), http($p, '/versiones/2/rechazo', ['motivo' => 'sin ejemplos'], 'luis'), $enviar('openapi', '1.0.1', ['medico'])];
prueba('CN-02', 'revisión y límites de versión: el autor no aprueba; otro sí; dos veces = E7; igual y menor = E3; la rechazada se reenvía (R-02, R-05)',
       $o === ['403 E5', '200', '409 E7', '409 E3', '409 E3', '201', '422 E2', '200', '201'], implode(', ', $o));

$ver = fn (string $ruta, string $roles) => http($p, $ruta, null, 'eva', $roles, 'GET');
$o = [$ver('/documentos/api-citas', 'medico'), $ver('/documentos/api-citas', 'enfermeria'), $ajeno = $GLOBALS['cuerpo'], $ver('/documentos/no-existe', 'enfermeria')];
$o[2] = $ajeno === $GLOBALS['cuerpo'] ? 'mismo cuerpo' : 'cuerpos distintos';
prueba('CN-03', 'audiencia: el lector de la audiencia ve; el ajeno recibe el mismo 404 E6 que lo inexistente (R-06, R-07, RNF-04)',
       $o === ['200', '404 E6', 'mismo cuerpo', '404 E6'], implode(', ', $o));

$o = [$enviar('openapi', '3.0.0', ['medico'], ''), http($p, '/versiones/3/aprobacion', null, 'cualquiera', 'paciente')];
prueba('CN-04', 'permisos: enviar sin usuario y aprobar con un rol sin permiso de revisión = 403 (RNF-01, H-01 de la semana 13)', $o === ['403', '403'], implode(', ', $o));

$o = [http($p, '/versiones/99/aprobacion', null, 'luis'), http($p, '/documentos/API-Citas', null, 'eva', 'medico', 'GET'), http($p, '/documentos/api-citas/versiones', null, 'ana', '', 'POST', 'no es JSON'),
      http($p, '/documentos/api-citas/versiones/9.9.9', null, 'eva', 'medico', 'GET')];
prueba('CN-05', 'errores del cliente: versión inexistente, slug con mayúsculas, cuerpo que no es JSON y versión que no existe', $o === ['404 NF', '404', '422 E2', '404 E6'], implode(', ', $o));
proc_terminate($proceso);

[$proceso, $p] = servidor('sqlite:' . sys_get_temp_dir() . '/s15-no-existe/contratos.sqlite');
$o = http($p, '/documentos/api-citas/versiones', ['tipo' => 'openapi', 'version' => '1.0.0', 'audiencia' => ['medico']], 'ana');
$tecnico = str_contains($GLOBALS['cuerpo'], 'SQLSTATE');
prueba('CN-06', 'base no disponible al arrancar = 503 E8, sin detalles técnicos', $o === '503 E8' && !$tecnico,
       $o . ($tecnico ? '; el cuerpo muestra el «Fatal error» de PHP con SQLSTATE y la ruta de index.php' : ''));
proc_terminate($proceso);
@unlink($base);

echo "RESULTADO: " . ($total - $fallos) . " de $total pruebas pasan" . ($fallos ? "; $fallos fallan (ver defectos)" : '') . "\n";
exit($fallos === 0 ? 0 : 1);
