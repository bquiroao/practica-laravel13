<?php
declare(strict_types=1);

// Semana 12 — Vuelve a contar en las migraciones lo que afirma la defensa del parcial 2 y revisa la presentación. Solo PHP.
// Uso desde la raíz del repo: php docs/modulos/verificacion-bd-postgresql/semana-12/verificar-defensa.php
$raiz = dirname(__DIR__, 4); $fallas = 0;
function ver(string $id, bool $ok, string $que): void { $GLOBALS['fallas'] += $ok ? 0 : 1; printf("%-6s %-5s %s\n", $id, $ok ? 'PASA' : 'FALLA', $que); }
$doc = (string) @file_get_contents(__DIR__ . '/semana-12-defensa-parcial-2.md');
$sql = implode("\n", array_map('file_get_contents', array_merge(glob("$raiz/database/migrations/central/*.php") ?: [], glob("$raiz/database/migrations/hospital/*.php") ?: [])));
preg_match_all('/CREATE TABLE (\w+) \((.*?)(?=CREATE (?:TABLE|INDEX|UNIQUE|OR|TRIGGER|FUNCTION)|SQL\))/s', $sql, $t, PREG_SET_ORDER);
$conPk = count(array_filter($t, fn ($x) => str_contains($x[2], 'PRIMARY KEY'))); $fk = substr_count($sql, 'REFERENCES'); $restrict = substr_count($sql, 'ON DELETE RESTRICT');
ver('P2-01', count($t) === 31 && $conPk === 31 && $fk === 57 && $restrict === 57 && str_contains($doc, '31 tablas') && str_contains($doc, '57 FK'), sprintf('%d tablas, %d con PK; %d FK, %d con ON DELETE RESTRICT (la defensa dice 31 y 57)', count($t), $conPk, $fk, $restrict));
$unicas = preg_match_all('/CONSTRAINT \w+ (UNIQUE|EXCLUDE)|CREATE UNIQUE INDEX \w+/', $sql);
ver('P2-02', $unicas === 33 && str_contains($doc, '33 restricciones'), "$unicas restricciones únicas, de exclusión e índices únicos (la defensa dice 33 → 409)");
$seeder = (string) @file_get_contents("$raiz/database/seeders/DatabaseSeeder.php");
ver('P2-03', $seeder !== '' && !preg_match('/->call\(|insert|DB::|::create\(/i', $seeder), 'H-13: DatabaseSeeder no inserta nada');
ver('P2-04', str_contains($sql, 'ON vital_signs (care_episode_id);') && str_contains($sql, 'ON appointments (doctor_profile_id);') && !str_contains($sql, 'vital_signs_episode_measured_index'), 'S11: C3 y C4 todavía solo tienen índices de una columna en develop');
$pdf = (string) @file_get_contents(__DIR__ . '/presentacion/semana-12-defensa-verificacion-bd.pdf'); $paginas = preg_match_all('/\/Type\s*\/Page[^s]/', $pdf);
ver('P2-05', is_file(__DIR__ . '/presentacion/semana-12-defensa-verificacion-bd.pptx') && $paginas >= 6 && $paginas <= 8, "La presentación existe y el PDF tiene $paginas páginas (6 a 8)");
echo $fallas === 0 ? "\nResultado: la defensa coincide con el repositorio.\n" : "\nResultado: $fallas comprobaciones fallan.\n";
exit($fallas ? 1 : 0);
