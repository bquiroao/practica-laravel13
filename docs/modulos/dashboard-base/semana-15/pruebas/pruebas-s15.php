<?php
declare(strict_types=1);

// Semana 15: pruebas unitarias (U), de caja blanca (CB) y de caja negra por HTTP (CN) del Micro-HIS de Dashboard base, trazadas a RF/RNF.
// No modifica el Micro-HIS. Uso: php pruebas-s15.php   (las CN levantan php -S en un puerto libre, con un SQLite temporal nuevo)
require __DIR__ . '/../../micro-his-dashboard-base/bootstrap.php';

use MicroHis\DashboardBase\Aplicacion\{ErrorPersistencia, FuenteConteos, Reloj, ServicioDashboard};
use MicroHis\DashboardBase\Dominio\{Catalogo, EstadoTarjeta, ReglaDeDominio};
use MicroHis\DashboardBase\Persistencia\RepositorioEnMemoria;
use MicroHis\DashboardBase\Presentacion\VistaJson;

const MICRO_HIS = __DIR__ . '/../../micro-his-dashboard-base';
const TODOS = ['pacientes.ver', 'citas.ver', 'camas.ver'];
$total = $fallos = 0;
function prueba(string $id, string $nombre, bool $ok, string $obtenido): void
{
    global $total, $fallos; $total++; $fallos += $ok ? 0 : 1;
    echo ($ok ? 'OK    ' : 'FALLA ') . "$id $nombre" . ($ok ? '' : "\n      obtenido: $obtenido") . "\n";
}
/** Código del rechazo, u «ok» si el caso de uso terminó sin rechazo. */
function codigo(callable $f): string { try { $f(); return 'ok'; } catch (ReglaDeDominio $e) { return $e->codigo; } }
$reloj = new class implements Reloj { public function hoy(): string { return '2026-10-26'; } };
// Doble espía del puerto de conteos: cuenta las lecturas; con $falla = true simula la base caída para esa tarjeta.
$doble = new class implements FuenteConteos {
    public array $valores = []; public array $falla = []; public int $lecturas = 0;
    public function conteo(string $t, string $dia): int { $this->lecturas++; if ($this->falla[$t] ?? false) throw new ErrorPersistencia('sin base'); return $this->valores[$t] ?? 0; }
};

echo "== Unitarias\n";
$t = Catalogo::tarjetas(TODOS);
$o = [EstadoTarjeta::deConteo(0)->value, EstadoTarjeta::deConteo(1)->value, $t['pacientes']->texto(EstadoTarjeta::Resumen, 1), $t['citas_hoy']->texto(EstadoTarjeta::Resumen, 2),
      $t['camas_disponibles']->texto(EstadoTarjeta::Vacio, 0), $t['pacientes']->texto(EstadoTarjeta::Cargando, null), $t['citas_hoy']->texto(EstadoTarjeta::Error, null)];
prueba('U-01', 'estados y textos de tarjeta: 0 = vacío con sugerencia, 1 = singular, 2 = plural, cargando y error sin detalles (R-04, R-05, R-09)',
       $o === ['vacio', 'resumen', '1 paciente activo', '2 citas hoy', 'No hay camas disponibles ahora.', 'Cargando pacientes…', 'No pudimos cargar las citas de hoy. Intenta de nuevo.'],
       json_encode($o, JSON_UNESCAPED_UNICODE));

$m = fn (array $p) => implode(' ', array_column(Catalogo::menu($p), 'ruta')) . '|' . implode(' ', array_keys(Catalogo::tarjetas($p)));
$o = [$m([]), $m(['citas.ver']), $m(['CITAS.VER', 'usuarios.ver']), $m(array_reverse(TODOS))];
prueba('U-02', 'menú y tarjetas por permiso: sin permisos nada; uno solo lo suyo; mayúsculas o ajenos nada; los 3 en orden fijo (R-02)',
       $o === ['|', '/citas|citas_hoy', '|', '/pacientes /citas /camas|pacientes citas_hoy camas_disponibles'], json_encode($o));

