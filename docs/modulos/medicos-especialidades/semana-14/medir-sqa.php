<?php
declare(strict_types=1);

// Mide las 7 métricas del plan SQA de Médicos (semana 14) y las compara con su meta.
//   php semana-14/medir-sqa.php [carpeta-del-módulo]      (por omisión: la carpeta que contiene a semana-14)
// Sale con 0 si todas están en VERDE y con 1 si alguna está en ROJO. Una métrica sin datos sale N/D.
$mod = realpath($argv[1] ?? __DIR__ . '/..') ?: exit("No encuentro la carpeta del módulo\n");
$mh = "$mod/micro-his-medicos";
function correr(string $archivo): ?string {
    if (!is_file($archivo)) { return null; }
    $p = proc_open([PHP_BINARY, $archivo], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $tuberias, dirname($archivo));
    $salida = stream_get_contents($tuberias[1]); stream_get_contents($tuberias[2]); proc_close($p);
    return $salida;
}
$codigo = fn (string $capa) => implode("\n", array_map('file_get_contents', glob("$mh/src/$capa/*.php") ?: []));
$lineas = array_sum(array_map(fn ($f) => count(file($f)), array_merge(glob("$mh/src/*/*.php") ?: [], glob("$mh/sql/esquema.sql") ?: [])));
$m = [];   // id => [nombre, valor medido (texto), cumple (true/false/null), meta]

// M1: pruebas del Micro-HIS
$s = correr("$mh/tests/ejecutar.php");
preg_match('/Total: (\d+)\s+Pasan: (\d+)/', (string) $s, $t);
$m['M1'] = ['Pruebas del Micro-HIS que pasan', $t ? "$t[2]/$t[1]" : 'N/D', $t ? $t[1] === $t[2] : null, '100 %'];

// M2: criterios de aceptación (tabla de la semana 2) mencionados por alguna prueba automática
$doc = (string) @file_get_contents("$mod/semana-02/semana-02-isp-requerimientos-diseno.md");
preg_match_all('/^\| (CA-\d\d) \|/m', $doc, $ca); preg_match_all('/CA-\d\d/', (string) @file_get_contents("$mh/tests/ejecutar.php"), $cu);
$cubiertos = count(array_intersect(array_unique($ca[1]), array_unique($cu[0])));
$m['M2'] = ['Criterios de aceptación con prueba automática', $ca[1] ? "$cubiertos/" . count(array_unique($ca[1])) : 'N/D', $ca[1] ? $cubiertos / count(array_unique($ca[1])) >= 0.8 : null, '≥ 80 %'];

// M3 y M4: hallazgos de la revisión técnica de la semana 13
$s = correr("$mod/semana-13/verificar-hallazgos.php");
preg_match_all('/^(RT-\d\d) PRESENTE/m', (string) $s, $rt);
$mayores = count(array_intersect($rt[1], ['RT-01', 'RT-02', 'RT-03']));   // severidad Mayor según semana-13 §6
$m['M3'] = ['Hallazgos Mayores abiertos', $s === null ? 'N/D' : (string) $mayores, $s === null ? null : $mayores === 0, '0'];
$dens = count($rt[1]) / $lineas * 1000;
$m['M4'] = ['Hallazgos abiertos por cada 1000 líneas', $s === null ? 'N/D' : sprintf('%.1f', $dens), $s === null ? null : $dens <= 10, '≤ 10'];

// M5: violaciones de capas (la regla de ARQ-01)
$v = preg_match_all('/Aplicacion|Persistencia|Presentacion|PDO/', $codigo('Dominio')) + preg_match_all('/\b(SELECT|INSERT|UPDATE|DELETE)\b/', $codigo('Aplicacion') . $codigo('Presentacion'));
$m['M5'] = ['Violaciones de capas', (string) $v, $v === 0, '0'];

// M6: contrato OpenAPI
$s = correr("$mod/semana-05/verificar-contrato.php");
preg_match('/Total: (\d+)\s+Pasan: (\d+)/', (string) $s, $t);
$m['M6'] = ['Pruebas del contrato OpenAPI que pasan', $t ? "$t[2]/$t[1]" : 'N/D', $t ? $t[1] === $t[2] : null, '100 %'];

// M7: tamaño de archivos de código
$grandes = array_filter(glob("$mh/src/*/*.php") ?: [], fn ($f) => count(file($f)) > 80);
$m['M7'] = ['Archivos de código de más de 80 líneas', (string) count($grandes), count($grandes) === 0, '0'];

$rojos = 0;
foreach ($m as $id => [$nombre, $valor, $cumple, $meta]) {
    $estado = $cumple === null ? 'N/D' : ($cumple ? 'VERDE' : 'ROJO'); $rojos += $cumple === false;
    printf("%-3s %-5s %s medido %-6s meta %s\n", $id, $estado, $nombre . str_repeat(' ', 50 - mb_strlen($nombre)), $valor, $meta);
}
printf("\nVerde: %d  Rojo: %d  Sin datos: %d  (sobre %d líneas de código y SQL)\n", count(array_filter($m, fn ($x) => $x[2] === true)), $rojos, count(array_filter($m, fn ($x) => $x[2] === null)), $lineas);
exit($rojos === 0 ? 0 : 1);
