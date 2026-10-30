<?php
declare(strict_types=1);

// Semana 15: pruebas unitarias (U), de caja blanca (CB) y de caja negra por HTTP (CN) del Micro-HIS de Dashboard de ocupación, trazadas a RF/RNF.
// No modifica el Micro-HIS. Uso: php pruebas-s15.php   (las CN levantan php -S en un puerto libre, con un SQLite temporal nuevo)
require __DIR__ . '/../../micro-his-dashboard-ocupacion/bootstrap.php';

use MicroHis\DashboardOcupacion\Aplicacion\{ConsolidadorOcupacion, ConsultaOcupacion, ErrorPersistencia, FuenteCamas, LectorCortes, Reloj};
use MicroHis\DashboardOcupacion\Dominio\{Corte, OcupacionSala, ReglaDeDominio};
use MicroHis\DashboardOcupacion\Persistencia\RepositorioEnMemoria;

const MICRO_HIS = __DIR__ . '/../../micro-his-dashboard-ocupacion';
$total = $fallos = 0;
function prueba(string $id, string $nombre, bool $ok, string $obtenido): void
{
    global $total, $fallos; $total++; $fallos += $ok ? 0 : 1;
    echo ($ok ? 'OK    ' : 'FALLA ') . "$id $nombre" . ($ok ? '' : "\n      obtenido: $obtenido") . "\n";
}
/** Código del rechazo, u «ok» si el caso de uso terminó sin rechazo. */
function codigo(callable $f): string { try { $f(); return 'ok'; } catch (ReglaDeDominio $e) { return $e->codigo; } catch (ErrorPersistencia) { return 'E8'; } }
$cama = fn (string $c, string $e, int $n = 0) => ['cama' => $c, 'estado' => $e, 'estancias' => $n];
$reloj = new class implements Reloj { public int $t = 1_000_000; public function ahora(): int { return $this->t; } };

echo "== Unitarias\n";
$a = OcupacionSala::calcular([$cama('A1', 'occupied', 1), $cama('A2', 'available'), $cama('A3', 'maintenance'), $cama('A4', 'inactive')]);
$b = OcupacionSala::calcular([$cama('B1', 'occupied', 1), $cama('B2', 'available'), $cama('B3', 'available')]);
$o = [$a['ocupacion'], $a['maintenance'], $a['inactive'], $b['ocupacion']];
prueba('U-01', 'ocupación = ocupadas / (disponibles + ocupadas): 1 de 2 = 50.0 aunque haya mantenimiento e inactiva aparte; 1 de 3 = 33.3 (R-01)',
       $o === [50.0, 1, 1, 33.3], json_encode($o, JSON_PRESERVE_ZERO_FRACTION));

$o = [OcupacionSala::calcular([$cama('C1', 'maintenance'), $cama('C2', 'inactive')])['ocupacion'], OcupacionSala::calcular([])['ocupacion'],
      OcupacionSala::calcular([$cama('D1', 'occupied', 1)])['ocupacion']];
prueba('U-02', 'sin camas operativas o sin camas = «no aplica», nunca división entre cero; todas ocupadas = 100.0 (R-02)', $o === ['no aplica', 'no aplica', 100.0],
       json_encode($o, JSON_PRESERVE_ZERO_FRACTION));

$o = OcupacionSala::calcular([$cama('E1', 'occupied', 1), $cama('E2', 'occupied', 0), $cama('E3', 'occupied', 2), $cama('E4', 'available', 1), $cama('E5', 'maintenance', 0)])['inconsistentes'];
prueba('U-03', 'inconsistencias: ocupada sin estancia, ocupada con dos y disponible con una se marcan; las coherentes no (R-04, semana 6)', $o === ['E2', 'E3', 'E4'], json_encode($o));

$o = [(new Corte(0, []))->esViejo(900), (new Corte(0, []))->esViejo(901), (new Corte(0, []))->esViejo(0)];
prueba('U-04', 'actualización: 900 s no es viejo, 901 s sí (más de 15 minutos), 0 s no (R-06)', $o === [false, true, false], json_encode($o));

