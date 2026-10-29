<?php
declare(strict_types=1);

// Semana 8 — Verifica el catálogo «violación de restricción → mensaje → HTTP» en tres niveles:
//   1) estático (siempre): el catálogo cubre las restricciones únicas de las migraciones y el documento lo repite sin diferencias;
//   2) traductor sin base de datos (siempre): casos con errores construidos a mano;
//   3) PostgreSQL (si SHI_PG_DSN está definido): esquema temporal con las migraciones, se provoca cada tipo de violación y se traduce el error real.
// Uso desde la raíz del repo: php docs/modulos/verificacion-bd-postgresql/semana-08/verificar-catalogo.php
require __DIR__ . '/traductor/TraductorViolaciones.php';
use Shi\Persistencia\TraductorViolaciones;

$raiz = dirname(__DIR__, 4); $fallas = 0;
function ver(string $id, bool $ok, string $que): void { $GLOBALS['fallas'] += $ok ? 0 : 1; printf("%-7s %-5s %s\n", $id, $ok ? 'PASA' : 'FALLA', $que); }
$catalogo = require __DIR__ . '/traductor/catalogo.php'; $tr = new TraductorViolaciones($catalogo);
$migraciones = fn (string $b) => glob("$raiz/database/migrations/$b/*.php") ?: [];
$sql = fn (string $b) => implode("\n", array_map('file_get_contents', $migraciones($b)));

// ---- 1) Estático ----
$texto = $sql('central') . "\n" . $sql('hospital');
preg_match_all('/CONSTRAINT (\w+) (?:UNIQUE|EXCLUDE)|CREATE UNIQUE INDEX (\w+)/', $texto, $m);
$reales = array_values(array_unique(array_filter(array_merge($m[1], $m[2]))));
$cat = array_keys($catalogo['unicas']);
ver('EST-01', $reales !== [] && array_diff($reales, $cat) === [], sprintf('Las %d restricciones únicas, de exclusión e índices únicos de las migraciones están en el catálogo (faltan: %s)', count($reales), implode(', ', array_diff($reales, $cat)) ?: 'ninguna'));
ver('EST-02', array_diff($cat, $reales) === [], 'El catálogo no tiene restricciones que ya no existen en las migraciones (sobran: ' . (implode(', ', array_diff($cat, $reales)) ?: 'ninguna') . ')');
$tecnicas = '/violates|constraint|SQLSTATE|relation|table|duplicate key|\bSELECT\b|_unique|_check/i'; $malos = [];
foreach ($catalogo['unicas'] as $n => [$c, $msg]) { if (preg_match($tecnicas, $msg) || !preg_match('/^[a-z_]+$/', $c)) { $malos[] = $n; } }
foreach ($catalogo['checks'] as [, $c, $msg]) { if (preg_match($tecnicas, $msg)) { $malos[] = $c; } }
ver('EST-03', $malos === [], 'Ningún mensaje del catálogo contiene SQL, tablas ni nombres de restricciones' . ($malos ? ': ' . implode(', ', $malos) : ''));
$doc = (string) @file_get_contents(__DIR__ . '/semana-08-errores-integridad.md'); $sinDoc = [];
foreach ($catalogo['unicas'] as $n => [$c, $msg]) { if (!str_contains($doc, "| `$n` | `$c` | 409 | $msg |")) { $sinDoc[] = $n; } }
foreach (array_merge(array_map(fn ($x) => [$x[1], 422, $x[2]], $catalogo['checks']), array_map(fn ($x) => [$x[2], $x[1], $x[3]], $catalogo['disparadores'])) as [$c, $http, $msg]) { if (!str_contains($doc, "| `$c` | $http | $msg |")) { $sinDoc[] = $c; } }
ver('EST-04', $doc !== '' && $sinDoc === [], 'Las tablas del documento repiten el catálogo (código, HTTP y mensaje)' . ($sinDoc ? '; difieren: ' . implode(', ', $sinDoc) : ''));
preg_match_all("/RAISE EXCEPTION '([^']+)'/", $texto, $r);   // los triggers de las migraciones (HOSPITAL y CENTRAL)
$sinRegla = array_filter(array_unique($r[1]), fn ($x) => !array_filter($catalogo['disparadores'], fn ($d) => preg_match($d[0], $x)));
ver('EST-05', $r[1] !== [] && $sinRegla === [], sprintf('Los %d mensajes distintos de RAISE EXCEPTION de las migraciones tienen regla en «disparadores» (sin regla: %s)', count(array_unique($r[1])), implode(', ', $sinRegla) ?: 'ninguno'));

