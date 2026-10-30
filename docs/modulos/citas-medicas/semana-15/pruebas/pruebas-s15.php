<?php
declare(strict_types=1);

// Semana 15: pruebas unitarias (U), de caja blanca (CB) y de caja negra por HTTP (CN) del Micro-HIS de Citas, trazadas a RF/RNF.
// No modifica el Micro-HIS. Uso: php pruebas-s15.php   (las CN levantan php -S en un puerto libre, con un SQLite temporal nuevo)
require __DIR__ . '/../../micro-his-citas/bootstrap.php';

use MicroHis\Citas\Aplicacion\{Reloj, RepositorioCitas, ServicioCitas};
use MicroHis\Citas\Dominio\{EstadoCita, Horario, ReglaDeDominio};
use MicroHis\Citas\Persistencia\RepositorioEnMemoria;
use MicroHis\Citas\Presentacion\VistaJson;

const MICRO_HIS = __DIR__ . '/../../micro-his-citas';
$total = $fallos = 0;
function prueba(string $id, string $nombre, bool $ok, string $obtenido): void
{
    global $total, $fallos; $total++; $fallos += $ok ? 0 : 1;
    echo ($ok ? 'OK    ' : 'FALLA ') . "$id $nombre" . ($ok ? '' : "\n      obtenido: $obtenido") . "\n";
}
/** Código del rechazo, u «ok» si el caso de uso terminó sin rechazo. */
function codigo(callable $f): string { try { $f(); return 'ok'; } catch (ReglaDeDominio $e) { return $e->codigo; } }
$reloj = new class implements Reloj { public function ahora(): int { return 1000; } };   // «ahora» fijo (RNF-03)

// Doble espía del puerto: respuestas configurables y cuenta de escrituras.
$doble = new class implements RepositorioCitas {
    public bool $vigente = true; public ?string $cruce = null; public bool $ganaOtro = false; public bool $tomado = false; public int $escrituras = 0;
    public function medicoVigente(string $m, string $e): bool { return $this->vigente; }
    public function cruce(string $m, string $p, int $i, int $f): ?string { return $this->cruce; }
    public function guardar(string $m, string $p, string $e, Horario $h, string $mot): int
    {
        $this->escrituras++;
        if ($this->tomado) throw new MicroHis\Citas\Aplicacion\HorarioTomado();   // solo existe con la semana 6 (PR #435)
        return 7;
    }
    public function estado(int $id): ?string { return [1 => 'scheduled', 2 => 'completed'][$id] ?? null; }
    public function cambiarEstado(int $id, string $de, string $a, ?string $m): bool { $this->escrituras++; return !$this->ganaOtro; }
};

echo "== Unitarias\n";
$estados = ['scheduled', 'confirmed', 'completed', 'cancelled'];
$esperado = ['scheduled' => ['E7', 'ok', 'E7', 'ok'], 'confirmed' => ['E7', 'E7', 'E7', 'ok'], 'completed' => ['E7', 'E7', 'E7', 'E7'], 'cancelled' => ['E7', 'E7', 'E7', 'E7']];
$distintos = [];
foreach ($esperado as $de => $fila) {
    foreach ($estados as $i => $a) {
        $o = codigo(fn () => EstadoCita::from($de)->validarCambioA(EstadoCita::from($a)));
        if ($o !== $fila[$i]) $distintos[] = "$de -> $a = $o";
    }
}
prueba('U-01', 'matriz de estados 4 x 4: 3 permitidos y 13 E7 (R-07)', $distintos === [], implode(', ', $distintos));

$h = fn (int $i, int $f) => codigo(fn () => Horario::validar($i, $f, 1000));
$o = [$h(1000, 2000), $h(1001, 1001), $h(1001, 1000), $h(1001, 1002)];
prueba('U-02', 'límites del horario: inicio = ahora, fin = inicio y fin < inicio = E2; 1 segundo después admite (R-01)', $o === ['E2', 'E2', 'E2', 'ok'], implode(', ', $o));

$r = new RepositorioEnMemoria([['m1', 'cardio'], ['m2', 'cardio']]); $s = new ServicioCitas($r, $reloj);
$id = $s->solicitar('m1', 'p1', 'cardio', 2000, 3000, 'control');
$o = [codigo(fn () => $s->solicitar('m1', 'p2', 'cardio', 3000, 4000, 'control')), codigo(fn () => $s->solicitar('m1', 'p3', 'cardio', 2999, 3500, 'control')),
      codigo(fn () => $s->solicitar('m2', 'p1', 'cardio', 1500, 2001, 'control'))];
prueba('U-03', 'cruce en [inicio, fin): contigua admite; 1 segundo cruzado = E4 (médico) y E5 (paciente) (R-02, R-03)', $o === ['ok', 'E4', 'E5'], implode(', ', $o));

$o = [codigo(fn () => $s->cancelar($id, '  ')), $r->estado($id), codigo(fn () => $s->cancelar($id, 'paciente avisó')), $r->cancelaciones()[0]['motivo'] ?? '-',
      codigo(fn () => $s->solicitar('m1', 'p4', 'cardio', 2000, 3000, 'control'))];
