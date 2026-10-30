<?php
declare(strict_types=1);

// Semana 15: pruebas unitarias (U), de caja blanca (CB) y de caja negra por HTTP (CN) del Micro-HIS de Catálogo, trazadas a RF/RNF.
// No modifica el Micro-HIS. Uso: php pruebas-s15.php   (las CN levantan php -S en un puerto libre, con un SQLite temporal nuevo)
require __DIR__ . '/../../micro-his-catalogo-camas/bootstrap.php';

use MicroHis\Catalogo\Aplicacion\{RepositorioCatalogo, ServicioCatalogo};
use MicroHis\Catalogo\Dominio\{EstadoCama, ReglaDeDominio};
use MicroHis\Catalogo\Persistencia\RepositorioEnMemoria;
use MicroHis\Catalogo\Presentacion\VistaJson;

const MICRO_HIS = __DIR__ . '/../../micro-his-catalogo-camas';
$total = $fallos = 0;
function prueba(string $id, string $nombre, bool $ok, string $obtenido): void
{
    global $total, $fallos; $total++; $fallos += $ok ? 0 : 1;
    echo ($ok ? 'OK    ' : 'FALLA ') . "$id $nombre" . ($ok ? '' : "\n      obtenido: $obtenido") . "\n";
}
/** Código del rechazo, u «ok» si el caso de uso terminó sin rechazo. */
function codigo(callable $f): string { try { $f(); return 'ok'; } catch (ReglaDeDominio $e) { return $e->codigo; } }

// Doble espía del puerto: salas y camas fijas, cuenta las escrituras y puede simular que otro usuario ganó la carrera (E7).
trait Doble
{
    public int $escrituras = 0;
    public bool $ganaOtro = false;
    public function salaActiva(string $s): ?bool { return ['A' => true, 'X' => false][$s] ?? null; }
    public function guardarSala(string $s, string $n, ?int $capacidad = null): void { $this->escrituras++; }
    public function estadoCama(string $s, string $c): ?string { return ['101' => 'available', '102' => 'occupied'][$c] ?? null; }
    public function cambiarEstado(string $s, string $c, string $de, string $a, string $m): bool { $this->escrituras++; return !$this->ganaOtro; }
    public function capacidad(string $s): ?int { return null; }
    public function camasEnUso(string $s): int { return 0; }
}
// Con la semana 6 (PR #433, sin integrar) guardarCama devuelve bool: el doble sirve para las dos versiones del puerto.
$doble = (string) (new ReflectionMethod(RepositorioCatalogo::class, 'guardarCama'))->getReturnType() === 'bool'
    ? new class implements RepositorioCatalogo { use Doble; public function guardarCama(string $s, string $c): bool { $this->escrituras++; return true; } }
    : new class implements RepositorioCatalogo { use Doble; public function guardarCama(string $s, string $c): void { $this->escrituras++; } };

echo "== Unitarias\n";
$estados = ['available', 'occupied', 'maintenance', 'inactive'];
$esperado = ['available' => ['E6', 'E6', 'ok', 'ok'], 'occupied' => ['E5', 'E5', 'E5', 'E5'],
             'maintenance' => ['ok', 'E6', 'E6', 'ok'], 'inactive' => ['ok', 'E6', 'E6', 'E6']];
$distintos = [];
foreach ($esperado as $de => $fila) {
    foreach ($estados as $i => $a) {
        $o = codigo(fn () => EstadoCama::from($de)->validarCambioA(EstadoCama::from($a)));
        if ($o !== $fila[$i]) $distintos[] = "$de -> $a = $o";
    }
}
prueba('U-01', 'matriz de transiciones 4 x 4 sin base de datos: 16 casos (R-05, R-06)', $distintos === [], implode(', ', $distintos));

$r = new RepositorioEnMemoria(); $s = new ServicioCatalogo($r);
$s->registrarSala('A', 'Medicina'); $s->registrarSala('B', 'Cirugía'); $s->registrarCama('A', '101');
$o = [$r->estadoCama('A', '101'), codigo(fn () => $s->registrarCama('B', '101')), codigo(fn () => $s->registrarCama('A', '101'))];
prueba('U-02', 'la cama nace disponible y su código es único solo dentro de su sala (R-02, R-04)', $o === ['available', 'ok', 'E3'], implode(', ', $o));

$o = [codigo(fn () => $s->cambiarEstado('A', '101', 'maintenance', '   ')), $r->estadoCama('A', '101'), count($r->historial())];
prueba('U-03', 'motivo de solo espacios = E2; la cama no cambia ni deja historial (R-09)', $o === ['E2', 'available', 0], implode(', ', $o));

