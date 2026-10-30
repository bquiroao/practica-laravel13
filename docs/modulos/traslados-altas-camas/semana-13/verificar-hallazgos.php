<?php
declare(strict_types=1);

// Reproduce cada hallazgo del acta de la semana 13 contra el código real y comprueba que el acta dice la verdad:  php verificar-hallazgos.php
// Un hallazgo «Abierto» debe seguir reproduciéndose; si alguien lo corrige, este script falla hasta que el acta pase a «Cerrado».
use Traslados\Aplicacion\{RegistrarMovimiento, Usuario};
use Traslados\Dominio\Resultado;
use Traslados\Persistencia\MovimientosPdo;
use Traslados\Presentacion\{Enrutador, MovimientosControlador, Peticion};

$modulo = dirname(__DIR__) . '/micro-his-traslados';
require "$modulo/bootstrap.php";
function nuevo(string $modulo): array {
    $pdo = new PDO('sqlite::memory:'); $pdo->exec((string) file_get_contents("$modulo/sql/esquema.sql")); $r = new MovimientosPdo($pdo);
    $caso = new RegistrarMovimiento($r, $r, fn () => new DateTimeImmutable('2026-10-25 12:00'), fn () => 'estancia-nueva');
    return [$pdo, $r, new Enrutador(new MovimientosControlador($caso, ['ana' => new Usuario('enf-ana', 'enfermeria')]))];
}
function post(Enrutador $e, array $cuerpo, string $ruta = '/api/v1/traslados-altas/movimientos', string $metodo = 'POST'): array { return $e->atender(new Peticion($metodo, $ruta, [], 'ana', json_encode($cuerpo))); }
$traslado = ['episodio_id' => 'epi-1', 'tipo' => 'traslado', 'destino_id' => 'bed-a2'];
$abiertas = fn (PDO $pdo, string $cama) => (int) $pdo->query("SELECT COUNT(*) FROM bed_stays WHERE bed_id = '$cama' AND ended_at IS NULL")->fetchColumn();
$fuente = fn (string $dir) => implode("\n", array_map('file_get_contents', $dir === 'src' ? glob("$modulo/src/*/*.php") : glob("$modulo/$dir/*.php")));

$hallazgos = [];
[$pdo, , $e] = nuevo($modulo); $pdo->exec("INSERT INTO bed_stays VALUES ('stay-x', 'epi-9', 'bed-a2', '2026-10-23 08:00', NULL, NULL)");   // a2 figura «available» pero ya tiene un paciente
[$estado] = post($e, $traslado);
$hallazgos['H-01'] = [$estado === 201 && $abiertas($pdo, 'bed-a2') === 2, 'cama «available» con un paciente adentro se acepta como destino: 201 y 2 estancias abiertas en A2'];
[$pdo, $repo] = nuevo($modulo); $repo->aplicar('epi-1', 'ord-1', 'traslado', Resultado::aplicado('bed-a1', 'bed-a3'), 'stay-1', 'estancia-nueva', new DateTimeImmutable('2026-10-25 12:00'));
$hallazgos['H-02'] = [$abiertas($pdo, 'bed-a3') === 2, 'el Repository aplica un traslado sobre una cama ocupada sin volver a comprobarla: 2 estancias abiertas en A3'];
[, , $e] = nuevo($modulo); [$estado, $c] = post($e, ['episodio_id' => 'epi-1', 'tipo' => 'otro']);
$hallazgos['H-03'] = [$estado === 409 && $c['codigo'] === 'orden_requerida', "tipo='otro' responde 409 orden_requerida en lugar de un error de validación"];
[$pdo, , $e] = nuevo($modulo); $pdo->exec("UPDATE ordenes_movimiento SET tipo_alta = 'fuga' WHERE orden_id = 'ord-2'");
try { post($e, ['episodio_id' => 'epi-2', 'tipo' => 'alta']); $excepcion = false; } catch (\Throwable) { $excepcion = true; }
$hallazgos['H-05'] = [$excepcion, "una orden con tipo_alta='fuga' (el esquema no lo impide) lanza una excepción que nadie captura"];
[, , $e] = nuevo($modulo); [$estado] = post($e, [], '/api/v1/traslados-altas/movimientos', 'GET');
$hallazgos['H-04'] = [$estado === 405 && substr_count($fuente('src/Presentacion'), "=> ['POST' =>") === 1, 'solo existe POST /movimientos: no hay historial de camas, aviso a limpieza ni salida temporal (RF-06, RF-07, RF-08)'];
[$pdo, , $e] = nuevo($modulo); post($e, $traslado);
$hallazgos['H-06'] = [(bool) preg_match('/T/', (string) $pdo->query("SELECT ended_at FROM bed_stays WHERE bed_stay_id = 'stay-1'")->fetchColumn()) && !str_contains((string) $pdo->query("SELECT started_at FROM bed_stays WHERE bed_stay_id = 'stay-1'")->fetchColumn(), 'T'), 'las fechas se guardan en dos formatos: «2026-10-20 08:00» (datos) y «2026-10-25T12:00:00+00:00» (movimientos)'];
[, , $e] = nuevo($modulo); $avisos = []; set_error_handler(function (int $n, string $m) use (&$avisos) { $avisos[] = $m; return true; });
post($e, ['episodio_id' => ['x'], 'tipo' => 'alta']); restore_error_handler();
$hallazgos['H-07'] = [in_array('Array to string conversion', $avisos, true), 'episodio_id como arreglo emite el aviso «Array to string conversion»'];

