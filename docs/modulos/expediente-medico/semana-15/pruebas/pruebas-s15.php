<?php
declare(strict_types=1);

// Semana 15: pruebas unitarias (U), de caja blanca (CB) y de caja negra por HTTP (CN) del Micro-HIS de Expediente médico, trazadas a RF/RNF.
// No modifica el Micro-HIS. Uso: php pruebas-s15.php   (las CN levantan php -S en un puerto libre, con dos SQLite temporales nuevos: HOSPITAL y CENTRAL)
require __DIR__ . '/../../micro-his-expediente/bootstrap.php';

use MicroHis\Expediente\Aplicacion\{Auditoria, ErrorPersistencia, IndiceCentral, Reloj, ServicioExpediente};
use MicroHis\Expediente\Dominio\{Consentimiento, Paciente, ReglaDeDominio};
use MicroHis\Expediente\Persistencia\HospitalEnMemoria;
use MicroHis\Expediente\Presentacion\VistaJson;

const MICRO_HIS = __DIR__ . '/../../micro-his-expediente';
$total = $fallos = 0;
function prueba(string $id, string $nombre, bool $ok, string $obtenido): void
{
    global $total, $fallos; $total++; $fallos += $ok ? 0 : 1;
    echo ($ok ? 'OK    ' : 'FALLA ') . "$id $nombre" . ($ok ? '' : "\n      obtenido: $obtenido") . "\n";
}
/** Código del rechazo, u «ok» si el caso de uso terminó sin rechazo. */
function codigo(callable $f): string { try { $f(); return 'ok'; } catch (ReglaDeDominio $e) { return $e->codigo; } catch (ErrorPersistencia) { return 'E8'; } }
$reloj = new class implements Reloj { public function ahora(): int { return 1000; } };
// Doble espía de CENTRAL: cuenta las llamadas; con $cae = true simula la base CENTRAL caída.
$central = new class implements IndiceCentral {
    public int $llamadas = 0; public bool $cae = false; public array $consentimientos = ['g1|h1' => ['desde' => 0, 'hasta' => null]];
    public function consentimiento(string $g, string $h): ?array { $this->llamadas++; if ($this->cae) throw new ErrorPersistencia('sin central'); return $this->consentimientos["$g|$h"] ?? null; }
    public function expedientesExternos(string $g, string $h): array { $this->llamadas++; return [['hospital' => 'h2', 'expediente' => 'H2-0007']]; }
};
$hospital = fn () => new HospitalEnMemoria(['p1' => ['g1', 'active', 'linked'], 'p2' => ['g2', 'inactive', 'linked'], 'p6' => ['g6', 'active', 'unlinked'], 'p7' => ['g7', 'active', 'linked']],
                                           [['p1', 'm1'], ['p6', 'm1'], ['p7', 'm1'], ['p9', 'm1']]);

echo "== Unitarias\n";
$o = [codigo(fn () => (new Paciente('p1', 'g1', 'inactive', true, null))->numeroParaAbrir()), codigo(fn () => (new Paciente('p1', 'g1', 'deceased', true, null))->numeroParaAbrir()),
      codigo(fn () => (new Paciente('p1', 'g1', 'active', true, 'EXP-p1'))->numeroParaAbrir()), (new Paciente('p1', 'g1', 'active', true, null))->numeroParaAbrir()];
prueba('U-01', 'apertura: inactivo y fallecido = E2; con expediente = E3; activo sin expediente = EXP-p1 (R-01 a R-03)', $o === ['E2', 'E2', 'E3', 'EXP-p1'], json_encode($o));

$v = fn (?array $c, int $t) => Consentimiento::vigente($c, $t);
$o = [$v(null, 100), $v(['desde' => 100, 'hasta' => 200], 99), $v(['desde' => 100, 'hasta' => 200], 100), $v(['desde' => 100, 'hasta' => 200], 199),
      $v(['desde' => 100, 'hasta' => 200], 200), $v(['desde' => 100, 'hasta' => null], PHP_INT_MAX)];
