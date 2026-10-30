<?php
declare(strict_types=1);

// Semana 5 — Comprueba la matriz endpoint → tablas → operación contra el repositorio. Solo PHP.
// Uso desde la raíz del repo: php docs/modulos/verificacion-bd-postgresql/semana-05/verificar-matriz.php
$raiz = dirname(__DIR__, 4); $fallas = 0;
function ver(string $id, bool $ok, string $que): void { $GLOBALS['fallas'] += $ok ? 0 : 1; printf("%-5s %-5s %s\n", $id, $ok ? 'PASA' : 'FALLA', $que); }
$leer = fn (string $r) => (string) @file_get_contents("$raiz/$r");
$archivos = ['routes/api.php' => 'routes/api.php',
    'notas-soap-diagnosticos/.../Http/Rutas.php' => 'docs/modulos/notas-soap-diagnosticos/micro-his-notas-soap/src/Presentacion/Http/Rutas.php',
    'signos-vitales-alertas/.../Enrutador.php' => 'docs/modulos/signos-vitales-alertas/micro-his-signos-vitales/src/Presentacion/Enrutador.php',
    'traslados-altas-camas/.../Enrutador.php' => 'docs/modulos/traslados-altas-camas/micro-his-traslados/src/Presentacion/Enrutador.php',
    'medicos-especialidades/.../Enrutador.php' => 'docs/modulos/medicos-especialidades/micro-his-medicos/src/Presentacion/Enrutador.php',
    'reportes-operativos-exportables/.../Enrutador.php' => 'docs/modulos/reportes-operativos-exportables/micro-his-reportes/src/Presentacion/Enrutador.php'];

// Filas de la matriz: | `MÉTODO /ruta` | fuente | base | lee | escribe | Tx | motivo |
preg_match_all('/^\| `(GET|POST) (\/api\/v1\/\S+)` \| `([^`]+)` \| [^|]+ \| ([^|]+) \| ([^|]+) \| (Sí|No) \|/m', $leer('docs/modulos/verificacion-bd-postgresql/semana-05/semana-05-matriz-endpoints-tablas.md'), $filas, PREG_SET_ORDER);
$fuente = []; foreach ($filas as $f) { $fuente[$f[3] === 'routes/api.php' ? 'grupo' : 'micro'][] = $f; }
$fuente['grupo'] ??= []; $fuente['micro'] ??= [];

// M-01: rutas de Laravel (prefijo de bootstrap/app.php)
preg_match("/apiPrefix: '([^']+)'/", $leer('bootstrap/app.php'), $p); $prefijo = '/' . trim($p[1] ?? 'api', '/');
preg_match_all("/Route::(get|post|put|patch|delete)\('([^']+)'/", $leer('routes/api.php'), $r, PREG_SET_ORDER);
$enCodigo = array_map(fn ($x) => strtoupper($x[1]) . ' ' . $prefijo . $x[2], $r); $enMatriz = array_map(fn ($f) => "$f[1] $f[2]", $fuente['grupo']);
sort($enCodigo); sort($enMatriz);
ver('M-01', $enCodigo !== [] && $enCodigo === $enMatriz, sprintf('Las %d rutas de routes/api.php son las mismas que las de la matriz', count($enCodigo)));

// M-02: cada ruta de Micro-HIS existe en su archivo ({param} y grupos de regex cuentan como comodín)
$forma = fn (string $ruta) => preg_replace(['/\{[^}]+\}/', '/\([^)]*\)/'], '*', $ruta);
$faltan = []; $ultimo = '';   // «.../archivo» en la matriz = el mismo archivo de la fila anterior
foreach ($fuente['micro'] as $f) {
    $ultimo = str_starts_with($f[3], '...') ? $ultimo : ($archivos[$f[3]] ?? '');
    preg_match_all('/\/api\/v1\/[^\'"#$\s]*/', $leer($ultimo), $m);
    if (!in_array($forma($f[2]), array_map($forma, $m[0]), true)) { $faltan[] = $f[2]; }
}
ver('M-02', count($fuente['micro']) > 0 && $faltan === [], sprintf('Las %d rutas de Micro-HIS existen en su archivo (faltan: %s)', count($fuente['micro']), implode(', ', $faltan) ?: 'ninguna'));

// M-03: tablas de la matriz contra las migraciones
$sql = implode("\n", array_map('file_get_contents', glob("$raiz/database/migrations/*/*.php") ?: []));
preg_match_all("/CREATE TABLE (?:IF NOT EXISTS )?(\w+)|Schema::create\('(\w+)'/", $sql, $t); $tablas = array_filter(array_merge($t[1], $t[2]));
$mal = [];
foreach ($filas as $f) {
    preg_match_all('/`(\w+)`( \([^)]*no existe\))?/', "$f[4] $f[5]", $ts, PREG_SET_ORDER);
    foreach ($ts as $x) {
        $existe = in_array($x[1], $tablas, true); $dice = !isset($x[2]) && !in_array($x[1], ['tenants', 'roles', 'users', 'model_has_roles'], true);
        if ($existe !== $dice) { $mal[] = $x[1]; }
    }
}
ver('M-03', $tablas !== [] && $mal === [], sprintf('Las tablas de la matriz existen en las migraciones salvo las marcadas como inexistentes (no coinciden: %s)', implode(', ', array_unique($mal)) ?: 'ninguna'));

// M-04: escribir en 2 tablas o más exige transacción
$sinTx = array_map(fn ($f) => $f[2], array_filter($filas, fn ($f) => preg_match_all('/`\w+`/', $f[5]) >= 2 && $f[6] !== 'Sí'));
ver('M-04', $sinTx === [], sprintf('Las %d filas que escriben en 2 tablas o más están marcadas con transacción; %d endpoints la necesitan', count(array_filter($filas, fn ($f) => preg_match_all('/`\w+`/', $f[5]) >= 2)), count(array_filter($filas, fn ($f) => $f[6] === 'Sí'))));

// M-05: hallazgo E-01
$usa = str_contains($leer('app/Http/Middleware/TenantMiddleware.php'), 'App\Models\Tenant') && str_contains($leer('app/Http/Controllers/Api/V1/AuthController.php'), 'App\Models\User');
ver('M-05', $usa && !is_file("$raiz/app/Models/User.php") && !is_file("$raiz/app/Models/Tenant.php") && array_intersect(['users', 'tenants'], $tablas) === [], 'E-01/E-02: la API usa App\Models\User y App\Models\Tenant, que no existen, y las tablas users y tenants no están en las migraciones');

echo $fallas === 0 ? "\nResultado: la matriz coincide con el repositorio.\n" : "\nResultado: $fallas comprobaciones fallan.\n";
exit($fallas ? 1 : 0);