prueba('U-04', 'cancelar: motivo vacío = E2 sin cambio; con motivo queda guardado y el horario se libera (R-07, R-10)',
       $o === ['E2', 'scheduled', 'ok', 'paciente avisó', 'ok'], implode(', ', $o));

if (class_exists('MicroHis\Citas\Aplicacion\HorarioTomado')) {
    $doble->tomado = true; $o = codigo(fn () => (new ServicioCitas($doble, $reloj))->solicitar('m1', 'p1', 'cardio', 2000, 3000, 'x')); $doble->tomado = false;
    prueba('U-05', 'si otra solicitud gana el horario al guardar = E6 (semana 6)', $o === 'E6', $o);
} else {
    echo "PEND. U-05 horario tomado al guardar: HorarioTomado llega con la semana 6 (PR #435, sin integrar)\n";
}
preg_match_all("/ReglaDeDominio\('(\w+)'/", file_get_contents(MICRO_HIS . '/src/Dominio/Cita.php') . file_get_contents(MICRO_HIS . '/src/Aplicacion/ServicioCitas.php'), $m);
$sinHttp = array_diff(array_unique($m[1]), array_keys((new ReflectionClassConstant(VistaJson::class, 'HTTP'))->getValue()));
prueba('U-06', 'cada código de rechazo (' . implode(', ', array_unique($m[1])) . ') tiene su HTTP fijo', $sinHttp === [], implode(', ', $sinHttp));

echo "== Caja blanca\n";
$caminos = [   // [motivo, inicio, vigente, cruce, código esperado, escrituras esperadas]
    'C1 motivo vacío' => [' ', 2000, true, null, 'E2', 0], 'C2 el horario rechaza' => ['x', 1000, true, null, 'E2', 0],
    'C3 sin especialidad' => ['x', 2000, false, null, 'E3', 0], 'C4 cruce del médico' => ['x', 2000, true, 'medico', 'E4', 0],
    'C5 cruce del paciente' => ['x', 2000, true, 'paciente', 'E5', 0], 'C6 éxito' => ['x', 2000, true, null, 'ok', 1],
];
$s = new ServicioCitas($doble, $reloj); $distintos = [];
foreach ($caminos as $nombre => [$motivo, $inicio, $vigente, $cruce, $codigo, $escrituras]) {
    [$doble->vigente, $doble->cruce, $antes] = [$vigente, $cruce, $doble->escrituras];
    $o = codigo(fn () => $s->solicitar('m1', 'p1', 'cardio', $inicio, $inicio + 1800, $motivo));
    if ($o !== $codigo || $doble->escrituras - $antes !== $escrituras) $distintos[] = "$nombre = $o con " . ($doble->escrituras - $antes) . ' escrituras';
}
prueba('CB-01', 'solicitar: 6 caminos básicos (V(G) = 5 más la salida del dominio); solo C6 escribe', $distintos === [], implode('; ', $distintos));

$cambio = function (callable $f, bool $gana = false) use ($doble) { [$doble->ganaOtro, $antes] = [$gana, $doble->escrituras]; return codigo($f) . '/' . ($doble->escrituras - $antes); };
$o = [$cambio(fn () => $s->confirmar(9)), $cambio(fn () => $s->confirmar(2)), $cambio(fn () => $s->confirmar(1), true), $cambio(fn () => $s->confirmar(1)),
      $cambio(fn () => $s->cancelar(1, '')), $cambio(fn () => $s->cancelar(1, 'x'))];
prueba('CB-02', 'cambiar(): NF, E7 del dominio sin escribir, E7 de la carrera tras intentar y éxito; la condición del motivo en cancelar',
       $o === ['NF/0', 'E7/0', 'E7/1', 'ok/1', 'E2/0', 'ok/1'], implode(', ', $o));

