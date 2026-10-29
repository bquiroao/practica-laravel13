<?php
declare(strict_types=1);

// Semana 8 — Comprueba que el diseño de UX coincide con el código del Micro-HIS y que los entregables existen.
// Uso desde la raíz del repo: php docs/modulos/reportes-operativos-exportables/semana-08/verificar-ux.php
use function Reportes\Dominio\catalogo;

$micro = dirname(__DIR__) . '/micro-his-reportes'; $fallas = 0;
function ver(string $id, bool $ok, string $que): void { $GLOBALS['fallas'] += $ok ? 0 : 1; printf("%-6s %-5s %s\n", $id, $ok ? 'PASA' : 'FALLA', $que); }
$leer = fn (string $r) => (string) @file_get_contents($r);
$doc = $leer(__DIR__ . '/semana-08-diseno-ux.md'); $html = $leer(__DIR__ . '/wireframes/wireframes.html');
$codigo = implode("\n", array_map($leer, glob("$micro/src/*/*.php")));
if ($doc === '' || $codigo === '') { echo "Falta el documento o el código del Micro-HIS.\n"; exit(1); }
require "$micro/bootstrap.php";
$tabla = fn (string $re) => (preg_match_all($re, $doc, $m, PREG_SET_ORDER) ? $m : []);

// Códigos de error y estados HTTP
preg_match('/ESTADOS = \[(.*?)\];/s', $codigo, $e); preg_match_all("/'(\w+)' => (\d{3})/", $e[1] ?? '', $est);
$http = array_combine($est[1], array_map('intval', $est[2])); preg_match_all("/new ErrorDominio\('(\w+)'/", $codigo, $ed);
$emitidos = array_values(array_unique(array_merge(array_keys($http), $ed[1]))); foreach ($emitidos as $c) { $http[$c] ??= 422; }
$filas = $tabla('/^\| `(\w+)` \| (\d{3}) \| (.{10,}?) \| (.+?) \|$/m'); $delDoc = array_column(array_map(fn ($x) => [$x[1], (int) $x[2]], $filas), 1, 0);
ksort($http); ksort($delDoc);
ver('UX-01', $emitidos !== [] && $http === $delDoc, sprintf('Los %d códigos de error del servidor tienen mensaje, acción y el mismo estado HTTP en el diseño; no hay otros (distintos: %s)', count($emitidos), implode(', ', array_keys(array_merge(array_diff_assoc($http, $delDoc), array_diff_assoc($delDoc, $http)))) ?: 'ninguno'));

// Roles que pueden exportar
preg_match("/in_array\(\\\$usuario->rol, \[([^\]]*)\]/", $codigo, $r); $roles = array_map(fn ($x) => trim($x, " '"), explode(',', $r[1] ?? '')); sort($roles);
preg_match('/^\| Ver y exportar un reporte \| ([^|\s]+) \| ([^|\s]+) \| ([^|\s]+) \|/m', $doc, $f);
$delDocRoles = array_keys(array_filter(['jefatura' => ($f[1] ?? '') === 'Sí', 'administracion' => ($f[2] ?? '') === 'Sí', 'auditor' => ($f[3] ?? '') === 'Sí'])); sort($delDocRoles);
ver('UX-02', $roles !== [] && $roles === $delDocRoles, sprintf('Los roles que pueden exportar en el diseño son los del código: %s', implode(', ', $roles)));

// Reportes, columnas (con dato personal) y filtros
$del = []; foreach ($tabla('/^\| [^|]+ \| `(\w+)` \| ([^|]+) \| `(\w+)`: ([^|]+) \|$/m') as $x) {
    $cols = []; foreach (explode(',', $x[2]) as $c) { $c = trim($c); $cols[rtrim($c, '*')] = str_ends_with($c, '*'); }
    $del[$x[1]] = [$cols, [$x[3] => array_map('trim', explode(',', $x[4]))]];
}
$real = []; foreach (catalogo() as $c => $d) { $real[$c] = [$d->columnas, $d->filtros]; }
ksort($del); ksort($real);
ver('UX-03', $del === $real && count($real) === 2, sprintf('Los %d reportes del diseño tienen las columnas, los datos personales y los filtros del catálogo del servidor', count($real)));

// Reglas de filtros
preg_match('/->days > (\d+)/', $codigo, $d); preg_match('/Máximo (\d+) días/', $codigo, $d2);
ver('UX-04', isset($d[1]) && $d[1] === ($d2[1] ?? '') && str_contains($doc, "Máximo {$d[1]} días") && str_contains($html, "máximo {$d[1]} días") && str_contains($codigo, 'fechas futuras'), sprintf('El límite de %s días y la regla de fechas futuras del servidor se anuncian en el diseño y en W1', $d[1] ?? '?'));

// Formatos
$index = $leer("$micro/public/index.php"); preg_match('/catalogo\(\), \[([^\]]*)\]/', $index, $ff); preg_match_all("/'(\w+)' => new /", $ff[1] ?? '', $fm);
ver('UX-05', $fm[1] === ['csv'] && str_contains($doc, 'solo existe **CSV**') && str_contains($html, 'Exportar CSV') && str_contains($html, 'PDF (no disponible)'), 'El servidor exporta solo CSV; el diseño lo dice y muestra PDF desactivado con su razón');

// Entregables
$wf = array_filter(['wf1', 'wf2', 'wf3', 'wf4', 'wf5', 'wf6'], fn ($w) => str_contains($html, "id=\"$w\"") && is_file(__DIR__ . "/wireframes/semana-08-$w.png"));
$estados = $tabla('/^\| (W\d) [^|]+\| .+ \| .+ \| .+ \| .+ \|$/m');
ver('UX-06', count($wf) === 6 && is_file(__DIR__ . '/diagramas/semana-08-flujo-por-rol.png') && is_file(__DIR__ . '/diagramas/semana-08-flujo-por-rol.puml') && count($estados) === 6, sprintf('Existen los %d wireframes con su imagen, el flujo por rol y la tabla de estados de las 6 pantallas', count($wf)));
echo $fallas === 0 ? "\nResultado: el diseño coincide con el código.\n" : "\nResultado: $fallas comprobaciones fallan.\n";
exit($fallas ? 1 : 0);