// ---- 2) Traductor con errores construidos a mano ----
$falso = function (string $estado, string $mensaje): \PDOException { $e = new \PDOException($mensaje); $e->errorInfo = [$estado, 0, $mensaje]; return $e; };
$caso = fn (string $estado, string $mensaje) => $tr->traducir($falso($estado, $mensaje));
$r = $caso('23505', 'duplicate key value violates unique constraint "doctor_profiles_license_number_unique"');
ver('TRA-01', [$r['http'], $r['codigo'], $r['campo']] === [409, 'medico_duplicado', 'license_number'], 'Colegiado repetido → 409 medico_duplicado, campo license_number');
$r = $caso('23505', 'duplicate key value violates unique constraint "tabla_nueva_algo_unique"');
ver('TRA-02', [$r['http'], $r['codigo']] === [409, 'registro_duplicado'], 'Restricción única que el catálogo no conoce → 409 registro_duplicado (no se cae)');
$r = $caso('23505', 'llave duplicada viola la restricción de unicidad «doctor_profiles_license_number_unique»');
ver('TRA-03', [$r['http'], $r['codigo']] === [409, 'registro_duplicado'], 'Mensaje de PostgreSQL en español (sin lc_messages = C): cae al 409 genérico, no al 500');
$r = $caso('23514', 'new row for relation "tabla_nueva" violates check constraint "tabla_nueva_peso_check"');
ver('TRA-04', [$r['http'], $r['codigo'], $r['campo']] === [422, 'valor_invalido', 'peso'], 'CHECK sin regla específica → 422 valor_invalido con el campo');
$r = $caso('40001', 'could not serialize access due to concurrent update');
ver('TRA-05', [$r['http'], $r['codigo']] === [409, 'conflicto_concurrencia'], 'Error de serialización → 409 conflicto_concurrencia');
$r = $caso('08006', 'connection failure');
ver('TRA-06', [$r['http'], $r['codigo']] === [503, 'base_no_disponible'], 'Conexión caída → 503 base_no_disponible');
$r = $caso('XX000', 'internal error: relation "pacientes" does not exist at character 15');
ver('TRA-07', [$r['http'], $r['codigo']] === [500, 'error_interno'] && !str_contains($r['mensaje'], 'pacientes'), 'Error desconocido → 500 error_interno sin repetir el texto de la base');