preg_match_all("/ReglaDeDominio\('(\w+)'/", file_get_contents(MICRO_HIS . '/src/Dominio/EstadoCama.php') . file_get_contents(MICRO_HIS . '/src/Aplicacion/ServicioCatalogo.php'), $m);
$sinHttp = array_diff(array_unique($m[1]), array_keys((new ReflectionClassConstant(VistaJson::class, 'HTTP'))->getValue()));
prueba('U-04', 'cada código de rechazo (' . implode(', ', array_unique($m[1])) . ') tiene su HTTP fijo (RNF-04)', $sinHttp === [], implode(', ', $sinHttp));

if (class_exists('MicroHis\Catalogo\Dominio\CapacidadSala')) {
    $tope = fn ($max, int $enUso = 0) => codigo(fn () => (new MicroHis\Catalogo\Dominio\CapacidadSala($max))->admiteOtraCama($enUso));
    $o = [$tope(0), $tope('2'), $tope(2, 1), $tope(2, 2), $tope(null, 1000)];
    prueba('U-05', 'capacidad de sala: 0 y texto = E2; 1 de 2 admite; 2 de 2 = E9; sin tope admite (semana 6)', $o === ['E2', 'E2', 'ok', 'E9', 'ok'], implode(', ', $o));
} else {
    echo "PEND. U-05 capacidad de sala: CapacidadSala llega con la semana 6 (PR #433, sin integrar)\n";
}

echo "== Caja blanca\n";
$caminos = [   // [cama, estado, motivo, gana otro usuario, código esperado, escrituras esperadas]
    'C1 cama inexistente' => ['999', 'maintenance', 'x', false, 'NF', 0], 'C2 motivo vacío' => ['101', 'maintenance', '', false, 'E2', 0],
    'C3 estado desconocido' => ['101', 'borrada', 'x', false, 'E2', 0], 'C4 el dominio rechaza' => ['102', 'available', 'x', false, 'E5', 0],
    'C5 otro usuario ganó' => ['101', 'maintenance', 'x', true, 'E7', 1], 'C6 éxito' => ['101', 'maintenance', 'x', false, 'ok', 1],
];
$s = new ServicioCatalogo($doble); $distintos = [];
foreach ($caminos as $nombre => [$cama, $estado, $motivo, $gana, $codigo, $escrituras]) {
    $doble->ganaOtro = $gana; $antes = $doble->escrituras;
    $o = codigo(fn () => $s->cambiarEstado('A', $cama, $estado, $motivo));
    if ($o !== $codigo || $doble->escrituras - $antes !== $escrituras) $distintos[] = "$nombre = $o con " . ($doble->escrituras - $antes) . ' escrituras';
}
prueba('CB-01', 'cambiarEstado: 6 caminos básicos (V(G) = 5 más la salida del dominio); solo C5 y C6 escriben', $distintos === [], implode('; ', $distintos));

$doble->escrituras = 0;
$o = [codigo(fn () => $s->registrarSala(' ', 'Cirugía')), codigo(fn () => $s->registrarSala('B', '')), codigo(fn () => $s->registrarSala('B', 'Cirugía')),
      codigo(fn () => $s->registrarCama('Z', '')), codigo(fn () => $s->registrarCama('X', '5')), codigo(fn () => $s->registrarCama('A', ' ')),
      codigo(fn () => $s->registrarCama('A', '101')), codigo(fn () => $s->registrarCama('A', '103')), $doble->escrituras];
prueba('CB-02', 'condiciones: MC/DC de «código vacío || nombre vacío» y orden sala -> código -> duplicado en registrarCama',
       $o === ['E2', 'E2', 'ok', 'E4', 'E4', 'E2', 'E3', 'ok', 2], implode(', ', $o));