prueba('U-02', 'vigencia [desde, hasta): sin consentimiento no; 99 no, 100 sí, 199 sí, 200 no; sin fin vale siempre (R-06, R-10)', $o === [false, false, true, true, false, true], json_encode($o));

$h = $hospital(); $s = new ServicioExpediente($h, $central, $h, $reloj, 'h1');
$o = [codigo(fn () => $s->consultar('p1', 'm2', false)), codigo(fn () => $s->consultar('p1', 'm1', false)), $s->abrir('p1', 'm1'), json_encode($s->consultar('p1', 'm1', false))];
prueba('U-03', 'acceso autorizado: sin asignación activa = E4; con asignación y sin expediente = NF; abierto, la consulta local solo trae el número (R-05)',
       $o === ['E4', 'NF', 'EXP-p1', '{"expediente":"EXP-p1"}'], json_encode($o));

$s->abrir('p6', 'm1'); $s->abrir('p7', 'm1');
$central->consentimientos = ['g1|h1' => ['desde' => 0, 'hasta' => null], 'g7|h1' => ['desde' => 0, 'hasta' => 1000]];
$res = function (string $p) use ($s) { $r = null; $k = codigo(function () use ($s, $p, &$r) { $r = $s->consultar($p, 'm1', true); }); return $k === 'ok' ? json_encode($r) : $k; };
$o = [$res('p1'), $res('p6'), $res('p7')];
prueba('U-04', 'longitudinal: vinculado con consentimiento = otros hospitales (solo hospital y número); sin vínculo MPI = solo local; consentimiento vencido = E5 (R-06, R-09, R-10)',
       $o === ['{"expediente":"EXP-p1","externos":[{"hospital":"h2","expediente":"H2-0007"}]}', '{"expediente":"EXP-p6","solo_local":true}', 'E5'], json_encode($o));

$h = $hospital(); $s = new ServicioExpediente($h, $central, $h, $reloj, 'h1'); $central->cae = true;
$o = [codigo(fn () => $s->abrir('p1', 'm1')), codigo(fn () => $s->abrir('p1', 'm1')), codigo(fn () => $s->consultar('p1', 'm2', false)), codigo(fn () => $s->consultar('p1', 'm1', true))];
$central->cae = false;
prueba('U-05', 'integridad de la auditoría: cada operación deja un evento con su resultado, también los rechazos y el fallo de CENTRAL (R-08, RNF-04, semana 6)',
       $o === ['ok', 'E3', 'E4', 'E8'] && array_column($h->eventos, 3) === ['ok', 'E3', 'E4', 'E8'] && array_column($h->eventos, 1) === ['abrir', 'abrir', 'consultar', 'consultar_longitudinal'],
       json_encode($h->eventos));

preg_match_all("/ReglaDeDominio\('(\w+)'/", file_get_contents(MICRO_HIS . '/src/Dominio/Expediente.php') . file_get_contents(MICRO_HIS . '/src/Aplicacion/ServicioExpediente.php'), $c);
$sinHttp = array_diff(array_unique($c[1]), array_keys((new ReflectionClassConstant(VistaJson::class, 'HTTP'))->getValue()));
prueba('U-06', 'cada código de rechazo (' . implode(', ', array_unique($c[1])) . ') tiene su HTTP fijo', $sinHttp === [], implode(', ', $sinHttp));

echo "== Caja blanca\n";
$h = new HospitalEnMemoria(['p1' => ['g1', 'active', 'linked'], 'p4' => ['g4', 'active', 'linked'], 'p6' => ['g6', 'active', 'unlinked'], 'p7' => ['g7', 'active', 'linked']],
                           [['p1', 'm1'], ['p4', 'm1'], ['p6', 'm1'], ['p7', 'm1'], ['p9', 'm1']]);