// ---- 3) PostgreSQL ----
$dsn = getenv('SHI_PG_DSN');
if (!$dsn || !extension_loaded('pdo_pgsql')) { echo "Ejecución: OMITIDA (requiere la extensión pdo_pgsql y SHI_PG_DSN, SHI_PG_USER, SHI_PG_PASS)\n"; exit($fallas ? 1 : 0); }
$pdo = new PDO($dsn, getenv('SHI_PG_USER') ?: null, getenv('SHI_PG_PASS') ?: null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$esquema = 'verif_s8_' . getmypid();
$pdo->exec("CREATE SCHEMA $esquema; SET search_path TO $esquema, public");
try { $pdo->exec("SET lc_messages = 'C'"); } catch (\PDOException) { /* en producción: ALTER ROLE ... SET lc_messages = 'C' */ }
$cargar = function (string $base) use ($pdo, $migraciones) {   // mismo SQL que ejecuta Laravel
    foreach ($migraciones($base) as $archivo) {
        preg_match_all("/->statement\('([^']*)'\)|<<<'SQL'\R(.*?)\R\s*SQL\);/s", (string) file_get_contents($archivo), $s, PREG_SET_ORDER);
        foreach ($s as $x) { $pdo->exec(($x[1] ?? '') !== '' ? $x[1] : $x[2]); }
    }
};
try {
    $cargar('hospital');
    $u = fn () => (string) $pdo->query('SELECT gen_random_uuid()')->fetchColumn();
    [$usr, $esp, $med, $pac, $epi, $serv, $cama, $reg, $uno, $usr2, $cardio] = array_map($u, range(1, 11));
    $pdo->exec("INSERT INTO local_user_refs (local_user_ref_id, central_user_id, membership_id, display_name, role_code, synced_at) VALUES ('$usr', '{$u()}', '{$u()}', 'Dra. Ana', 'doctor', now());
        INSERT INTO specialties (specialty_id, code, name) VALUES ('$esp', 'MI', 'Medicina interna');
        INSERT INTO doctor_profiles (doctor_profile_id, local_user_ref_id, license_number, valid_from) VALUES ('$med', '$usr', '11111', '2026-01-01');
        INSERT INTO doctor_specialties (doctor_profile_id, specialty_id, is_primary, valid_from) VALUES ('$med', '$esp', true, '2026-01-01');
        INSERT INTO local_patients (local_patient_id, global_id, code, given_name, family_name, birth_date, sex) VALUES ('$pac', '{$u()}', 'P-1', 'Luis', 'Paz', '1980-01-01', 'male');
        INSERT INTO care_episodes (care_episode_id, local_patient_id, type, started_at, reason) VALUES ('$epi', '$pac', 'inpatient', now() - interval '1 day', 'ingreso');
        INSERT INTO local_user_refs (local_user_ref_id, central_user_id, membership_id, display_name, role_code, synced_at) VALUES ('$usr2', '{$u()}', '{$u()}', 'Enf. Rosa', 'nurse', now());
        INSERT INTO specialties (specialty_id, code, name) VALUES ('$cardio', 'CAR', 'Cardiología');
        INSERT INTO care_assignments (care_episode_id, doctor_profile_id, specialty_id, assigned_by_user_ref_id, scope, reason, source, valid_from) VALUES ('$epi', '$med', '$esp', '$usr', '{\"episodio\": true}', 'ingreso', 'manual', now() - interval '1 day');
        INSERT INTO wards (ward_id, code, name, location) VALUES ('$serv', 'MED', 'Medicina', 'Piso 2');
        INSERT INTO beds (bed_id, ward_id, code, type) VALUES ('$cama', '$serv', 'C1', 'general');
        INSERT INTO bed_stays (care_episode_id, bed_id, assigned_by_user_ref_id, started_at, reason) VALUES ('$epi', '$cama', '$usr', now() - interval '1 hour', 'ingreso');
        INSERT INTO appointments (local_patient_id, doctor_profile_id, specialty_id, starts_at, ends_at, reason) VALUES ('$pac', '$med', '$esp', '2026-11-02 08:00-06', '2026-11-02 08:30-06', 'control');
        INSERT INTO medical_records (medical_record_id, local_patient_id, record_number) VALUES ('$reg', '$pac', 'EXP-1');
        INSERT INTO clinical_notes (medical_record_id, care_episode_id, author_user_ref_id, note_type, content, version_no) VALUES ('$reg', '$epi', '$usr', 'evolución', 'texto', 1)");
    $prueba = function (string $id, string $que, string $consulta, array $esperado) use ($pdo, $tr, &$fallas, $esp) {
        try { $pdo->exec('SAVEPOINT p'); $pdo->exec($consulta); $pdo->exec('RELEASE SAVEPOINT p'); $real = ['sin error']; }
        catch (\PDOException $e) { $pdo->exec('ROLLBACK TO SAVEPOINT p'); $t = $tr->traducir($e); $real = [$t['http'], $t['codigo'], $t['campo'] ?? null]; $txt = $t['mensaje']; }
        $ok = $real === $esperado && !preg_match('/violates|constraint|_pkey|_unique|_fkey|_check|relation/i', $txt ?? '');
        ver($id, $ok, $que . ' → ' . implode(' ', array_map(fn ($x) => (string) ($x ?? '—'), $real)));
    };
    $pdo->exec('BEGIN');
    $prueba('LIV-01', 'Especialidad con código repetido', "INSERT INTO specialties (code, name) VALUES ('MI', 'Otra')", [409, 'especialidad_duplicada', 'code']);
    $prueba('LIV-02', 'Colegiado repetido', "INSERT INTO doctor_profiles (local_user_ref_id, license_number, valid_from) VALUES ('$usr', '11111', '2026-02-01')", [409, 'medico_duplicado', 'license_number']);
    $prueba('LIV-03', 'Misma especialidad con fechas que se cruzan (EXCLUDE)', "INSERT INTO doctor_specialties (doctor_profile_id, specialty_id, valid_from) VALUES ('$med', '$esp', '2026-06-01')", [409, 'especialidad_duplicada', null]);
    $prueba('LIV-04', 'Paciente con código repetido', "INSERT INTO local_patients (global_id, code, given_name, family_name, birth_date, sex) VALUES (gen_random_uuid(), 'P-1', 'X', 'Y', '1990-01-01', 'male')", [409, 'paciente_duplicado', 'code']);
    $prueba('LIV-05', 'Cita del médico que se cruza (EXCLUDE)', "INSERT INTO appointments (local_patient_id, doctor_profile_id, specialty_id, starts_at, ends_at, reason) VALUES ('$pac', '$med', '$esp', '2026-11-02 08:15-06', '2026-11-02 08:45-06', 'otra')", [409, 'cita_conflicto_medico', null]);
    $prueba('LIV-06', 'Cama ocupada en el mismo período (EXCLUDE)', "INSERT INTO bed_stays (care_episode_id, bed_id, assigned_by_user_ref_id, started_at, reason) VALUES ('$epi', '$cama', '$usr', now() - interval '30 minutes', 'otro')", [409, 'cama_ocupada', null]);
    $prueba('LIV-07', 'Versión de nota repetida', "INSERT INTO clinical_notes (medical_record_id, care_episode_id, author_user_ref_id, note_type, content, version_no) VALUES ('$reg', '$epi', '$usr', 'evolución', 'otro', 1)", [409, 'version_duplicada', null]);
    $prueba('LIV-08', 'Referencia a un usuario que no existe (llave foránea)', "INSERT INTO doctor_profiles (local_user_ref_id, license_number, valid_from) VALUES ('$uno', '22222', '2026-01-01')", [422, 'referencia_inexistente', 'local_user_ref_id']);
    $prueba('LIV-09', 'Borrar una especialidad que ya tiene médicos (RESTRICT)', "DELETE FROM specialties WHERE specialty_id = '$esp'", [409, 'registro_en_uso', null]);
    $prueba('LIV-10', 'Dato obligatorio ausente (NOT NULL)', "INSERT INTO specialties (code) VALUES ('CAR')", [422, 'campo_obligatorio', 'name']);
    $prueba('LIV-11', 'Fecha de nacimiento futura (CHECK)', "UPDATE local_patients SET birth_date = current_date + 1 WHERE local_patient_id = '$pac'", [422, 'fecha_nacimiento_invalida', 'birth_date']);
    $prueba('LIV-12', 'Vigencia que termina antes de empezar (CHECK)', "INSERT INTO doctor_profiles (local_user_ref_id, license_number, valid_from, valid_to) VALUES ('$usr', '33333', '2026-05-01', '2026-04-01')", [422, 'periodo_invalido', null]);
    $prueba('LIV-13', 'Estado que no existe (CHECK)', "UPDATE specialties SET status = 'archivada' WHERE specialty_id = '$esp'", [422, 'estado_invalido', 'status']);
    $prueba('LIV-14', 'Motivo de cita en blanco (CHECK)', "UPDATE appointments SET reason = '  ' WHERE local_patient_id = '$pac'", [422, 'texto_vacio', 'reason']);
    $prueba('LIV-15', 'Identificador que no es un UUID (22P02)', "SELECT 'abc'::uuid", [422, 'formato_invalido', null]);
    $prueba('LIV-16', 'Fecha imposible (22008)', "SELECT '2026-13-45'::date", [422, 'formato_invalido', null]);
    $prueba('LIV-17', 'Borrar una cita (trigger de retención)', "DELETE FROM appointments WHERE local_patient_id = '$pac'", [409, 'no_se_elimina', null]);
    $prueba('LIV-18', 'Cita con una especialidad que el médico no tiene (trigger)', "INSERT INTO appointments (local_patient_id, doctor_profile_id, specialty_id, starts_at, ends_at, reason) VALUES ('$pac', '$med', '$cardio', '2026-11-03 08:00-06', '2026-11-03 08:30-06', 'control')", [422, 'medico_sin_especialidad', null]);
    $prueba('LIV-19', 'Nota escrita por alguien sin asignación en el episodio (trigger)', "INSERT INTO clinical_notes (medical_record_id, care_episode_id, author_user_ref_id, note_type, content, version_no) VALUES ('$reg', '$epi', '$usr2', 'evolución', 'x', 1)", [403, 'autor_sin_asignacion', null]);
    $prueba('LIV-20', 'Cambiar una nota ya firmada (trigger)', "UPDATE clinical_notes SET status = 'signed', signed_at = now() WHERE medical_record_id = '$reg'; UPDATE clinical_notes SET content = 'cambiado' WHERE medical_record_id = '$reg'", [409, 'registro_inmutable', null]);
    $pdo->exec('ROLLBACK');
} finally {
    $pdo->exec("SET search_path TO public; DROP SCHEMA $esquema CASCADE");
}
echo $fallas === 0 ? "\nResultado: todo lo esperado se cumple.\n" : "\nResultado: $fallas verificaciones fallaron.\n";
exit($fallas ? 1 : 0);
