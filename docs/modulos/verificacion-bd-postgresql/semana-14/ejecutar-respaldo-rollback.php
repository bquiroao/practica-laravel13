<?php
declare(strict_types=1);

// Semana 14 — Ejecuta y mide: respaldo y restauración de HOSPITAL (pg_dump/pg_restore), rollback de la última migración y aislamiento entre dos hospitales.
// BORRA y recrea la base HOSPITAL de .env y crea bases y roles de prueba: exige una base *_test y --si-borrar-base-de-prueba. Necesita pg_dump y pg_restore.
// Uso desde la raíz del repo (con composer install): php docs/modulos/verificacion-bd-postgresql/semana-14/ejecutar-respaldo-rollback.php --si-borrar-base-de-prueba
use Illuminate\Support\Facades\{Artisan, DB};

$raiz = dirname(__DIR__, 4);
require "$raiz/vendor/autoload.php";
(require "$raiz/bootstrap/app.php")->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$cfg = config('database.connections.hospital'); $base = (string) $cfg['database'];
if (!in_array('--si-borrar-base-de-prueba', $argv, true) || !str_ends_with($base, '_test')) { exit("No se ejecutó: hace falta una base HOSPITAL *_test y --si-borrar-base-de-prueba (base actual: $base).\n"); }
$fallas = 0;
function ver(string $id, bool $ok, string $que): void { $GLOBALS['fallas'] += $ok ? 0 : 1; printf("%-6s %-5s %s\n", $id, $ok ? 'PASA' : 'FALLA', $que); }
$pg = fn (string $prog, array $args) => (function () use ($prog, $args, $cfg) {   // pg_dump / pg_restore / psql sin shell; la contraseña va por PGPASSWORD
    $p = proc_open(array_merge([$prog, '-h', $cfg['host'], '-p', (string) $cfg['port'], '-U', $cfg['username']], $args), [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $tub, null, array_merge(getenv(), ['PGPASSWORD' => (string) $cfg['password']]));
    $salida = stream_get_contents($tub[1]) . stream_get_contents($tub[2]); return [proc_close($p), $salida];
})();
$migrar = fn (string $orden, array $extra = []) => Artisan::call($orden, ['--database' => 'hospital', '--path' => database_path('migrations/hospital'), '--realpath' => true, '--force' => true] + $extra);
$migrar('migrate:fresh');
$db = DB::connection('hospital');
$db->unprepared("SET session_replication_role = replica;
  INSERT INTO local_patients (global_id, code, given_name, family_name, birth_date, sex) SELECT gen_random_uuid(), 'P-' || i, 'Ana', 'López ' || i, date '1980-01-01' + i, 'female' FROM generate_series(1, 5000) i;
  INSERT INTO care_episodes (local_patient_id, type, started_at, reason) SELECT local_patient_id, 'inpatient', now() - interval '3 days', 'Ingreso' FROM local_patients;
  INSERT INTO local_user_refs (local_user_ref_id, central_user_id, membership_id, display_name, role_code, synced_at) VALUES ('00000000-0000-0000-0000-000000000001', gen_random_uuid(), gen_random_uuid(), 'Dra. Ruiz', 'physician', now());
  INSERT INTO allergies (local_patient_id, allergen_name, reaction, allergy_type, severity, status, verified_by_user_ref_id) SELECT local_patient_id, 'Penicilina', 'Urticaria', 'allergy', 'moderate', 'active', '00000000-0000-0000-0000-000000000001' FROM local_patients LIMIT 300;
  SET session_replication_role = origin");
$huella = fn ($c) => $c->select("SELECT relname AS t, (xpath('/row/n/text()', query_to_xml('SELECT count(*) AS n FROM ' || quote_ident(relname), false, true, '')))[1]::text AS n FROM pg_stat_user_tables ORDER BY relname");
$sumas = fn ($c) => $c->scalar("SELECT md5(string_agg(local_patient_id::text || code || family_name, ',' ORDER BY local_patient_id)) FROM local_patients");

// 1. Respaldo y restauración en una base nueva
$archivo = sys_get_temp_dir() . "/respaldo-$base.dump"; $restaurada = "{$base}_restaurada";
$t0 = microtime(true); [$e1] = $pg('pg_dump', ['-Fc', '-f', $archivo, $base]); $tRespaldo = microtime(true) - $t0;
$pg('psql', ['-d', 'postgres', '-qc', "DROP DATABASE IF EXISTS $restaurada"]); $pg('psql', ['-d', 'postgres', '-qc', "CREATE DATABASE $restaurada"]);
$t0 = microtime(true); [$e2, $s2] = $pg('pg_restore', ['--no-owner', '-d', $restaurada, $archivo]); $tRestaura = microtime(true) - $t0;
config(['database.connections.restaurada' => array_merge($cfg, ['database' => $restaurada])]); $r = DB::connection('restaurada');
$filas = array_sum(array_map(fn ($x) => (int) $x->n, $huella($db)));
ver('RES-01', $e1 === 0 && $e2 === 0 && $huella($db) == $huella($r) && $sumas($db) === $sumas($r), sprintf('Respaldo (%.2f s, %d KB) y restauración en una base nueva (%.2f s): las %d tablas tienen las mismas filas (%d en total) y la suma de pacientes coincide',
    $tRespaldo, (int) (filesize($archivo) / 1024), $tRestaura, count($huella($r)), $filas));
ver('RES-02', (int) $r->scalar("SELECT count(*) FROM pg_trigger WHERE NOT tgisinternal") === (int) $db->scalar("SELECT count(*) FROM pg_trigger WHERE NOT tgisinternal") && $r->scalar("SELECT extname FROM pg_extension WHERE extname = 'btree_gist'") === 'btree_gist',
    'La base restaurada trae los mismos triggers de integridad y retención y la extensión btree_gist');
DB::purge('restaurada'); $pg('psql', ['-d', 'postgres', '-qc', "DROP DATABASE $restaurada"]); @unlink($archivo);

// 2. Rollback de la última migración (alergias y signos vitales) y vuelta a aplicarla
$alergias = (int) $db->scalar('SELECT count(*) FROM allergies');
$migrar('migrate:rollback', ['--step' => 1]);
$existe = fn (string $t) => (bool) $db->scalar('SELECT to_regclass(?) IS NOT NULL', ["public.$t"]);
$quitadas = !$existe('allergies') && !$existe('vital_signs') && $existe('clinical_notes');
$migrar('migrate');
ver('RBK-01', $quitadas && $existe('allergies') && (int) $db->scalar('SELECT count(*) FROM migrations') === 6, 'Rollback de 2026_08_11_000401: quita allergies y vital_signs y deja lo demás; migrate la vuelve a aplicar (6 migraciones)');
ver('RBK-02', $alergias === 300 && (int) $db->scalar('SELECT count(*) FROM allergies') === 0, "Riesgo medido: el rollback borró las $alergias alergias (down() hace DROP TABLE); por eso el plan exige respaldo antes de cualquier rollback");

// 3. Aislamiento: cada hospital con su base y su rol; el rol de un hospital no puede entrar a la base del otro (claves al azar, solo para esta prueba)
[$ca, $cb] = [bin2hex(random_bytes(12)), bin2hex(random_bytes(12))];
$pg('psql', ['-d', 'postgres', '-q', '-c', 'DROP DATABASE IF EXISTS hosp_a_test', '-c', 'DROP DATABASE IF EXISTS hosp_b_test', '-c', 'DROP ROLE IF EXISTS app_hosp_a', '-c', 'DROP ROLE IF EXISTS app_hosp_b',
    '-c', "CREATE ROLE app_hosp_a LOGIN PASSWORD '$ca'", '-c', "CREATE ROLE app_hosp_b LOGIN PASSWORD '$cb'", '-c', 'CREATE DATABASE hosp_a_test', '-c', 'CREATE DATABASE hosp_b_test']);
$entra = function (string $rol, string $clave, string $bd) use ($cfg): bool { try { new PDO("pgsql:host={$cfg['host']};port={$cfg['port']};dbname=$bd", $rol, $clave); return true; } catch (PDOException) { return false; } };
ver('AIS-00', $entra('app_hosp_a', $ca, 'hosp_b_test'), 'Hueco por defecto: sin más configuración, el rol del hospital A SÍ entra a la base del hospital B (PostgreSQL da CONNECT a PUBLIC)');
$pg('psql', ['-d', 'postgres', '-q', '-c', 'REVOKE CONNECT ON DATABASE hosp_a_test, hosp_b_test FROM PUBLIC', '-c', 'GRANT CONNECT ON DATABASE hosp_a_test TO app_hosp_a', '-c', 'GRANT CONNECT ON DATABASE hosp_b_test TO app_hosp_b']);
ver('AIS-01', $entra('app_hosp_a', $ca, 'hosp_a_test') && !$entra('app_hosp_a', $ca, 'hosp_b_test') && !$entra('app_hosp_b', $cb, 'hosp_a_test'), 'Con REVOKE CONNECT … FROM PUBLIC y un rol por hospital, el rol del hospital A entra a su base y no a la del B (ni al revés)');
$pg('psql', ['-d', 'postgres', '-q', '-c', 'DROP DATABASE hosp_a_test', '-c', 'DROP DATABASE hosp_b_test', '-c', 'DROP ROLE app_hosp_a', '-c', 'DROP ROLE app_hosp_b']);
echo $fallas === 0 ? "\nResultado: restauración, rollback y aislamiento ejecutados y comprobados.\n" : "\nResultado: $fallas comprobaciones fallan.\n";
exit($fallas ? 1 : 0);
