<?php
declare(strict_types=1);

// Semana 14 — Comprueba en el repositorio lo que el plan SQA da por medido: down() completos, credenciales por entorno y métricas completas. Solo PHP.
// Uso desde la raíz del repo: php docs/modulos/verificacion-bd-postgresql/semana-14/verificar-plan.php
$raiz = dirname(__DIR__, 4); $fallas = 0;
function ver(string $id, bool $ok, string $que): void { $GLOBALS['fallas'] += $ok ? 0 : 1; printf("%-6s %-5s %s\n", $id, $ok ? 'PASA' : 'FALLA', $que); }
$plan = (string) @file_get_contents(__DIR__ . '/semana-14-plan-sqa-bd.md');
$migs = array_merge(glob("$raiz/database/migrations/central/*.php") ?: [], glob("$raiz/database/migrations/hospital/*.php") ?: []);
$completas = 0; $borranDatos = 0;
foreach ($migs as $f) {
    $s = (string) file_get_contents($f); $partes = explode('function down', $s, 2);
    preg_match_all('/CREATE TABLE (\w+)/', $partes[0], $c); preg_match_all('/DROP TABLE(?: IF EXISTS)? ([\w, ]+)/', $partes[1] ?? '', $d);
    $quita = array_map('trim', explode(',', implode(',', $d[1])));
    $completas += (isset($partes[1]) && array_diff($c[1], $quita) === []) ? 1 : 0; $borranDatos += $c[1] !== [] ? 1 : 0;
}
ver('SQA-01', count($migs) === 11 && $completas === 11 && str_contains($plan, '11 de 11'), sprintf('M3: %d de %d migraciones tienen un down() que quita todas las tablas que crea su up()', $completas, count($migs)));
ver('SQA-02', $borranDatos === 10, "R1: $borranDatos migraciones borran tablas al revertir (DROP TABLE): un rollback con datos los pierde");
$cfg = (string) @file_get_contents("$raiz/config/database.php");
$credenciales = preg_match_all("/'(password|username|host|database)' => env\('(CENTRAL|HOSPITAL)_DB_/", $cfg);
ver('SQA-03', $credenciales === 8 && !preg_match("/'password' => '[^']+'/", $cfg), "R5: las conexiones central y hospital toman host, base, usuario y contraseña del entorno ($credenciales de 8)");
$sql = implode("\n", array_map('file_get_contents', $migs));
ver('SQA-04', !preg_match('/\b(GRANT|REVOKE)\b/', $sql), 'R3: ninguna migración da ni quita permisos: el aislamiento entre hospitales depende del despliegue (control C3)');
preg_match_all('/^\| M\d \| [^|]+ \| [^|]+ \| [^|]+ \| [^|]+ \| [^|]+ \|$/m', $plan, $m);
preg_match_all('/^\| R\d \|/m', $plan, $r); preg_match_all('/^\| C\d \| (Preventivo|Detectivo) \|/m', $plan, $c);
ver('SQA-05', count($m[0]) >= 5 && count($m[0]) <= 7 && count($r[0]) >= 5 && count(array_unique($c[1])) === 2, sprintf('El plan tiene %d métricas con umbral, fuente, responsable y acción; %d riesgos; controles preventivos y detectivos', count($m[0]), count($r[0])));
echo $fallas === 0 ? "\nResultado: el plan coincide con el repositorio.\n" : "\nResultado: $fallas comprobaciones fallan.\n";
exit($fallas ? 1 : 0);
