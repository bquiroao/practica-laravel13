<?php
declare(strict_types=1);

// Reproduce cada hallazgo del acta de la semana 13 contra el código real y comprueba que el acta dice la verdad:  php verificar-hallazgos.php
// Un hallazgo «Abierto» debe seguir reproduciéndose; si alguien lo corrige, este script falla hasta que el acta pase a «Cerrado».
use Reportes\Aplicacion\{ExportarReporte, Usuario};
use Reportes\Dominio\Csv;
use Reportes\Persistencia\ReportesPdo;
use Reportes\Presentacion\{Enrutador, Peticion, ReportesControlador};
use function Reportes\Dominio\catalogo;

$modulo = dirname(__DIR__) . '/micro-his-reportes';
require "$modulo/bootstrap.php";
function app(string $modulo): Enrutador {
    $pdo = new PDO('sqlite::memory:'); $pdo->exec((string) file_get_contents("$modulo/sql/esquema.sql")); $r = new ReportesPdo($pdo);
    return new Enrutador(new ReportesControlador(new ExportarReporte($r, $r, catalogo(), ['csv' => new Csv()], fn () => '2026-10-25 12:00:00'), ['jefa' => new Usuario('usr-jefa', 'jefatura', 'hosp-1', true)]));
}
function pedir(string $ruta, array $q = []): array { return app($GLOBALS['modulo'])->atender(new Peticion('GET', $ruta, $q, 'jefa')); }
$exportar = '/api/v1/reportes/censo/exportar'; $rango = ['desde' => '2026-10-01', 'hasta' => '2026-10-25'];
$fuente = fn (string $dir) => implode("\n", array_map('file_get_contents', $dir === 'src' ? glob("$modulo/src/*/*.php") : glob("$modulo/$dir/*.php")));

$hallazgos = [];
try { pedir($exportar, ['desde' => '2026-13-45', 'hasta' => '2026-13-46']); $excepcion = false; } catch (\Throwable) { $excepcion = true; }
$hallazgos['H-01'] = [$excepcion, 'desde=2026-13-45 lanza una excepción que nadie captura (no hay respuesta 422)'];
$celda = (new Csv())->generar(['paciente'], [['=HYPERLINK("http://ejemplo.invalid","clic")'], ['+1+1'], ['@SUM(1)']]);
$hallazgos['H-02'] = [count(array_filter(array_slice(explode("\n", $celda), 1), fn ($l) => preg_match('/^[=+@\-]/', (string) str_getcsv($l, ',', '"', '')[0]))) === 3, 'una celda que empieza con = + - @ sale sin neutralizar en el CSV'];
[$estado] = pedir($exportar, ['desde' => '2026-02-31', 'hasta' => '2026-03-02']);
$hallazgos['H-03'] = [$estado === 200, 'desde=2026-02-31 (fecha que no existe) responde 200'];
$hallazgos['H-04'] = [pedir('/api/v1/reportes')[0] === 404 && pedir('/api/v1/reportes/censo')[0] === 404, 'no hay ruta del catálogo ni de la vista previa: ambas responden 404 (RF-01, RF-04)'];
$hallazgos['H-05'] = [pedir($exportar, $rango + ['formato' => 'pdf'])[1]['codigo'] === 'formato_no_soportado' && pedir($exportar, $rango + ['formato' => 'json'])[0] === 400, 'solo existe CSV: formato=pdf y formato=json responden 400'];
$avisos = []; set_error_handler(function (int $n, string $m) use (&$avisos) { $avisos[] = $m; return true; });
pedir($exportar, ['desde' => ['x'], 'hasta' => '2026-10-25']); restore_error_handler();
$hallazgos['H-06'] = [in_array('Array to string conversion', $avisos, true), 'desde como arreglo emite el aviso «Array to string conversion»'];
[$estado] = pedir($exportar, ['desde' => '2026-07-25', 'hasta' => '2026-10-25']);
$hallazgos['H-07'] = [$estado === 200, '2026-07-25 a 2026-10-25 (93 días calendario) se acepta aunque RF-02 dice máximo 92'];

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
    'C-03 el hash y los filtros se calculan en un solo lugar' => substr_count($fuente('src'), "hash('sha256'") === 1 && substr_count($fuente('src'), 'function desde(') === 1,
    'C-11 Dominio sin dependencias de otras capas' => !preg_match('/\bPDO\b|Aplicacion|Persistencia|Presentacion/', $dominio),
    'C-12 SQL preparado, sin credenciales y con identificadores validados' => !preg_match('/password|passwd|secret|api[_-]?key/i', $fuente('src') . $fuente('public')) && str_contains($fuente('src/Persistencia'), 'prepare(') && str_contains($fuente('src/Persistencia'), 'Identificador no permitido'),
];
chdir($modulo); exec('php tests/ejecutar.php 2>&1', $sal, $codigo);
$cumple['C-07/C-08 las 9 pruebas del Micro-HIS pasan (registro antes de entregar, aislamiento y enmascarado)'] = $codigo === 0 && count(preg_grep('/ PASA /', $sal)) === 9;
foreach ($cumple as $que => $ok) { printf("%-6s %s\n", $ok ? 'PASA' : 'FALLA', $que); $fallas += $ok ? 0 : 1; }

echo $fallas === 0 ? "Resultado: $abiertos hallazgos abiertos reproducidos; el acta y el código coinciden\n" : "Resultado: $fallas FALLA(S)\n";
exit($fallas === 0 ? 0 : 1);
