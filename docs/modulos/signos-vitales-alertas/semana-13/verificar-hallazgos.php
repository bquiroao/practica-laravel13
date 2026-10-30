<?php
declare(strict_types=1);

// Reproduce cada hallazgo del acta de la semana 13 contra el código real y comprueba que el acta dice la verdad:  php verificar-hallazgos.php
// Un hallazgo «Abierto» debe seguir reproduciéndose; si alguien lo corrige, este script falla hasta que el acta pase a «Cerrado».
use SignosVitales\Aplicacion\{ConsultarTomas, RegistrarToma, Usuario};
use SignosVitales\Persistencia\TomasPdo;
use SignosVitales\Presentacion\{Enrutador, Peticion, TomasControlador};

$modulo = dirname(__DIR__) . '/micro-his-signos-vitales';
require "$modulo/bootstrap.php";
function app(string $modulo): array {
    $pdo = new PDO('sqlite::memory:'); $pdo->exec((string) file_get_contents("$modulo/sql/esquema.sql")); $r = new TomasPdo($pdo);
    $caso = new RegistrarToma($r, $r, fn () => new DateTimeImmutable('2026-10-25 08:00'));
    return [$pdo, new Enrutador(new TomasControlador($caso, new ConsultarTomas($r, $r), ['ana' => new Usuario('enf-ana', 'enfermeria', 'hosp-1')]))];
}
function post(Enrutador $e, array $cuerpo, string $ruta = '/api/v1/signos-vitales/tomas'): array { return $e->atender(new Peticion('POST', $ruta, [], 'ana', json_encode($cuerpo))); }
$adulto = ['paciente_id' => 'pac-adulto', 'episodio_id' => 'epi-1'];
$fuente = fn (string $dir) => implode("\n", array_map('file_get_contents', $dir === 'src' ? glob("$modulo/src/*/*.php") : glob("$modulo/$dir/*.php")));

$hallazgos = [];
[, $e] = app($modulo); [$estado, $cuerpo] = post($e, $adulto + ['mediciones' => ['pas' => 80, 'pad' => 120]]);
$hallazgos['H-01'] = [$estado === 201 && isset($cuerpo['toma_id']), 'PAS 80 / PAD 120 se guarda (201) con 2 alertas'];
[, $e] = app($modulo); [$estado, $cuerpo] = post($e, ['paciente_id' => 'pac-nino', 'episodio_id' => 'epi-2', 'mediciones' => ['fc' => 250]]);
$hallazgos['H-02'] = [$estado === 201 && $cuerpo['clasificaciones'] === ['fc' => 'sin_rango'] && $cuerpo['alertas'] === [], 'niño de 5 años con FC 250 → sin_rango y 0 alertas'];
[$pdo, $e] = app($modulo); post($e, $adulto + ['mediciones' => ['fc' => 80]]); post($e, $adulto + ['mediciones' => ['fc' => 80]]);
$hallazgos['H-03'] = [(int) $pdo->query('SELECT COUNT(*) FROM tomas_signos_vitales')->fetchColumn() === 2, 'la misma petición enviada dos veces deja 2 tomas'];
$hallazgos['H-04'] = [!str_contains($fuente('src/Aplicacion') . $fuente('src/Persistencia') . (string) file_get_contents("$modulo/sql/esquema.sql"), 'reconoc'), 'no existe caso de uso ni columnas para reconocer una alerta (RF-09)'];
$hallazgos['H-05'] = [!str_contains($fuente('src/Aplicacion') . $fuente('src/Persistencia'), "'enviada'"), "ningún código marca una alerta como 'enviada' ni reintenta (RF-07)"];
[, $e] = app($modulo); $avisos = []; set_error_handler(function (int $n, string $m) use (&$avisos) { $avisos[] = $m; return true; });
post($e, ['paciente_id' => ['x'], 'episodio_id' => 'epi-1', 'mediciones' => ['fc' => 80]]); restore_error_handler();
$hallazgos['H-06'] = [in_array('Array to string conversion', $avisos, true), 'paciente_id como arreglo emite el aviso «Array to string conversion»'];
[, $e] = app($modulo); [$estado] = post($e, $adulto + ['mediciones' => ['fc' => 80]], '/api/v1/signos-vitales/tomas/');
$hallazgos['H-07'] = [$estado === 404, 'POST a /tomas/ (con barra final) responde 404'];

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
    'C-03 límites físicos definidos una sola vez' => substr_count($fuente('src'), "'pas' => [40, 300]") === 1,
    'C-11 Dominio sin dependencias de otras capas' => !preg_match('/\bPDO\b|Aplicacion|Persistencia|Presentacion/', $dominio),
    'C-12 sin credenciales en el módulo y SQL preparado' => !preg_match('/password|passwd|secret|api[_-]?key/i', $fuente('src') . $fuente('public')) && str_contains($fuente('src/Persistencia'), 'prepare('),
];
chdir($modulo); exec('php tests/ejecutar.php 2>&1', $sal, $codigo);
$cumple['C-06/C-07 las 9 pruebas del Micro-HIS pasan (transacción, rol y hospital)'] = $codigo === 0 && count(preg_grep('/ PASA /', $sal)) === 9;
foreach ($cumple as $que => $ok) { printf("%-6s %s\n", $ok ? 'PASA' : 'FALLA', $que); $fallas += $ok ? 0 : 1; }

echo $fallas === 0 ? "Resultado: $abiertos hallazgos abiertos reproducidos; el acta y el código coinciden\n" : "Resultado: $fallas FALLA(S)\n";
exit($fallas === 0 ? 0 : 1);