$s = new ServicioExpediente($h, $central, $h, $reloj, 'h1'); foreach (['p1', 'p6', 'p7'] as $p) $s->abrir($p, 'm1');
$camino = function (string $p, string $m, bool $l) use ($s, $central, $h) { [$antes, $ev] = [$central->llamadas, count($h->eventos)]; $r = [];
    $k = codigo(function () use ($s, $p, $m, $l, &$r) { $r = $s->consultar($p, $m, $l); });
    return ($k === 'ok' ? implode('+', array_keys($r)) : $k) . '/' . ($central->llamadas - $antes) . '/' . (count($h->eventos) - $ev); };
$o = [$camino('p1', 'm2', true), $camino('p9', 'm1', true), $camino('p4', 'm1', true), $camino('p1', 'm1', false), $camino('p6', 'm1', true), $camino('p7', 'm1', true), $camino('p1', 'm1', true)];
prueba('CB-01', 'consultar(): 7 caminos (V(G) = 7): E4, E2, NF, local, solo local, E5 y externos; solo los dos últimos tocan CENTRAL y todos auditan 1 evento',
       $o === ['E4/0/1', 'E2/0/1', 'NF/0/1', 'expediente/0/1', 'expediente+solo_local/0/1', 'E5/1/1', 'expediente+externos/2/1'], implode(', ', $o));

$o = [$v(null, 5), $v(['desde' => 10, 'hasta' => null], 5), $v(['desde' => 0, 'hasta' => null], 5), $v(['desde' => 0, 'hasta' => 10], 5), $v(['desde' => 0, 'hasta' => 5], 5)];
$sinAuditoria = new class implements Auditoria { public function registrar(string $m, string $op, string $p, string $res): void { throw new ErrorPersistencia('sin auditoría'); } };
$x = new HospitalEnMemoria(['p1' => ['g1', 'active', 'linked']], [['p1', 'm1']]); $central->cae = true;
$o[] = codigo(fn () => (new ServicioExpediente($x, $central, $sinAuditoria, $reloj, 'h1'))->consultar('p1', 'm1', false));
$x->abrirExpediente('p1', 'EXP-p1'); $o[] = codigo(fn () => (new ServicioExpediente($x, $central, $x, $reloj, 'h1'))->consultar('p1', 'm1', true)); $o[] = count($x->eventos);
$central->cae = false;
prueba('CB-02', 'MC/DC de vigente() (null, antes, sin fin, antes del fin, en el fin) y las 3 salidas de auditado(): rechazo, caída de la auditoría y caída de CENTRAL con su evento',
       $o === [false, false, true, true, false, 'E8', 'E8', 1], json_encode($o));

