<?php
declare(strict_types=1);

// Semana 4 — Verifica la revisión de conexiones y la propuesta RepositorioPacientes en dos niveles:
//   1) estático (siempre): lee config/database.php, las migraciones y app/ y comprueba lo que afirma el documento;
//   2) ejecución (si SHI_PG_DSN está definido): esquema temporal con las migraciones HOSPITAL, prueba el repositorio
//      y revisa el plan (EXPLAIN) de cada consulta antes y después de los índices propuestos; al final borra el esquema.
// Uso desde la raíz del repo: php docs/modulos/verificacion-bd-postgresql/semana-04/verificar-repositorio.php
require __DIR__ . '/propuesta/RepositorioPacientes.php';
require __DIR__ . '/propuesta/PdoRepositorioPacientes.php';
use Shi\Pacientes\{ErrorRepositorio, EstadoVinculo, PacienteLocal, PdoRepositorioPacientes, RepositorioPacientes};

$raiz = dirname(__DIR__, 4);
$fallas = 0;
function ver(string $id, bool $ok, string $que): void { $GLOBALS['fallas'] += $ok ? 0 : 1; printf("%-7s %-5s %s\n", $id, $ok ? 'PASA' : 'FALLA', $que); }
$leer = fn (string $ruta) => is_file($ruta) ? (string) file_get_contents($ruta) : '';
$migraciones = fn (string $base) => glob("$raiz/database/migrations/$base/*.php") ?: [];

// ---- 1) Estático: cómo accede la persistencia a cada conexión ----
$config = $leer("$raiz/config/database.php");
$bloque = fn (string $n) => preg_match("/\n        '$n' => \[(.*?)\n        \],/s", $config, $m) ? $m[1] : '';
$pg = fn (string $n, string $env) => str_contains($bloque($n), "'driver' => 'pgsql'") && str_contains($bloque($n), "'password' => env('{$env}_DB_PASSWORD', '')");
ver('CON-01', $pg('central', 'CENTRAL') && $pg('hospital', 'HOSPITAL'), "config/database.php: conexiones 'central' y 'hospital' en PostgreSQL, credenciales solo por variables de entorno");
ver('CON-02', str_contains($config, "'default' => env('DB_CONNECTION', 'hospital')"), "La conexión por defecto es 'hospital'");
foreach (['central', 'hospital'] as $base) {
    $fijas = array_filter($migraciones($base), fn ($f) => str_contains($leer($f), "protected \$connection = '$base';") && str_contains($leer($f), 'DB::connection($this->connection)'));
    ver('CON-03', $migraciones($base) !== [] && count($fijas) === count($migraciones($base)), sprintf("Migraciones de %s: %d/%d fijan su conexión con \$connection", $base, count($fijas), count($migraciones($base))));
}
$sueltas = array_filter($migraciones('framework'), fn ($f) => !str_contains($leer($f), 'protected $connection'));
ver('CON-04', $migraciones('framework') !== [] && count($sueltas) === count($migraciones('framework')), sprintf("Migraciones de framework: %d sin \$connection → caen en la conexión por defecto (hospital)", count($sueltas)));