$s = new ServicioDashboard(new RepositorioEnMemoria(['active'], [], []), $reloj);
$ini = $s->inicio('hospital-1', ['pacientes.ver', 'camas.ver']);
$o = [codigo(fn () => $s->inicio('', TODOS)), codigo(fn () => $s->inicio('   ', TODOS)), count($ini['menu']), implode(' ', array_unique(array_column($ini['tarjetas'], 'estado')))];
prueba('U-03', 'resumen inicial: sin hospital o con espacios = E1; con hospital, el menú del rol y todas las tarjetas en «cargando» (R-01, R-04)',
       $o === ['E1', 'E1', 2, 'cargando'], json_encode($o));

$doble->valores = ['pacientes' => 3]; $doble->falla = ['citas_hoy' => true]; $s = new ServicioDashboard($doble, $reloj);
$o = array_map(fn (string $id) => $s->tarjeta($id, 'hospital-1', TODOS), ['pacientes', 'citas_hoy', 'camas_disponibles']);
$claves = array_unique(array_merge(...array_map('array_keys', $o)));
prueba('U-04', 'cada tarjeta por su cuenta: la que falla queda en error y las otras no; la vista solo lleva id, estado y texto (R-03, R-06)',
       array_column($o, 'estado') === ['resumen', 'error', 'vacio'] && $claves === ['id', 'estado', 'texto'], json_encode([array_column($o, 'estado'), $claves]));

if (method_exists(Catalogo::class, 'destino')) {
    $o = [codigo(fn () => Catalogo::destino('/citas', ['citas.ver'])), codigo(fn () => Catalogo::destino('/citas', ['camas.ver'])), codigo(fn () => Catalogo::destino('/usuarios', TODOS))];
    prueba('U-05', 'navegación (CU-03): ruta permitida; ruta del menú sin permiso = E6; ruta que no existe = NF (R-02, H-02 de la semana 13)', $o === ['ok', 'E6', 'NF'], implode(', ', $o));
} else {
    echo "PEND. U-05 navegación (CU-03): Catalogo::destino llega con la semana 6 (PR #446, sin integrar)\n";
}

preg_match_all("/ReglaDeDominio\('(\w+)'/", file_get_contents(MICRO_HIS . '/src/Dominio/Dashboard.php') . file_get_contents(MICRO_HIS . '/src/Aplicacion/ServicioDashboard.php'), $c);
$sinHttp = array_diff(array_unique($c[1]), array_keys((new ReflectionClassConstant(VistaJson::class, 'HTTP'))->getValue()));
prueba('U-06', 'cada código de rechazo (' . implode(', ', array_unique($c[1])) . ') tiene su HTTP fijo', $sinHttp === [], implode(', ', $sinHttp));

echo "== Caja blanca\n";
$camino = function (string $id, string $hospital, array $permisos) use ($s, $doble) { $antes = $doble->lecturas; $r = '';
    $c = codigo(function () use ($s, $id, $hospital, $permisos, &$r) { $r = $s->tarjeta($id, $hospital, $permisos)['estado']; });
    return ($c === 'ok' ? $r : $c) . '/' . ($doble->lecturas - $antes); };
$doble->valores = ['pacientes' => 0, 'camas_disponibles' => 4]; $doble->falla = ['citas_hoy' => true];
$o = [$camino('pacientes', '', TODOS), $camino('pacientes', 'h1', ['citas.ver']), $camino('citas_hoy', 'h1', TODOS), $camino('pacientes', 'h1', TODOS), $camino('camas_disponibles', 'h1', TODOS)];
prueba('CB-01', 'tarjeta(): 5 caminos (V(G) = 5): E1, E4, error, vacío y resumen; solo los tres últimos leen la base',
       $o === ['E1/0', 'E4/0', 'error/1', 'vacio/1', 'resumen/1'], implode(', ', $o));