echo "== Caja negra (HTTP)\n";
function servidor(string $hospital, string $central): array
{
    $libre = stream_socket_server('tcp://127.0.0.1:0'); $puerto = (int) substr(strrchr(stream_socket_get_name($libre, false), ':'), 1); fclose($libre);
    $log = sys_get_temp_dir() . '/s15-expediente-servidor.log';
    $p = proc_open([PHP_BINARY, '-S', "127.0.0.1:$puerto", '-t', MICRO_HIS . '/public'], [['pipe', 'r'], ['file', $log, 'w'], ['file', $log, 'a']],
                   $tubos, null, ['EXPEDIENTE_DSN' => $hospital, 'CENTRAL_DSN' => $central] + getenv());
    for ($i = 0; $i < 50 && !@fsockopen('127.0.0.1', $puerto); $i++) usleep(100000);
    return [$p, $puerto];
}
/** Respuesta como «status código»; el cuerpo decodificado queda en $GLOBALS['json'] y el crudo en $GLOBALS['cuerpo']. */
function http(int $puerto, string $metodo, string $ruta, string $profesional = 'm1', ?string $tenant = 'h1'): string
{
    $ctx = stream_context_create(['http' => ['method' => $metodo, 'ignore_errors' => true, 'header' => "X-Profesional: $profesional" . ($tenant === null ? '' : "\r\nX-Tenant-ID: $tenant")]]);
    $b = $GLOBALS['cuerpo'] = (string) file_get_contents("http://127.0.0.1:$puerto$ruta", false, $ctx);
    $GLOBALS['json'] = json_decode($b, true) ?? [];
    preg_match('#HTTP/\S+ (\d+)#', $http_response_header[0], $m);
    return trim($m[1] . ' ' . ($GLOBALS['json']['error'] ?? ''));
}
$bh = sys_get_temp_dir() . '/s15-expediente-h-' . getmypid() . '.sqlite'; $bc = sys_get_temp_dir() . '/s15-expediente-c-' . getmypid() . '.sqlite'; $t = time();
$dh = new PDO("sqlite:$bh"); $dh->exec(file_get_contents(MICRO_HIS . '/database/schema.sql')); $dc = new PDO("sqlite:$bc"); $dc->exec(file_get_contents(MICRO_HIS . '/database/schema-central.sql'));
$dh->exec("INSERT INTO local_patients VALUES ('p1','g1','active','linked'), ('p2','g2','inactive','linked'), ('p3','g3','deceased','unlinked'), ('p4','g4','active','linked'),
           ('p5','g5','active','linked'), ('p6','g6','active','unlinked'), ('p7','g7','active','linked'), ('p8','g8','active','linked');
           INSERT INTO care_assignments VALUES ('p1','m1','active'), ('p1','m3','closed'), ('p4','m1','active'), ('p5','m1','active'), ('p6','m1','active');
           INSERT INTO medical_records VALUES ('p5','EXP-p5'), ('p6','EXP-p6')");
$dc->exec("INSERT INTO consents VALUES ('g1','h1'," . ($t - 60) . ',' . ($t + 3600) . ",'active'), ('g5','h1'," . ($t - 7200) . ',' . ($t - 60) . ",'active');
           INSERT INTO mpi_records VALUES ('g1','h1','EXP-p1'), ('g1','h3','H3-0001'), ('g1','h2','H2-0007')");
[$proceso, $p] = servidor("sqlite:$bh", "sqlite:$bc");
$r = fn (string $pac, string $sufijo = '') => "/patients/$pac/record$sufijo";

$o = [http($p, 'POST', $r('p1')), $GLOBALS['json']['expediente'] ?? '', http($p, 'POST', $r('p1')), http($p, 'POST', $r('p2')), http($p, 'POST', $r('p3')), http($p, 'POST', $r('p99'))];
prueba('CN-01', 'equivalencia de la apertura: activo = 201 EXP-p1; otra vez = 409 E3; inactivo, fallecido e inexistente = 422 E2 (R-01 a R-03)',
       $o === ['201', 'EXP-p1', '409 E3', '422 E2', '422 E2', '422 E2'], implode(', ', $o));

$o = [http($p, 'GET', $r('p1')), http($p, 'GET', $r('p1'), 'm2'), http($p, 'GET', $r('p1'), 'm3'), http($p, 'GET', $r('p4'))];
prueba('CN-02', 'consulta autorizada: asignado = 200; sin asignación y con asignación cerrada = 403 E4; asignado sin expediente = 404 NF (R-05)',
       $o === ['200', '403 E4', '403 E4', '404 NF'], implode(', ', $o));

$o = [http($p, 'GET', $r('p1', '/external')), implode(' ', array_column($GLOBALS['json']['externos'] ?? [], 'hospital')), http($p, 'GET', $r('p5', '/external')),
      http($p, 'GET', $r('p6', '/external')), json_encode($GLOBALS['json']['solo_local'] ?? null)];
prueba('CN-03', 'longitudinal: consentimiento vigente = 200 con h2 y h3 (no el propio); vencido hace 60 s = 403 E5; sin vínculo MPI = solo local (R-06, R-10)',
       $o === ['200', 'h2 h3', '403 E5', '200', 'true'], implode(', ', $o));

$o = [http($p, 'POST', $r('p7'), ''), http($p, 'POST', $r('p8'), 'm2'), http($p, 'GET', $r('p5'), 'm1', null)];
prueba('CN-04', 'permisos y sesión: abrir sin profesional = 401, abrir sin relación de atención = 403 y consultar sin hospital = 401 (CP-04 de la semana 14, H-02 de la semana 13)',
       $o === ['401', '403', '401'], implode(', ', $o));

$o = [http($p, 'DELETE', $r('p5')), http($p, 'POST', $r('p5', '/external')), http($p, 'GET', '/patients/p5'), http($p, 'GET', $r('p5', '/'))];
prueba('CN-05', 'errores del cliente: borrar el expediente (R-04), abrir por la ruta externa, ruta sin /record y barra final = 404', $o === ['404', '404', '404', '404'], implode(', ', $o));

$antes = (int) $dh->query('SELECT COUNT(*) FROM audit_events')->fetchColumn(); http($p, 'GET', $r('p6')); http($p, 'GET', $r('p6'), 'm2'); http($p, 'POST', $r('p2'));
$o = $dh->query("SELECT actor || ' ' || action || ' ' || outcome FROM audit_events WHERE id > $antes ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
prueba('CN-06', 'auditoría por HTTP: consulta aceptada, consulta rechazada y apertura rechazada dejan 3 eventos con su resultado (R-08, RF-05)',
       $o === ['m1 consultar ok', 'm2 consultar E4', 'm1 abrir E2'], json_encode($o));

try { $filas = $dh->exec("UPDATE audit_events SET outcome = 'ok' WHERE outcome = 'E4'"); } catch (PDOException) { $filas = 'rechazado'; }
prueba('CN-07', 'integridad: la base HOSPITAL rechaza cambiar un evento de auditoría (R-08, H-04 de la semana 13)', $filas === 'rechazado', "UPDATE aceptado: $filas filas cambiadas de E4 a ok");

$dc->exec("DROP TABLE consents; CREATE VIEW consents AS SELECT 'g1' AS global_id, 'h1' AS hospital_id, abs(-9223372036854775807 - 1) AS valid_from, NULL AS valid_to, 'active' AS status");
$o = [http($p, 'GET', $r('p1', '/external')), str_contains($GLOBALS['cuerpo'], 'SQLSTATE') ? 'con detalles' : 'sin detalles',
      $dh->query('SELECT action || \' \' || outcome FROM audit_events ORDER BY id DESC LIMIT 1')->fetchColumn()];
prueba('CN-08', 'CENTRAL caída: la consulta longitudinal = 503 E8 sin detalles y deja su evento E8 (RNF-04, semana 6)', $o === ['503 E8', 'sin detalles', 'consultar_longitudinal E8'], implode(', ', $o));
proc_terminate($proceso); $dh = $dc = null;

[$proceso, $p] = servidor('sqlite:' . sys_get_temp_dir() . '/s15-no-existe/hospital.sqlite', "sqlite:$bc");
$o = http($p, 'GET', $r('p5')); $tecnico = str_contains($GLOBALS['cuerpo'], 'SQLSTATE');
prueba('CN-09', 'base HOSPITAL no disponible al arrancar = 503 E8 con el mensaje amable, sin detalles técnicos (RNF-04)', $o === '503 E8' && !$tecnico,
       $o . ($tecnico ? '; el cuerpo muestra el «Fatal error» de PHP con SQLSTATE y la ruta de index.php' : ''));
proc_terminate($proceso);
@unlink($bh); @unlink($bc);

echo "RESULTADO: " . ($total - $fallos) . " de $total pruebas pasan" . ($fallos ? "; $fallos fallan (ver defectos)" : '') . "\n";
exit($fallos === 0 ? 0 : 1);