$app = implode("\n", array_map($leer, array_filter(iterator_to_array(new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$raiz/app", FilesystemIterator::SKIP_DOTS))), fn ($f) => $f->isFile())));
preg_match_all('/use App\\\\Models\\\\(\w+);/', $app, $usados);
$tablas = implode("\n", array_map($leer, array_merge($migraciones('central'), $migraciones('hospital'), $migraciones('framework'))));
$faltan = array_filter(['users', 'tenants', 'roles'], fn ($t) => !preg_match("/CREATE TABLE $t\b|Schema::create\('$t'/", $tablas));
printf("H-06    %s app/ usa App\\Models\\{%s}; app/Models %s; tablas sin migración: %s\n", is_dir("$raiz/app/Models") ? 'REVISAR' : 'SIGUE',
       implode(', ', array_unique($usados[1])), is_dir("$raiz/app/Models") ? 'ya existe' : 'no existe', $faltan ? implode(', ', $faltan) : 'ninguna');

preg_match('/CREATE TABLE local_patients \((.*?)\n\s*\);/s', implode("\n", array_map($leer, $migraciones('hospital'))), $ddl);
preg_match('/^SELECT (.*?) FROM/', PdoRepositorioPacientes::CONSULTAS['porId'], $sel);
$columnas = array_map('trim', explode(',', $sel[1]));
ver('REP-01', isset($ddl[1]) && array_filter($columnas, fn ($c) => !preg_match("/\b$c (uuid|text|date)/", $ddl[1])) === [], 'Las ' . count($columnas) . ' columnas que usa el repositorio existen en CREATE TABLE local_patients');
$metodos = array_map(fn ($m) => $m->getName(), (new ReflectionClass(RepositorioPacientes::class))->getMethods());
ver('REP-02', array_diff($metodos, array_keys(PdoRepositorioPacientes::CONSULTAS)) === [], count($metodos) . ' operaciones de la interfaz, cada una con su consulta preparada');

// ---- 2) Ejecución en PostgreSQL ----
$dsn = getenv('SHI_PG_DSN');
if (!$dsn || !extension_loaded('pdo_pgsql')) { echo "Ejecución: OMITIDA (requiere la extensión pdo_pgsql y SHI_PG_DSN, SHI_PG_USER, SHI_PG_PASS)\n"; exit($fallas ? 1 : 0); }
$pdo = new PDO($dsn, getenv('SHI_PG_USER') ?: null, getenv('SHI_PG_PASS') ?: null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$esquema = 'verif_s4_' . getmypid();
$pdo->exec("CREATE SCHEMA $esquema; SET search_path TO $esquema, public");
try {
    foreach ($migraciones('hospital') as $archivo) {   // mismo SQL que ejecuta Laravel (como en la semana 2)
        preg_match_all("/->statement\('([^']*)'\)|<<<'SQL'\R(.*?)\R\s*SQL\);/s", $leer($archivo), $sql, PREG_SET_ORDER);
        foreach ($sql as $s) { $pdo->exec(($s[1] ?? '') !== '' ? $s[1] : $s[2]); }
    }
    // 5 000 pacientes: 400 combinaciones de apellidos; 1 de cada 100 pendiente de vínculo con CENTRAL.
    $pdo->exec("INSERT INTO local_patients (global_id, code, given_name, family_name, birth_date, sex, mpi_link_status, created_at)
        SELECT gen_random_uuid(), 'P-' || lpad(i::text, 5, '0'), (ARRAY['Ana','Luis','Rosa','Juan','Eva'])[i % 5 + 1],
               a[i % 20 + 1] || ' ' || a[(i / 20) % 20 + 1], date '1950-01-01' + i, (ARRAY['female','male'])[i % 2 + 1],
               CASE WHEN i % 100 = 0 THEN 'pending' ELSE 'linked' END, now() - (5000 - i) * interval '1 minute'
        FROM generate_series(1, 5000) i, (SELECT ARRAY['Pérez','López','García','Gómez','Hernández','Martínez','Ramírez','Cruz','Reyes','Morales',
               'Ortega','Díaz','Castillo','Mendoza','Flores','Ruiz','Juárez','Méndez','Barrios','Orellana'] a) s; ANALYZE local_patients;");
    $repo = new PdoRepositorioPacientes($pdo);
    $uuid = fn () => (string) $pdo->query('SELECT gen_random_uuid()')->fetchColumn();
    [$id, $global] = [$uuid(), $uuid()];
    $repo->registrar(new PacienteLocal($id, $global, 'P-NUEVO', 'Damaris', 'Reyes Ortega', '1979-05-04', 'female'));
    ver('FUN-01', $repo->porCodigo('P-NUEVO')?->id === $id && $repo->porId($id)?->codigo === 'P-NUEVO' && $repo->porGlobalId($global)?->apellidos === 'Reyes Ortega', 'registrar y leer por id, código y global_id devuelven el mismo paciente');
    $dup = null; try { $repo->registrar(new PacienteLocal($uuid(), $uuid(), 'P-NUEVO', 'X', 'Y', '1990-01-01', 'male')); } catch (ErrorRepositorio $e) { $dup = $e->getPrevious()?->getCode(); }
    ver('FUN-02', $dup === '23505', 'Código repetido → ErrorRepositorio con SQLSTATE 23505 (local_patients_code_unique)');
    $encontrados = $repo->buscarPorApellido('PÉREZ GÓ', 50);
    ver('FUN-03', count($encontrados) > 0 && array_filter($encontrados, fn ($p) => !str_starts_with($p->apellidos, 'Pérez Gó')) === [] && $repo->buscarPorApellido('%') === [], 'buscarPorApellido: prefijo sin distinguir mayúsculas; «%» se busca literal, no como comodín');
    $pendientes = $repo->pendientesDeVinculo(100);
    $repo->marcarVinculo($pendientes[0]->id, $pendientes[0]->globalId, EstadoVinculo::Vinculado);
    $noExiste = false; try { $repo->marcarVinculo($uuid(), $uuid(), EstadoVinculo::Vinculado); } catch (ErrorRepositorio) { $noExiste = true; }
    ver('FUN-04', count($pendientes) === 51 && count($repo->pendientesDeVinculo(100)) === 50 && $noExiste, 'pendientesDeVinculo: 51 pendientes; al vincular uno quedan 50; vincular un id inexistente → ErrorRepositorio');

    $indices = function (array $nodo) use (&$indices): array {   // nombres de índice (o «Seq Scan») en todo el plan
        $propio = isset($nodo['Index Name']) ? [$nodo['Index Name']] : (($nodo['Node Type'] ?? '') === 'Seq Scan' ? ['Seq Scan'] : []);
        return array_merge($propio, ...array_map($indices, $nodo['Plans'] ?? []));
    };
    $plan = function (string $consulta, array $p) use ($pdo, $indices): array {
        $c = $pdo->prepare('EXPLAIN (FORMAT JSON) ' . PdoRepositorioPacientes::CONSULTAS[$consulta]); $c->execute($p);
        return $indices(json_decode((string) $c->fetchColumn(), true)[0]['Plan']);
    };
    $param = ['porId' => [$id], 'porCodigo' => ['P-NUEVO'], 'porGlobalId' => [$global], 'buscarPorApellido' => ['pérez gó%', 20], 'pendientesDeVinculo' => [100]];
    $antes = array_map(fn ($c) => $plan($c, $param[$c]), array_combine(array_keys($param), array_keys($param)));
    foreach ($antes as $c => $usa) { printf("        %-20s antes: %s\n", $c, implode(', ', $usa)); }
    ver('PLN-01', $antes['porId'] === ['local_patients_pkey'] && $antes['porCodigo'] === ['local_patients_code_unique'] && $antes['porGlobalId'] === ['local_patients_global_id_unique'], 'Búsquedas por id, código y global_id ya usan índice único');
    ver('PLN-02', in_array('Seq Scan', $antes['buscarPorApellido'], true) && in_array('Seq Scan', $antes['pendientesDeVinculo'], true), 'Sin los índices propuestos, apellido y pendientes recorren toda la tabla (Seq Scan)');
    $pdo->exec((string) file_get_contents(__DIR__ . '/propuesta/indices-propuestos.sql') . '; ANALYZE local_patients;');
    $despues = ['buscarPorApellido' => $plan('buscarPorApellido', $param['buscarPorApellido']), 'pendientesDeVinculo' => $plan('pendientesDeVinculo', $param['pendientesDeVinculo'])];
    foreach ($despues as $c => $usa) { printf("        %-20s después: %s\n", $c, implode(', ', $usa)); }
    ver('PLN-03', in_array('local_patients_family_name_lower_pattern_index', $despues['buscarPorApellido'], true) && in_array('local_patients_mpi_pending_index', $despues['pendientesDeVinculo'], true), 'Con indices-propuestos.sql las dos consultas usan su índice');
} finally {
    $pdo->exec("SET search_path TO public; DROP SCHEMA $esquema CASCADE");
}
echo $fallas === 0 ? "\nResultado: todo lo esperado se cumple.\n" : "\nResultado: $fallas verificaciones fallaron.\n";
exit($fallas ? 1 : 0);