$corte = new Corte(0, ['A' => ['available' => 1, 'occupied' => 0, 'maintenance' => 0, 'inactive' => 0], 'B' => ['available' => 0, 'occupied' => 2, 'maintenance' => 0, 'inactive' => 0]]);
$o = [implode(' ', array_keys($corte->filtrar(null))), implode(' ', array_keys($corte->filtrar('occupied'))), implode(' ', array_keys($corte->filtrar('inactive'))),
      codigo(fn () => $corte->filtrar('OCCUPIED')), codigo(fn () => $corte->filtrar(''))];
prueba('U-05', 'filtro por estado de cama: sin filtro todas; «occupied» solo B; «inactive» ninguna; mayúsculas y vacío = E3 (CU-03)', $o === ['A B', 'B', '', 'E3', 'E3'], json_encode($o));

$r = new RepositorioEnMemoria(['A' => true, 'V' => true, 'X' => false], [['A', 'A1', 'occupied', 1], ['A', 'A2', 'available', 0], ['X', 'X1', 'occupied', 1]]);
$reloj->t = 1_000_000; $c = (new ConsolidadorOcupacion($r, $r, $reloj))->consolidar();
$claves = array_unique(array_merge(...array_map('array_keys', array_values($c->salas))));
$o = [implode(' ', array_keys($c->salas)), $c->salas['V']['ocupacion'], $r->ultimoCorte()->hora, implode(' ', $claves)];
prueba('U-06', 'procedencia: solo salas activas (sin X), la sala sin camas da «no aplica», el corte guarda su hora y solo lleva cifras (R-03, R-05, R-07)',
       $o === ['A V', 'no aplica', 1_000_000, 'available occupied maintenance inactive ocupacion inconsistentes'], json_encode($o));

echo "== Caja blanca\n";
// Doble espía del lector de cortes: cuenta las lecturas y entrega el corte fijado (o ninguno).
$lector = new class implements LectorCortes { public ?Corte $corte = null; public int $lecturas = 0; public function ultimoCorte(): ?Corte { $this->lecturas++; return $this->corte; } };
$q = new ConsultaOcupacion($lector, $reloj);
$camino = function (?Corte $c, ?string $estado) use ($q, $lector) { $lector->corte = $c; $r = null; $k = codigo(function () use ($q, $estado, &$r) { $r = $q->consultar($estado); });
    return $k !== 'ok' ? $k : ($r['vacio'] ? 'vacio' : count($r['salas']) . ' salas' . ($r['aviso_viejo'] ? ' viejo' : '')); };
$reloj->t = 1000; $reciente = new Corte(900, $corte->salas);   // 100 s: sin aviso; $corte (hora 0) tiene 1000 s: viejo
$o = [$camino(null, 'occupied'), $camino($reciente, null), $camino($reciente, 'ocupada'), $camino($reciente, 'available'), $camino($corte, null)];
prueba('CB-01', 'consultar() + filtrar(): 4 caminos (V(G) = 4): sin corte = vacío (aunque el filtro sea inválido), sin filtro, estado inválido = E3 y filtrado; más el aviso de viejo',
       $o === ['vacio', '2 salas', 'E3', '1 salas', '2 salas viejo'], implode(', ', $o));

// Doble de la fuente que falla y espía del escritor: si la lectura falla no se guarda nada (R-09).
$falla = new class implements FuenteCamas { public function camasDeSalasActivas(): array { throw new ErrorPersistencia('sin base'); } };
$espia = new class implements MicroHis\DashboardOcupacion\Aplicacion\EscritorCortes { public int $n = 0; public function guardarCorte(Corte $c): void { $this->n++; } };
$o = [codigo(fn () => (new ConsolidadorOcupacion($falla, $espia, $reloj))->consolidar()), $espia->n];
$sinCamas = new RepositorioEnMemoria(['S' => true], []); (new ConsolidadorOcupacion($sinCamas, $espia, $reloj))->consolidar(); $o[] = $espia->n;
$cond = array_map(fn (array $c) => OcupacionSala::calcular([$c])['inconsistentes'] === [] ? 'F' : 'V',
                  [$cama('1', 'occupied', 1), $cama('2', 'occupied', 0), $cama('3', 'occupied', 2), $cama('4', 'available', 0), $cama('5', 'available', 1)]);
$o[] = implode('', $cond);
prueba('CB-02', 'consolidar(): la lectura que falla sube antes de guardar (0 escrituras) y «cama === null» con una sala sin camas; condición de R-04 con 5 combinaciones',
       $o === ['E8', 0, 1, 'FVVFV'], json_encode($o));

