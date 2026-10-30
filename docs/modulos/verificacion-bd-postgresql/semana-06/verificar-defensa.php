<?php
declare(strict_types=1);

// Semana 6 — Comprueba la defensa del parcial: enlaces, matriz de acceso, hallazgo A-01 y presentación. Solo PHP.
// Uso desde la raíz del repo: php docs/modulos/verificacion-bd-postgresql/semana-06/verificar-defensa.php
$raiz = dirname(__DIR__, 4); $fallas = 0;
function ver(string $id, bool $ok, string $que): void { $GLOBALS['fallas'] += $ok ? 0 : 1; printf("%-6s %-5s %s\n", $id, $ok ? 'PASA' : 'FALLA', $que); }
$doc = (string) @file_get_contents(__DIR__ . '/semana-06-defensa-parcial-1.md');
if ($doc === '') { echo "Falta el documento.\n"; exit(1); }

preg_match_all('/\]\((\.\.\/semana-0[1-4]\/[^)#]+)/', $doc, $l); $enlaces = array_unique($l[1]);
$rotos = array_filter($enlaces, fn ($e) => !is_file(__DIR__ . "/$e"));
ver('DEF-01', count($enlaces) >= 5 && $rotos === [], sprintf('Los %d enlaces a las semanas 1–4 existen (rotos: %s)', count($enlaces), implode(', ', $rotos) ?: 'ninguno'));

$sql = implode("\n", array_map('file_get_contents', array_merge(glob("$raiz/database/migrations/central/*.php") ?: [], glob("$raiz/database/migrations/hospital/*.php") ?: [])));
preg_match_all('/CREATE TABLE (\w+)/', $sql, $t);
preg_match('/## 2\. Matriz de acceso(.*?)Qué falta/s', $doc, $m); preg_match_all('/`(\w+)`/', $m[1] ?? '', $tm); $tm = array_unique($tm[1]);
$faltan = array_diff($tm, $t[1]);
ver('DEF-02', count($tm) >= 20 && $faltan === [], sprintf('Las %d tablas de la matriz de acceso existen en las migraciones (faltan: %s)', count($tm), implode(', ', $faltan) ?: 'ninguna'));

$sinCheck = fn (string $tabla) => preg_match("/CREATE TABLE $tabla \((.*?)\n\s*\);/s", $sql, $c) && str_contains($c[1], 'role_code text NOT NULL') && !preg_match('/role_code\s+(text NOT NULL\s+)?(CONSTRAINT \w+ )?CHECK|CHECK \(role_code/', $c[1]);
ver('DEF-03', $sinCheck('hospital_memberships') && $sinCheck('local_user_refs'), 'A-01: role_code es texto libre, sin CHECK, en hospital_memberships (CENTRAL) y local_user_refs (HOSPITAL)');

$pdf = (string) @file_get_contents(__DIR__ . '/presentacion/semana-06-defensa-verificacion-bd.pdf'); $paginas = preg_match_all('/\/Type\s*\/Page[^s]/', $pdf);
ver('DEF-04', is_file(__DIR__ . '/presentacion/semana-06-defensa-verificacion-bd.pptx') && $paginas >= 6 && $paginas <= 8, "La presentación existe y el PDF tiene $paginas páginas (6 a 8)");

echo $fallas === 0 ? "\nResultado: la defensa coincide con el repositorio.\n" : "\nResultado: $fallas comprobaciones fallan.\n";
exit($fallas ? 1 : 0);
