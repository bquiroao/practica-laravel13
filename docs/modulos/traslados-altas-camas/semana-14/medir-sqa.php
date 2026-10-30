<?php
declare(strict_types=1);

// Mide las 7 métricas del plan SQA (semana 14) sobre el repositorio real:  php medir-sqa.php
// Termina con código 1 si alguna métrica está en ROJO. Si falta un documento de otra semana, esa métrica sale N/D (no se inventa).
$modulo = dirname(__DIR__);
$micro = "$modulo/micro-his-traslados";
$vista = 'VistaJson.php';
$verificadores = ['semana-11/verificar-prototipo.php', 'semana-12/demostrar-cambio.php', 'semana-13/verificar-hallazgos.php'];
$acta = "$modulo/semana-13/semana-13-revision-tecnica-formal.md";
$prefijoRf = 'RF';
$ns = 'Traslados';

$capas = ['Dominio', 'Aplicacion', 'Persistencia', 'Presentacion'];
$archivos = array_merge(...array_map(fn ($c) => glob("$micro/src/$c/*.php"), $capas));
$texto = fn (string $dir) => implode("\n", array_map('file_get_contents', glob("$micro/src/$dir/*.php")));
$semaforo = fn (float $v, float $verde, float $amarillo, bool $mayorEsMejor = true) => $mayorEsMejor ? ($v >= $verde ? 'VERDE' : ($v >= $amarillo ? 'AMARILLO' : 'ROJO')) : ($v <= $verde ? 'VERDE' : ($v <= $amarillo ? 'AMARILLO' : 'ROJO'));
$m = [];

// M-01 pruebas del Micro-HIS que pasan (todas las suites de tests/)
chdir($micro); $sal = []; foreach (glob("$micro/tests/*.php") as $suite) { exec('php ' . escapeshellarg($suite) . ' 2>&1', $sal); } $pasan = count(preg_grep('/ PASA /', $sal)); $total = $pasan + count(preg_grep('/ FALLA /', $sal));
$m['M-01'] = ['Pruebas del Micro-HIS que pasan', "$pasan/$total", $total ? $semaforo(100 * $pasan / $total, 100, 90) : 'ROJO'];

// M-02 trazabilidad: RF citados en la matriz de la semana 12, en la matriz de la semana 15 o en las pruebas
$matrices = array_merge(glob("$modulo/semana-12/*.md"), glob("$modulo/semana-15/*.md"));
$docs = array_merge($matrices, glob("$micro/tests/*.php"));
if ($matrices === []) { $m['M-02'] = ['Requisitos RF-01..RF-10 con evidencia citada', 'sin matrices', 'N/D']; }
else {
    preg_match_all("/\b$prefijoRf-(\d\d)\b/", implode("\n", array_map('file_get_contents', $docs)), $r); $citados = count(array_unique($r[1]));
    $m['M-02'] = ['Requisitos RF-01..RF-10 con evidencia citada', "$citados/10", $semaforo(10 * $citados, 90, 70)];
}

// M-03 hallazgos de severidad Alta abiertos (acta de la semana 13)
if (!is_file($acta)) { $m['M-03'] = ['Hallazgos Alta abiertos', 'sin acta', 'N/D']; }
else { $alta = preg_match_all('/^\| H-\d\d \|.*\| Alta \|.*\| Abierto \|$/m', (string) file_get_contents($acta)); $m['M-03'] = ['Hallazgos Alta abiertos', (string) $alta, $semaforo($alta, 0, 2, false)]; }

// M-04 violaciones de dependencia entre capas (misma regla que ARQ-01)
$mal = (int) preg_match('/Aplicacion|Persistencia|Presentacion|PDO/', $texto('Dominio')) + (int) preg_match("/$ns\\\\Persistencia|\\\\PDO\b/", $texto('Aplicacion'))
     + (int) preg_match('/\b(SELECT|INSERT|UPDATE|DELETE)\b/', $texto('Aplicacion') . $texto('Presentacion'));
$m['M-04'] = ['Violaciones de dependencia entre capas', (string) $mal, $mal === 0 ? 'VERDE' : 'ROJO'];

// M-05 tamaño del archivo fuente más largo
$largo = max(array_map(fn ($f) => count(file($f)), $archivos));
$m['M-05'] = ['Líneas del archivo fuente más largo', (string) $largo, $semaforo($largo, 120, 200, false)];

// M-06 estados HTTP de error de la vista (más el 422 por defecto) que alguna prueba verifica
preg_match('/ESTADOS = \[(.*?)\];/s', (string) file_get_contents("$micro/src/Presentacion/$vista"), $e); preg_match_all('/=> (\d{3})/', $e[1] ?? '', $c);
$estados = array_unique(array_merge($c[1], ['422'])); $pruebas = implode("\n", array_map('file_get_contents', glob("$micro/tests/*.php")));
$cubiertos = count(array_filter($estados, fn ($s) => preg_match("/\b$s\b/", $pruebas)));
$m['M-06'] = ['Estados HTTP de error con prueba', "$cubiertos/" . count($estados), $semaforo(100 * $cubiertos / count($estados), 90, 70)];

// M-07 verificadores semanales (semanas 11 a 13) que terminan en PASA
$hay = array_values(array_filter($verificadores, fn ($v) => is_file("$modulo/$v")));
if ($hay === []) { $m['M-07'] = ['Verificadores semanales en PASA', 'ninguno presente', 'N/D']; }
else { $ok = count(array_filter($hay, function ($v) use ($modulo) { exec('php ' . escapeshellarg("$modulo/$v") . ' 2>&1', $s, $codigo); return $codigo === 0; })); $m['M-07'] = ['Verificadores semanales en PASA', "$ok/" . count($hay), $semaforo(100 * $ok / count($hay), 100, 67)]; }

foreach ($m as $id => [$nombre, $valor, $estado]) { printf("%-5s %-48s %-18s %s\n", $id, $nombre, $valor, $estado); }
$rojos = count(array_filter($m, fn ($x) => $x[2] === 'ROJO'));
echo $rojos === 0 ? "Resultado: ninguna métrica en ROJO\n" : "Resultado: $rojos métrica(s) en ROJO\n";
exit($rojos === 0 ? 0 : 1);