echo "== Caja negra (HTTP)\n";
function servidor(string $dsn): array
{
    $libre = stream_socket_server('tcp://127.0.0.1:0'); $puerto = (int) substr(strrchr(stream_socket_get_name($libre, false), ':'), 1); fclose($libre);
    $log = sys_get_temp_dir() . '/s15-citas-servidor.log';
    $p = proc_open([PHP_BINARY, '-S', "127.0.0.1:$puerto", '-t', MICRO_HIS . '/public'], [['pipe', 'r'], ['file', $log, 'w'], ['file', $log, 'a']],
                   $tubos, null, ['CITAS_DSN' => $dsn] + getenv());
    for ($i = 0; $i < 50 && !@fsockopen('127.0.0.1', $puerto); $i++) usleep(100000);
    return [$p, $puerto];
}
function http(int $puerto, string $ruta, string $cuerpo = '', array $cabeceras = [], string $metodo = 'POST'): string
{
    $ctx = stream_context_create(['http' => ['method' => $metodo, 'content' => $cuerpo, 'ignore_errors' => true,
        'header' => implode("\r\n", ['Content-Type: application/json', ...$cabeceras])]]);
    $b = $GLOBALS['cuerpo'] = (string) file_get_contents("http://127.0.0.1:$puerto$ruta", false, $ctx);
    preg_match('#HTTP/\S+ (\d+)#', $http_response_header[0], $m);
    return trim($m[1] . ' ' . (json_decode($b, true)['error'] ?? ''));
}
// Precondición de la agenda: médicos m1 y m2 vigentes en cardio, m3 inactivo, y una cita vencida (id 1) que sigue programada.
$base = sys_get_temp_dir() . '/s15-citas-' . getmypid() . '.sqlite';
$db = new PDO("sqlite:$base"); $db->exec(file_get_contents(MICRO_HIS . '/database/schema.sql'));
$db->exec("INSERT INTO doctor_specialties VALUES ('m1', 'cardio', 'active'), ('m2', 'cardio', 'active'), ('m3', 'cardio', 'inactive')");
$db->exec("INSERT INTO appointments (doctor, patient, specialty, starts_at, ends_at, reason) VALUES ('m2', 'p9', 'cardio', 1000, 2000, 'vencida')"); $db = null;
[$proceso, $p] = servidor("sqlite:$base");
$t = time() + 86400;   // mañana: el servidor usa su reloj real
$pedir = fn (string $med, string $pac, int $i, int $f, string $esp = 'cardio', array $cab = []) => http($p, '/citas',
    json_encode(['medico' => $med, 'paciente' => $pac, 'especialidad' => $esp, 'inicio' => $i, 'fin' => $f, 'motivo' => 'control']), $cab);

$o = [$pedir('m1', 'p1', $t, $t + 1800), $pedir('m3', 'p2', $t, $t + 1800), $pedir('m2', 'p2', $t, $t + 1800, 'derma'), $pedir('m2', '', $t + 7200, $t + 9000)];
prueba('CN-01', 'equivalencia: vigente; médico inactivo; especialidad que no tiene; paciente vacío (R-04, R-05, RF-01)',
       $o === ['201', '422 E3', '422 E3', '422 E2'], implode(', ', $o));

$o = [$pedir('m2', 'p3', time() - 60, time() + 600), $pedir('m2', 'p3', $t, $t), $pedir('m2', 'p3', $t + 1800, $t + 3600), $pedir('m1', 'p4', $t + 1799, $t + 2400)];
prueba('CN-02', 'límites: inicio pasado y fin = inicio = 422; contigua admite; 1 segundo cruzado = 409 E4 (R-01, R-02)',
       $o === ['422 E2', '422 E2', '201', '409 E4'], implode(', ', $o));

$o = [$pedir('m2', 'p1', $t + 900, $t + 1200), http($p, '/citas/2/cancelacion', '{"motivo":"paciente avisó"}'), $pedir('m2', 'p1', $t + 900, $t + 1200)];
prueba('CN-03', 'cruce del paciente con otro médico = 409 E5; cancelar libera el horario (R-03, R-07)', $o === ['409 E5', '200', '201'], implode(', ', $o));

$o = [$pedir('m1', 'p5', $t + 10800, $t + 12600, 'cardio', ['X-Tenant-ID: hospital-ajeno']), http($p, '/citas/3/cancelacion', '{"motivo":"x"}', ['X-Usuario: m1'])];
prueba('CN-04', 'permisos: otro hospital agenda; el médico m1 cancela una cita de m2 = 403 (RNF-01, CP-04)', $o === ['403', '403'], implode(', ', $o));

$o = [http($p, '/citas/99/confirmacion'), http($p, '/citas', '', [], 'GET'), http($p, '/citas/2/confirmacion'), http($p, '/citas/4/cancelacion', '{}'), http($p, '/citas', 'no es JSON')];
prueba('CN-05', 'errores: cita inexistente, ruta desconocida, confirmar cancelada, cancelar sin motivo y cuerpo que no es JSON',
       $o === ['404 NF', '404', '422 E7', '422 E2', '422 E2'], implode(', ', $o));

$o = http($p, '/citas/1/confirmacion');
prueba('CN-06', 'confirmar una cita cuyo horario ya pasó = 422 (R-01, H-03 de la semana 13)', str_starts_with($o, '422'), $o);
proc_terminate($proceso);

[$proceso, $p] = servidor('sqlite:' . sys_get_temp_dir() . '/s15-no-existe/citas.sqlite');
$o = http($p, '/citas', json_encode(['medico' => 'm1', 'paciente' => 'p1', 'especialidad' => 'cardio', 'inicio' => $t, 'fin' => $t + 1800, 'motivo' => 'control']));
$tecnico = str_contains($cuerpo, 'SQLSTATE');
prueba('CN-07', 'base no disponible al arrancar = 503 E8, sin detalles técnicos', $o === '503 E8' && !$tecnico,
       $o . ($tecnico ? '; el cuerpo muestra el «Fatal error» de PHP con SQLSTATE y la ruta de index.php' : ''));
proc_terminate($proceso);
@unlink($base);

echo "RESULTADO: " . ($total - $fallos) . " de $total pruebas pasan" . ($fallos ? "; $fallos fallan (ver defectos)" : '') . "\n";
exit($fallos === 0 ? 0 : 1);