echo "== Caja negra (HTTP)\n";
function servidor(string $dsn): array
{
    $libre = stream_socket_server('tcp://127.0.0.1:0'); $puerto = (int) substr(strrchr(stream_socket_get_name($libre, false), ':'), 1); fclose($libre);
    $log = sys_get_temp_dir() . '/s15-catalogo-servidor.log';
    $p = proc_open([PHP_BINARY, '-S', "127.0.0.1:$puerto", '-t', MICRO_HIS . '/public'], [['pipe', 'r'], ['file', $log, 'w'], ['file', $log, 'a']],
                   $tubos, null, ['CATALOGO_DSN' => $dsn] + getenv());
    for ($i = 0; $i < 50 && !@fsockopen('127.0.0.1', $puerto); $i++) usleep(100000);
    return [$p, $puerto];
}
function http(int $puerto, string $metodo, string $ruta, string $cuerpo = '', array $cabeceras = []): string
{
    $ctx = stream_context_create(['http' => ['method' => $metodo, 'content' => $cuerpo, 'ignore_errors' => true,
        'header' => implode("\r\n", ['Content-Type: application/json', ...$cabeceras])]]);
    $b = $GLOBALS['cuerpo'] = (string) file_get_contents("http://127.0.0.1:$puerto$ruta", false, $ctx);
    preg_match('#HTTP/\S+ (\d+)#', $http_response_header[0], $m);
    return trim($m[1] . ' ' . (json_decode($b, true)['error'] ?? ''));
}
$j = fn (array $d) => json_encode($d, JSON_UNESCAPED_UNICODE);
$base = sys_get_temp_dir() . '/s15-catalogo-' . getmypid() . '.sqlite';
[$proceso, $p] = servidor("sqlite:$base");
$o = [http($p, 'POST', '/salas', $j(['codigo' => 'A', 'nombre' => 'Medicina'])), http($p, 'POST', '/salas/A/camas', $j(['codigo' => '101']))];
$estado = fn (string $e, string $motivo = 'revisión') => http($p, 'PATCH', '/salas/A/camas/101/estado', $j(['estado' => $e, 'motivo' => $motivo]));
$o = [...$o, $estado('occupied'), $estado('AVAILABLE'), $estado(''), $estado('borrada'), $estado('maintenance')];
prueba('CN-01', 'equivalencia del estado destino: válida (maintenance) e inválidas (occupied a mano, AVAILABLE, vacío, borrada)',
       $o === ['201', '201', '422 E6', '422 E2', '422 E2', '422 E2', '200'], implode(', ', $o));

$o = array_map(fn ($c) => http($p, 'POST', '/salas', $j(['codigo' => $c, 'nombre' => 'Sala'])), ['', ' ', 'B', ' A']);
prueba('CN-02', 'límites del código de sala: vacío, un espacio, 1 carácter y « A» frente a la sala A existente (R-01)',
       $o === ['422 E2', '422 E2', '201', '409 E3'], implode(', ', $o));

$o = [$estado('available', ''), $estado('available', '   '), $estado('available', 'x')];
prueba('CN-03', 'límites del motivo: vacío, solo espacios y 1 carácter (RF-03, R-09)', $o === ['422 E2', '422 E2', '200'], implode(', ', $o));

$o = [http($p, 'POST', '/salas', $j(['codigo' => 'C', 'nombre' => 'Sala'])), http($p, 'POST', '/salas', $j(['codigo' => 'D', 'nombre' => 'Sala']), ['X-Tenant-ID: hospital-ajeno'])];
prueba('CN-04', 'permisos: sin X-Tenant-ID ni permiso de catálogo, y con el tenant de otro hospital = 403 (RNF-01, R-07)', $o === ['403', '403'], implode(', ', $o));

$o = [http($p, 'GET', '/salas'), http($p, 'PATCH', '/salas/A/camas/999/estado', $j(['estado' => 'inactive', 'motivo' => 'x'])), http($p, 'POST', '/salas', 'esto no es JSON')];
prueba('CN-05', 'errores del cliente: ruta desconocida, cama inexistente y cuerpo que no es JSON', $o === ['404', '404 NF', '422 E2'], implode(', ', $o));
proc_terminate($proceso);

[$proceso, $p] = servidor('sqlite:' . sys_get_temp_dir() . '/s15-no-existe/catalogo.sqlite');
$o = http($p, 'POST', '/salas', $j(['codigo' => 'A', 'nombre' => 'Medicina']));
$tecnico = str_contains($cuerpo, 'SQLSTATE');
prueba('CN-06', 'base no disponible al arrancar = 503 E8, sin detalles técnicos (E8, RNF-04)', $o === '503 E8' && !$tecnico,
       $o . ($tecnico ? '; el cuerpo muestra el «Fatal error» de PHP con SQLSTATE y la ruta de index.php' : ''));
proc_terminate($proceso);
@unlink($base);

echo "RESULTADO: " . ($total - $fallos) . " de $total pruebas pasan" . ($fallos ? "; $fallos fallan (ver defectos)" : '') . "\n";
exit($fallos === 0 ? 0 : 1);
