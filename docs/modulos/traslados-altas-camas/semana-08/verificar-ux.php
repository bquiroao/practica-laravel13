<?php
declare(strict_types=1);

// Semana 8 — Comprueba que el diseño de UX coincide con el código del Micro-HIS (sin ejecutarlo) y que los entregables existen.
// Uso desde la raíz del repo: php docs/modulos/traslados-altas-camas/semana-08/verificar-ux.php
$micro = dirname(__DIR__) . '/micro-his-traslados'; $fallas = 0;
function ver(string $id, bool $ok, string $que): void { $GLOBALS['fallas'] += $ok ? 0 : 1; printf("%-6s %-5s %s\n", $id, $ok ? 'PASA' : 'FALLA', $que); }
$leer = fn (string $r) => (string) @file_get_contents($r);
$doc = $leer(__DIR__ . '/semana-08-diseno-ux.md'); $html = $leer(__DIR__ . '/wireframes/wireframes.html');
$codigo = implode("\n", array_map($leer, glob("$micro/src/*/*.php")));
if ($doc === '' || $codigo === '') { echo "Falta el documento o el código del Micro-HIS.\n"; exit(1); }
$tabla = fn (string $re) => (preg_match_all($re, $doc, $m, PREG_SET_ORDER) ? $m : []);

// Códigos de error y estados HTTP
preg_match('/ESTADOS = \[(.*?)\];/s', $codigo, $e); preg_match_all("/'(\w+)' => (\d{3})/", $e[1] ?? '', $est);
$http = array_combine($est[1], array_map('intval', $est[2]));
preg_match_all("/Resultado::rechazado\('(\w+)'\)/", $codigo, $rz); preg_match_all("/new ErrorDominio\('(\w+)'/", $codigo, $ed);
$emitidos = array_values(array_unique(array_merge(array_keys($http), $rz[1], $ed[1]))); foreach ($emitidos as $c) { $http[$c] ??= 422; }
$filas = $tabla('/^\| `(\w+)` \| (\d{3}) \| (.{10,}?) \| (.+?) \|$/m'); $delDoc = array_column(array_map(fn ($x) => [$x[1], (int) $x[2]], $filas), 1, 0);
ksort($http); ksort($delDoc);
ver('UX-01', $emitidos !== [] && $http === $delDoc, sprintf('Los %d códigos de error del servidor tienen mensaje, acción y el mismo estado HTTP en el diseño; no hay otros (distintos: %s)', count($emitidos), implode(', ', array_keys(array_merge(array_diff_assoc($http, $delDoc), array_diff_assoc($delDoc, $http)))) ?: 'ninguno'));

// Roles
preg_match_all("/in_array\(\\\$usuario->rol, \[([^\]]*)\]/", $codigo, $r); $reg = array_map(fn ($x) => trim($x, " '"), explode(',', $r[1][0] ?? '')); $con = array_map(fn ($x) => trim($x, " '"), explode(',', $r[1][1] ?? ''));
$fila = fn (string $accion) => (preg_match('/^\| ' . preg_quote($accion, '/') . '[^|]*\| ([^|\s]+) \| ([^|\s]+) \| ([^|\s]+) \|/m', $doc, $m) ? ['enfermeria' => $m[1] === 'Sí', 'admision' => $m[2] === 'Sí', 'medico' => $m[3] === 'Sí'] : []);
$permitidos = fn (array $f) => array_keys(array_filter($f));
$a = $permitidos($fila('Registrar traslado o alta')); $b = $permitidos($fila('Consultar el historial'));
sort($a); sort($b); sort($reg); sort($con);
$conCodigo = isset($r[1][1]);   // la consulta del historial todavía es propuesta: se compara solo cuando exista en el código
ver('UX-02', $reg === $a && (!$conCodigo || $con === $b) && $a !== [] && !in_array('medico', $a, true), sprintf('La matriz de roles coincide con el código: registran %s; consultan %s%s', implode(', ', $a), implode(', ', $b), $conCodigo ? '' : ' (consulta propuesta, aún sin código)'));

// Opciones: tipos de alta, requisitos y estados de cama
preg_match('/enum TipoAlta: string \{(.*?)\}/s', $codigo, $t); preg_match_all("/= '(\w+)'/", $t[1] ?? '', $ta);
$altas = $tabla('/^\| (\S+) \| `(\w+)` \| .+ \|$/m'); $etiq = array_column($altas, 1, 2);
$faltaAlta = array_filter($ta[1], fn ($v) => !isset($etiq[$v]) || !str_contains($html, $etiq[$v]));
ver('UX-03', count($ta[1]) === 4 && $faltaAlta === [], sprintf('Los 4 tipos de alta del código están en el diseño y se ven en los wireframes%s', $faltaAlta ? ' (falta: ' . implode(', ', $faltaAlta) . ')' : ''));
preg_match('/enum RequisitoCama: string \{(.*?)\}/s', $codigo, $q); preg_match_all("/= '(\w+)'/", $q[1] ?? '', $rq);
$camaPedida = array_column($tabla('/^\| (Cualquiera|UCI) \| `(\w+)` \|$/m'), 2); sort($camaPedida); $rqs = $rq[1]; sort($rqs);
ver('UX-04', $rqs === $camaPedida && count($rqs) === 2 && str_contains($html, '>Cualquiera<') && str_contains($html, '>UCI<'), 'Los requisitos de cama del código (cualquiera, uci) están en el diseño y en los wireframes');
$estados = $tabla('/^\| ([\wé ]+) \| `(\w+)` \| (Sí|No) \|$/m'); $elegibles = array_column(array_filter($estados, fn ($x) => $x[3] === 'Sí'), 2);
$esq = $leer("$micro/sql/esquema.sql"); preg_match("/status IN \(([^)]*)\)\)\);\n-- Estancia/", $esq, $s); preg_match_all("/'(\w+)'/", $s[1] ?? '', $es); $delEsq = $es[1]; $delDocE = array_column($estados, 2); sort($delEsq); sort($delDocE);
ver('UX-05', $delEsq === $delDocE && $elegibles === ['available'] && str_contains($codigo, "\$destino->estado !== 'available'") && str_contains($html, 'Disponible') && str_contains($html, 'En mantenimiento'), 'Los 4 estados de cama son los de la base; solo «available» se puede elegir, como en el Dominio, y los wireframes lo muestran');

// Entregables
$wf = array_filter(['wf1', 'wf2', 'wf3', 'wf4', 'wf5', 'wf6'], fn ($w) => str_contains($html, "id=\"$w\"") && is_file(__DIR__ . "/wireframes/semana-08-$w.png"));
$estadosDoc = $tabla('/^\| (W\d) [^|]+\| .+ \| .+ \| .+ \| .+ \|$/m');
ver('UX-06', count($wf) === 6 && is_file(__DIR__ . '/diagramas/semana-08-flujo-por-rol.png') && is_file(__DIR__ . '/diagramas/semana-08-flujo-por-rol.puml') && count($estadosDoc) === 6, sprintf('Existen los %d wireframes con su imagen, el flujo por rol y la tabla de estados de las 6 pantallas', count($wf)));
echo $fallas === 0 ? "\nResultado: el diseño coincide con el código.\n" : "\nResultado: $fallas comprobaciones fallan.\n";
exit($fallas ? 1 : 0);