$p = $t['pacientes'];
$o = [$p->texto(EstadoTarjeta::Cargando, null), $p->texto(EstadoTarjeta::Resumen, 1), $p->texto(EstadoTarjeta::Resumen, 0), $p->texto(EstadoTarjeta::Vacio, 0), $p->texto(EstadoTarjeta::Error, null),
      codigo(fn () => $s->inicio(' h1 ', [])), $m(['citas.ver ', 'camas.ver'])];
prueba('CB-02', 'decisiones de texto(): las 4 ramas del match y las 2 del singular; «trim(hospital) === \'\'» con « h1 »; in_array estricto con «citas.ver »',
       $o === ['Cargando pacientes…', '1 paciente activo', '0 pacientes activos', 'Aún no hay pacientes activos: registra el primero.', 'No pudimos cargar pacientes. Intenta de nuevo.', 'ok', '/camas|camas_disponibles'],
       json_encode($o, JSON_UNESCAPED_UNICODE));

echo "== Caja negra (HTTP)\n";
function servidor(string $dsn): array
{
    $libre = stream_socket_server('tcp://127.0.0.1:0'); $puerto = (int) substr(strrchr(stream_socket_get_name($libre, false), ':'), 1); fclose($libre);
    $log = sys_get_temp_dir() . '/s15-dashboard-servidor.log';
    $p = proc_open([PHP_BINARY, '-S', "127.0.0.1:$puerto", '-t', MICRO_HIS . '/public'], [['pipe', 'r'], ['file', $log, 'w'], ['file', $log, 'a']],
                   $tubos, null, ['DASHBOARD_DSN' => $dsn] + getenv());
    for ($i = 0; $i < 50 && !@fsockopen('127.0.0.1', $puerto); $i++) usleep(100000);
    return [$p, $puerto];
}
/** Respuesta como «status código»; el cuerpo decodificado queda en $GLOBALS['json'] y el crudo en $GLOBALS['cuerpo']. */
function http(int $puerto, string $ruta, string $hospital = 'hospital-1', string $permisos = 'pacientes.ver,citas.ver,camas.ver', string $metodo = 'GET'): string
{
    $ctx = stream_context_create(['http' => ['method' => $metodo, 'ignore_errors' => true, 'header' => "X-Tenant-ID: $hospital\r\nX-Permisos: $permisos"]]);
    $b = $GLOBALS['cuerpo'] = (string) file_get_contents("http://127.0.0.1:$puerto$ruta", false, $ctx);
    $GLOBALS['json'] = json_decode($b, true) ?? [];
    preg_match('#HTTP/\S+ (\d+)#', $http_response_header[0], $m);
    return trim($m[1] . ' ' . ($GLOBALS['json']['error'] ?? ''));
}
$base = sys_get_temp_dir() . '/s15-dashboard-' . getmypid() . '.sqlite'; $hoy = date('Y-m-d'); $manana = date('Y-m-d', strtotime('+1 day'));
$db = new PDO("sqlite:$base"); $db->exec(file_get_contents(MICRO_HIS . '/database/schema.sql'));
$db->exec("INSERT INTO local_patients (status) VALUES ('active'), ('inactive'); INSERT INTO beds (status) VALUES ('occupied');
           INSERT INTO appointments (starts_at, status) VALUES ('$hoy 12:00', 'scheduled'), ('$hoy 13:00', 'confirmed'), ('$hoy 14:00', 'cancelled'), ('$manana 12:00', 'scheduled')");
[$proceso, $p] = servidor("sqlite:$base");
$tj = fn (string $id, ...$a) => http($p, "/dashboard/tarjetas/$id", ...$a) . ' ' . ($GLOBALS['json']['estado'] ?? '') . ' «' . ($GLOBALS['json']['texto'] ?? '') . '»';

$o = [$tj('pacientes'), $tj('citas_hoy'), $tj('camas_disponibles')];
prueba('CN-01', 'equivalencia del conteo: 1 (singular), 2 (plural; no cuenta la cancelada ni la de mañana) y 0 (vacío con sugerencia) (RF-01, RF-02)',
       $o === ['200 resumen «1 paciente activo»', '200 resumen «2 citas hoy»', '200 vacio «No hay camas disponibles ahora.»'], implode(', ', $o));

$o = [http($p, '/dashboard', ''), http($p, '/dashboard', ' '), http($p, '/dashboard', 'h', ''), count($GLOBALS['json']['tarjetas'] ?? [-1]), http($p, '/dashboard', 'h', 'camas.ver,'),
      count($GLOBALS['json']['tarjetas'] ?? []), http($p, '/dashboard/tarjetas/CITAS_HOY')];
prueba('CN-02', 'límites de sesión y permisos: sin hospital y con un espacio = 401 E1; sin permisos = 200 y 0 tarjetas; «camas.ver,» = 1 tarjeta; id en mayúsculas = 404 (R-01, RF-04)',
       $o === ['401 E1', '401 E1', '200', 0, '200', 1, '404'], json_encode($o));

$o = [http($p, '/dashboard/tarjetas/citas_hoy', 'h', 'camas.ver'), http($p, '/dashboard', 'h', 'camas.ver'), implode(' ', array_column($GLOBALS['json']['menu'] ?? [], 'ruta'))];
prueba('CN-03', 'permisos del rol: tarjeta sin permiso = 403 E4 (el cliente la oculta); el menú solo muestra lo permitido (R-02, RF-05)', $o === ['403 E4', '200', '/camas'], implode(', ', $o));

$o = [http($p, '/dashboard', 'hospital-ajeno'), http($p, '/dashboard/tarjetas/pacientes', 'hospital-ajeno')];
prueba('CN-04', 'tenant: un hospital al que el usuario no pertenece = 403 en el resumen y en la tarjeta (CP-04 de la semana 14, H-01 de la semana 13)', $o === ['403', '403'], implode(', ', $o));

$o = [http($p, '/dashboard/tarjetas/usuarios'), http($p, '/dashboard/otra'), http($p, '/dashboard', 'h1', 'camas.ver', 'POST')];
prueba('CN-05', 'errores del cliente: tarjeta que no existe = 404 y no 403 (H-03 de la semana 13); ruta desconocida y método distinto = 404', $o === ['404', '404', '404'], implode(', ', $o));

$db->exec('DROP TABLE beds; CREATE VIEW beds AS SELECT abs(-9223372036854775807 - 1) AS status'); $db = null;   // la consulta de camas falla al evaluarse
$o = [$tj('camas_disponibles'), $tj('pacientes')]; $tecnico = str_contains($GLOBALS['cuerpo'], 'SQLSTATE');
prueba('CN-06', 'falla de una tarjeta: camas = 503 E5 con texto amable y pacientes sigue en 200; sin detalles técnicos (R-03, R-09, RF-06)',
       $o === ['503 E5 error «No pudimos cargar las camas disponibles. Intenta de nuevo.»', '200 resumen «1 paciente activo»'] && !$tecnico, implode(', ', $o));
proc_terminate($proceso);

[$proceso, $p] = servidor('sqlite:' . sys_get_temp_dir() . '/s15-no-existe/dashboard.sqlite');
$o = http($p, '/dashboard/tarjetas/pacientes'); $tecnico = str_contains($GLOBALS['cuerpo'], 'SQLSTATE');
prueba('CN-07', 'base no disponible al arrancar = 503 E5 con texto amable, sin detalles técnicos (R-09, RNF-01)', $o === '503 E5' && !$tecnico,
       $o . ($tecnico ? '; el cuerpo muestra el «Fatal error» de PHP con SQLSTATE y la ruta de index.php' : ''));
proc_terminate($proceso);
@unlink($base);

echo "RESULTADO: " . ($total - $fallos) . " de $total pruebas pasan" . ($fallos ? "; $fallos fallan (ver defectos)" : '') . "\n";
exit($fallos === 0 ? 0 : 1);