ksort($hallazgos);
$acta = (string) file_get_contents(__DIR__ . '/semana-13-revision-tecnica-formal.md');
preg_match_all('/^\| (H-\d\d) \|.*\| (Abierto|Cerrado) \|$/m', $acta, $f, PREG_SET_ORDER);
$estadoActa = array_column($f, 2, 1);
$fallas = 0; $abiertos = 0;
foreach ($hallazgos as $id => [$reproduce, $que]) {
    $esperado = $estadoActa[$id] ?? 'NO ESTÁ EN EL ACTA';
    $ok = $esperado === 'Abierto' && $reproduce || $esperado === 'Cerrado' && !$reproduce;
    $abiertos += $reproduce ? 1 : 0; $fallas += $ok ? 0 : 1;
    printf("%-6s %s %s [acta: %s]%s\n", $ok ? 'PASA' : 'FALLA', $id, $que, $esperado, $ok ? '' : ($reproduce ? ' → se reproduce pero el acta no lo da por abierto' : ' → ya no se reproduce: pase el acta a Cerrado'));
}
$sinActa = array_diff(array_keys($estadoActa), array_keys($hallazgos)); $fallas += count($sinActa);
if ($sinActa) { echo 'FALLA hallazgos del acta sin reproducción: ' . implode(', ', $sinActa) . "\n"; }

// Checklist: 14 ítems; cada uno marcado ✖ cita un hallazgo del acta y cada hallazgo se cita en algún ítem.
preg_match_all('/^\| (C-\d\d) \| .* \| (✔|✖)(?: (H-\d\d))? \|$/mu', $acta, $c, PREG_SET_ORDER);
$citados = array_filter(array_column($c, 3)); $noCumple = array_filter($c, fn ($x) => $x[2] === '✖');
$okLista = count($c) === 14 && count($noCumple) === count($citados) && (fn ($a, $b) => sort($a) && sort($b) && $a === $b)(array_unique($citados), array_keys($hallazgos));
printf("%-6s Checklist: %d ítems, %d no cumplen y citan %d hallazgos distintos\n", $okLista ? 'PASA' : 'FALLA', count($c), count($noCumple), count(array_unique($citados))); $fallas += $okLista ? 0 : 1;

// Ítems que el acta da por cumplidos y se pueden comprobar con el código.
$dominio = $fuente('src/Dominio');
$cumple = [
    'C-10 el mapa código → HTTP está en un solo lugar' => substr_count($fuente('src'), 'ESTADOS = [') === 1,
    'C-14 los 5 códigos de rechazo del RF-09 existen en la vista y tienen prueba' => count(array_filter(['sin_cama', 'cama_no_disponible', 'cama_no_cumple_requisito', 'alta_invalida', 'orden_requerida'], fn ($k) => str_contains($fuente('src/Presentacion'), "'$k'") && str_contains((string) file_get_contents("$modulo/tests/ejecutar.php"), $k))) === 5,
    'C-11 Dominio sin dependencias de otras capas' => !preg_match('/\bPDO\b|Aplicacion|Persistencia|Presentacion/', $dominio),
    'C-12 sin credenciales en el módulo y SQL preparado' => !preg_match('/password|passwd|secret|api[_-]?key/i', $fuente('src') . $fuente('public')) && str_contains($fuente('src/Persistencia'), 'prepare('),
];
chdir($modulo); exec('php tests/ejecutar.php 2>&1', $sal, $codigo);
$cumple['C-07/C-08 las 10 pruebas del Micro-HIS pasan (rollback, rol y guardas)'] = $codigo === 0 && count(preg_grep('/ PASA /', $sal)) === 10;
foreach ($cumple as $que => $ok) { printf("%-6s %s\n", $ok ? 'PASA' : 'FALLA', $que); $fallas += $ok ? 0 : 1; }

echo $fallas === 0 ? "Resultado: $abiertos hallazgos abiertos reproducidos; el acta y el código coinciden\n" : "Resultado: $fallas FALLA(S)\n";
exit($fallas === 0 ? 0 : 1);