echo "== Caja negra (HTTP)\n";
function servidor(string $dsn): array
{
    $libre = stream_socket_server('tcp://127.0.0.1:0'); $puerto = (int) substr(strrchr(stream_socket_get_name($libre, false), ':'), 1); fclose($libre);
    $log = sys_get_temp_dir() . '/s15-ocupacion-servidor.log';
    $p = proc_open([PHP_BINARY, '-S', "127.0.0.1:$puerto", '-t', MICRO_HIS . '/public'], [['pipe', 'r'], ['file', $log, 'w'], ['file', $log, 'a']],
                   $tubos, null, ['OCUPACION_DSN' => $dsn] + getenv());
    for ($i = 0; $i < 50 && !@fsockopen('127.0.0.1', $puerto); $i++) usleep(100000);
    return [$p, $puerto];
}
/** Respuesta como «status código»; el cuerpo decodificado queda en $GLOBALS['json'] y el crudo en $GLOBALS['cuerpo']. */
function http(int $puerto, string $ruta, string $metodo = 'GET', string $cabeceras = ''): string
{
    $ctx = stream_context_create(['http' => ['method' => $metodo, 'ignore_errors' => true, 'header' => $cabeceras]]);
    $b = $GLOBALS['cuerpo'] = (string) file_get_contents("http://127.0.0.1:$puerto$ruta", false, $ctx);
    $GLOBALS['json'] = json_decode($b, true) ?? [];
    preg_match('#HTTP/\S+ (\d+)#', $http_response_header[0], $m);
    return trim($m[1] . ' ' . ($GLOBALS['json']['error'] ?? ''));
}
$base = sys_get_temp_dir() . '/s15-ocupacion-' . getmypid() . '.sqlite';
$db = new PDO("sqlite:$base"); $db->exec(file_get_contents(MICRO_HIS . '/database/schema.sql'));
$db->exec("INSERT INTO wards (ward_id, code, status) VALUES (1, 'A', 'active'), (2, 'M', 'active'), (3, 'X', 'inactive');
           INSERT INTO beds (bed_id, ward_id, code, status) VALUES (1, 1, 'A1', 'occupied'), (2, 1, 'A2', 'available'), (3, 1, 'A3', 'occupied'), (4, 1, 'A4', 'occupied'),
                                                                   (5, 2, 'M1', 'maintenance'), (6, 3, 'X1', 'occupied');
           INSERT INTO bed_stays (bed_id, ended_at) VALUES (1, NULL), (3, NULL), (3, NULL), (4, '2026-10-29'), (6, NULL)");
[$proceso, $p] = servidor("sqlite:$base");

$o = [http($p, '/ocupacion'), ($GLOBALS['json']['vacio'] ?? null) === true ? 'vacío' : 'con datos', http($p, '/ocupacion/cortes', 'POST'), $GLOBALS['json']['salas'] ?? null];
$sala = fn (string $s, string $k) => json_encode(http($p, '/ocupacion') === '200' ? ($GLOBALS['json']['salas'][$s][$k] ?? null) : null, JSON_PRESERVE_ZERO_FRACTION);
$o = array_merge($o, [$sala('A', 'ocupacion'), $sala('M', 'ocupacion'), $sala('A', 'inconsistentes')]);
prueba('CN-01', 'equivalencia del corte: sin corte = estado vacío; consolidar = 201 con 2 salas activas; A = 75.0, M = «no aplica»; A3 y A4 inconsistentes (R-01 a R-04, E4)',
       $o === ['200', 'vacío', '201', 2, '75.0', '"no aplica"', '["A3","A4"]'], json_encode($o));

$o = [];
foreach ([0, 880, 920] as $edad) { $db->exec("INSERT INTO occupancy_snapshots (taken_at, payload) VALUES (" . (time() - $edad) . ", '{}')"); http($p, '/ocupacion'); $o[] = $GLOBALS['json']['aviso_viejo'] ?? null; }
prueba('CN-02', 'alrededor del límite de 15 min (con margen por el reloj del servidor; el límite exacto está en U-04): 0 s y 880 s sin aviso; 920 s con aviso (R-05, R-06)',
       $o === [false, false, true], json_encode($o));

http($p, '/ocupacion/cortes', 'POST'); $antes = $db->query('SELECT group_concat(status) FROM beds')->fetchColumn();
$o = [http($p, '/ocupacion?estado=occupied'), implode(' ', array_keys($GLOBALS['json']['salas'] ?? [])), http($p, '/ocupacion?estado=maintenance'), implode(' ', array_keys($GLOBALS['json']['salas'] ?? [])),
      http($p, '/ocupacion?estado=OCCUPIED'), http($p, '/ocupacion?estado='), $db->query('SELECT group_concat(status) FROM beds')->fetchColumn() === $antes ? 'camas iguales' : 'camas cambiadas'];
prueba('CN-03', 'filtro y solo lectura: «occupied» = A; «maintenance» = M; mayúsculas y vacío = 422; consolidar no cambia ninguna cama (CU-03, RNF-02, R-09)',
       $o === ['200', 'A', '200', 'M', '422 E3', '422 E3', 'camas iguales'], json_encode($o));

$o = [http($p, '/ocupacion'), http($p, '/ocupacion', 'GET', 'X-Tenant-ID: hospital-ajeno'), http($p, '/ocupacion/cortes', 'POST', 'X-Tenant-ID: hospital-ajeno')];
prueba('CN-04', 'permisos: consultar sin permiso de ocupación, y consultar o consolidar con un hospital ajeno = 403 (RNF-01, R-08, H-01 de la semana 13)',
       $o === ['403', '403', '403'], implode(', ', $o));

$o = [http($p, '/ocupacion/cortes'), http($p, '/ocupacion', 'DELETE'), http($p, '/ocupacion/salas/A')];
prueba('CN-05', 'errores del cliente: GET de cortes, DELETE del tablero y ruta inexistente = 404', $o === ['404', '404', '404'], implode(', ', $o));

http($p, '/ocupacion'); $hora = $GLOBALS['json']['hora'] ?? null;
$db->exec("DROP TABLE wards; CREATE VIEW wards AS SELECT abs(-9223372036854775807 - 1) AS ward_id, 'A' AS code, 'active' AS status");   // la lectura de camas falla
$o = [http($p, '/ocupacion/cortes', 'POST'), str_contains($GLOBALS['cuerpo'], 'SQLSTATE') ? 'con detalles' : 'sin detalles', http($p, '/ocupacion'), ($GLOBALS['json']['hora'] ?? null) === $hora ? 'mismo corte' : 'otro corte'];
prueba('CN-06', 'falla de lectura: consolidar = 503 E8 sin detalles y la consulta sigue mostrando el corte anterior (R-09, RF-06, RNF-04)',
       $o === ['503 E8', 'sin detalles', '200', 'mismo corte'], implode(', ', $o));

$db->exec("DROP VIEW wards; CREATE TABLE wards (ward_id INTEGER PRIMARY KEY, code TEXT NOT NULL UNIQUE, status TEXT NOT NULL);
           INSERT INTO wards VALUES (1, 'A', 'active'), (2, 'M', 'active'), (3, 'X', 'inactive')"); $db = null;
http($p, '/ocupacion/cortes', 'POST'); http($p, '/ocupacion');
$o = json_encode(array_merge(...array_column(array_values($GLOBALS['json']['salas'] ?? []), 'inconsistentes')));
prueba('CN-07', 'procedencia: la cama X1, ocupada y con estancia abierta en la sala inactiva X, queda visible como inconsistencia (R-03, R-04, H-04 de la semana 13)',
       str_contains($o, 'X1'), "inconsistentes = $o; X1 no aparece en el corte");
proc_terminate($proceso);

[$proceso, $p] = servidor('sqlite:' . sys_get_temp_dir() . '/s15-no-existe/ocupacion.sqlite');
$o = http($p, '/ocupacion'); $tecnico = str_contains($GLOBALS['cuerpo'], 'SQLSTATE');
prueba('CN-08', 'base no disponible al arrancar = 503 E8 con el mensaje amable, sin detalles técnicos (R-09, RNF-04)', $o === '503 E8' && !$tecnico,
       $o . ($tecnico ? '; el cuerpo muestra el «Fatal error» de PHP con SQLSTATE y la ruta de index.php' : ''));
proc_terminate($proceso);
@unlink($base);

echo "RESULTADO: " . ($total - $fallos) . " de $total pruebas pasan" . ($fallos ? "; $fallos fallan (ver defectos)" : '') . "\n";
exit($fallos === 0 ? 0 : 1);
