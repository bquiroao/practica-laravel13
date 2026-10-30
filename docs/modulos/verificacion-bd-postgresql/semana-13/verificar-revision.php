<?php
declare(strict_types=1);

// Semana 13 — Comprueba con PostgreSQL cada afirmación del acta de revisión de la migración 2026_08_11_000401 (alergias y signos vitales de HOSPITAL).
//   CL-xx: puntos del checklist que se cumplen.  HAL-xx: hallazgos que se reproducen tal como el acta los describe.
//   PASA = la afirmación del acta es cierta. Si el autor corrige la migración, el HAL correspondiente pasa a FALLA y el acta debe actualizarse.
// Uso desde la raíz del repo: php docs/modulos/verificacion-bd-postgresql/semana-13/verificar-revision.php [carpeta-con-database/migrations]
$raiz = $argv[1] ?? dirname(__DIR__, 4); $fallas = 0;
function ver(string $id, bool $ok, string $que): void { $GLOBALS['fallas'] += $ok ? 0 : 1; printf("%-7s %-5s %s\n", $id, $ok ? 'PASA' : 'FALLA', $que); }
$archivo = "$raiz/database/migrations/hospital/2026_08_11_000401_create_allergy_and_vital_sign_tables.php";
$php = (string) @file_get_contents($archivo);
$lineas = array_map('strlen', preg_split('/\R/', $php));
ver('CL-01', str_contains($php, "protected \$connection = 'hospital'") && substr_count($php, 'gen_random_uuid()') >= 2, 'La migración fija la conexión hospital y las claves primarias son uuid con valor por defecto');
ver('HAL-09', max($lineas ?: [0]) > 800 && count(array_filter($lineas, fn ($l) => $l > 300)) >= 5, sprintf('F-09: legibilidad, la línea más larga mide %d caracteres y %d líneas pasan de 300', max($lineas ?: [0]), count(array_filter($lineas, fn ($l) => $l > 300))));

$dsn = getenv('SHI_PG_DSN');
if (!$dsn || !extension_loaded('pdo_pgsql')) { echo "Ejecución: OMITIDA (requiere la extensión pdo_pgsql y SHI_PG_DSN, SHI_PG_USER, SHI_PG_PASS)\n"; exit($fallas ? 1 : 0); }
$pdo = new PDO($dsn, getenv('SHI_PG_USER') ?: null, getenv('SHI_PG_PASS') ?: null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$esquema = 'verif_s13_' . getmypid();
$pdo->exec("CREATE SCHEMA $esquema; SET search_path TO $esquema, public");
$sql = function (string $a, string $metodo = 'up'): string {   // el SQL que ejecuta Laravel
    $c = (string) file_get_contents($a);
    if ($metodo === 'down') { preg_match("/function down\(\): void\s*\{\s*DB::connection\(\\\$this->connection\)->unprepared\('([^']*)'\)/", $c, $m); return $m[1] ?? ''; }
    preg_match_all("/->statement\('([^']*)'\)|<<<'SQL'\R(.*?)\R\s*SQL\);/s", $c, $s, PREG_SET_ORDER);
    return implode(";\n", array_map(fn ($x) => ($x[1] ?? '') !== '' ? $x[1] : $x[2], $s));
};
$intento = function (string $q) use ($pdo): string { try { $pdo->exec($q); return 'acepta'; } catch (\PDOException $e) { return $e->getMessage(); } };
$u = fn (string $k) => "md5('$k')::uuid";
try {
    foreach (glob("$raiz/database/migrations/hospital/*.php") as $a) { $pdo->exec($sql($a)); }
    $pdo->exec("INSERT INTO local_user_refs (local_user_ref_id, central_user_id, membership_id, display_name, role_code, synced_at) VALUES
        ({$u('doc')}, {$u('c1')}, {$u('m1')}, 'Dra. Ana', 'doctor', now()), ({$u('enf')}, {$u('c2')}, {$u('m2')}, 'Enf. Rosa', 'nurse', now()), ({$u('adm')}, {$u('c3')}, {$u('m3')}, 'Admisión', 'admission', now());
        INSERT INTO specialties (specialty_id, code, name) VALUES ({$u('esp')}, 'MI', 'Medicina interna');
        INSERT INTO doctor_profiles (doctor_profile_id, local_user_ref_id, license_number, valid_from) VALUES ({$u('med')}, {$u('doc')}, '11111', current_date - 400);
        INSERT INTO doctor_specialties (doctor_profile_id, specialty_id, is_primary, valid_from) VALUES ({$u('med')}, {$u('esp')}, true, current_date - 400)");
    $armar = function (string $k) use ($pdo, $u) {   // paciente, expediente, episodio abierto y médico asignado
        $pdo->exec("INSERT INTO local_patients (local_patient_id, global_id, code, given_name, family_name, birth_date, sex) VALUES ({$u("p$k")}, {$u("g$k")}, 'P-$k', 'Luis', 'Paz Gómez', '1980-01-01', 'male');
            INSERT INTO medical_records (medical_record_id, local_patient_id, record_number) VALUES ({$u("r$k")}, {$u("p$k")}, 'EXP-$k');
            INSERT INTO care_episodes (care_episode_id, local_patient_id, type, started_at, reason) VALUES ({$u("e$k")}, {$u("p$k")}, 'inpatient', now() - interval '10 days', 'ingreso');
            INSERT INTO care_assignments (care_episode_id, doctor_profile_id, specialty_id, assigned_by_user_ref_id, scope, reason, source, valid_from) VALUES ({$u("e$k")}, {$u('med')}, {$u('esp')}, {$u('adm')}, '{\"episodio\": true}', 'tratante', 'manual', now() - interval '10 days')");
    };
    $toma = fn (string $k, string $quien, string $cols, string $vals) => "INSERT INTO vital_signs (medical_record_id, care_episode_id, recorded_by_user_ref_id, $cols) VALUES ({$u("r$k")}, {$u("e$k")}, {$u($quien)}, $vals)";
    foreach (['a', 'b', 'c', 'd', 'e'] as $k) { $armar($k); }

    // Hallazgos
    $control = $intento($toma('a', 'doc', 'heart_rate, measured_at', "80, now()"));
    $m = $intento($toma('a', 'enf', 'heart_rate, measured_at', "80, now()"));
    ver('HAL-01', $control === 'acepta' && str_contains($m, 'vital_signs_author_assignment_trigger'), 'F-01: una enfermera no puede registrar signos vitales (el médico sí); la base exige un perfil de médico con asignación activa');
    $pdo->exec($toma('b', 'doc', 'heart_rate, measured_at', "80, now()"));
    $pdo->exec("UPDATE care_assignments SET status = 'closed', valid_to = now() WHERE care_episode_id = {$u('eb')}");
    $m1 = $intento("UPDATE vital_signs SET status = 'validated', validated_at = now() WHERE care_episode_id = {$u('eb')}");
    $m2 = $intento("UPDATE vital_signs SET status = 'entered_in_error' WHERE care_episode_id = {$u('eb')}");
    ver('HAL-02', str_contains($m1, 'vital_signs_author_assignment_trigger') && str_contains($m2, 'vital_signs_author_assignment_trigger'), 'F-02: un borrador que queda al cerrarse la asignación ya no se puede validar ni marcar como error');
    $pdo->exec($toma('c', 'doc', 'vital_sign_id, heart_rate, measured_at, status, validated_at', "{$u('v1')}, 80, now(), 'validated', now()"));
    $m = $intento("UPDATE vital_signs SET status = 'entered_in_error', validated_at = NULL WHERE vital_sign_id = {$u('v1')}");
    $v2 = $intento($toma('c', 'doc', 'heart_rate, measured_at, status, validated_at, version_no, supersedes_id, correction_reason', "82, now(), 'validated', now(), 2, {$u('v1')}, 'error de digitación'"));
    $validadas = (int) $pdo->query("SELECT count(*) FROM vital_signs WHERE care_episode_id = {$u('ec')} AND status = 'validated'")->fetchColumn();
    ver('HAL-03', str_contains($m, 'vital_signs_validated_immutable') && $v2 === 'acepta' && $validadas === 2, 'F-03: la toma validada no puede marcarse «entered_in_error»; al corregirla, la original y la nueva quedan «validated» (2 vigentes)');
    $f1 = $intento($toma('d', 'doc', 'heart_rate, measured_at', "80, (SELECT started_at - interval '30 days' FROM care_episodes WHERE care_episode_id = {$u('ed')})"));
    $f2 = $intento($toma('d', 'doc', 'heart_rate, measured_at, recorded_at', "80, now() + interval '1 day', now() + interval '1 day'"));
    ver('HAL-04', $f1 === 'acepta' && $f2 === 'acepta', 'F-04: se aceptan una toma de 30 días antes de iniciar el episodio y otra con fecha de mañana');
    $al = fn (string $k, string $quien, string $nombre, string $extra = '') => "INSERT INTO allergies (local_patient_id, verified_by_user_ref_id, allergen_name, allergy_type, severity $extra) VALUES ({$u("p$k")}, {$u($quien)}, '$nombre', 'allergy', 'mild' %s)";
    ver('HAL-05', $intento(sprintf($al('e', 'adm', 'Látex'), '')) === 'acepta', 'F-05: una alergia puede quedar «verificada» por un usuario de admisión (no se exige un rol clínico)');
    $pdo->exec(sprintf($al('e', 'doc', 'Polen', ', updated_at'), ", '2026-01-01'"));
    $pdo->exec("UPDATE allergies SET severity = 'severe' WHERE allergen_name = 'Polen'");
    ver('HAL-06', $pdo->query("SELECT updated_at::date::text FROM allergies WHERE allergen_name = 'Polen'")->fetchColumn() === '2026-01-01', 'F-06: al modificar una alergia, updated_at no cambia (ningún trigger lo actualiza)');
    $pdo->exec($toma('e', 'doc', 'temperature, measured_at', "36.123456789, now()"));
    ver('HAL-07', $pdo->query("SELECT temperature::text FROM vital_signs WHERE temperature IS NOT NULL AND care_episode_id = {$u('ee')}")->fetchColumn() === '36.123456789', 'F-07: la temperatura (numeric sin precisión) guarda 36.123456789 tal cual');
    $x = $intento(sprintf($al('e', 'doc', 'Penicilina'), '')) . $intento(sprintf($al('e', 'doc', 'Penicilinas'), ''));
    ver('HAL-08', $x === 'aceptaacepta', 'F-08: «Penicilina» y «Penicilinas» quedan como dos alergias activas (el índice único solo detecta nombres idénticos)');

    // Lo que sí cumple
    $fk = (int) $pdo->query("SELECT count(*) FROM pg_constraint WHERE contype = 'f' AND conrelid IN ('allergies'::regclass, 'vital_signs'::regclass)")->fetchColumn();
    $noRestrict = (int) $pdo->query("SELECT count(*) FROM pg_constraint WHERE contype = 'f' AND confdeltype <> 'r' AND conrelid IN ('allergies'::regclass, 'vital_signs'::regclass)")->fetchColumn();
    $sinIndice = (int) $pdo->query("SELECT count(*) FROM pg_constraint c WHERE contype = 'f' AND conrelid IN ('allergies'::regclass, 'vital_signs'::regclass) AND NOT EXISTS (SELECT 1 FROM pg_index i WHERE i.indrelid = c.conrelid AND i.indkey[0] = c.conkey[1])")->fetchColumn();
    ver('CL-02', $fk === 6 && $noRestrict === 0, "Las $fk llaves foráneas de las dos tablas son ON DELETE RESTRICT");
    ver('CL-03', $sinIndice === 0, 'Toda llave foránea tiene un índice que empieza por su columna');
    ver('CL-04', str_contains($intento('DELETE FROM vital_signs'), 'vital_signs_delete_protection') && str_contains($intento('DELETE FROM allergies'), 'allergies_delete_protection'), 'No se puede borrar ninguna toma ni alergia (trigger de retención)');
    $mal = $intento($toma('c', 'doc', 'heart_rate, measured_at, version_no, supersedes_id, correction_reason', "80, now(), 3, {$u('v1')}, 'x'"));
    $otra = $intento($toma('c', 'doc', 'heart_rate, measured_at, version_no, supersedes_id, correction_reason', "81, now(), 2, {$u('v1')}, 'otra'"));
    ver('CL-05', str_contains($mal, 'vital_signs_supersedes_version_trigger') && str_contains($otra, 'vital_signs_supersedes_unique'), 'Una corrección con versión que no sigue a la anterior, o una segunda corrección de la misma toma, se rechazan');
    ver('CL-06', str_contains($intento($toma('a', 'doc', 'systolic_bp, diastolic_bp, measured_at', '80, 120, now()')), 'vital_signs_blood_pressure_check'), 'La presión sistólica menor que la diastólica se rechaza con un CHECK nombrado');
    $baja = $intento($sql($archivo, 'down')); $fuera = $pdo->query("SELECT to_regclass('vital_signs') IS NULL AND to_regclass('allergies') IS NULL")->fetchColumn();
    $alta = $intento($sql($archivo)); $dentro = $pdo->query("SELECT to_regclass('vital_signs') IS NOT NULL AND to_regclass('allergies') IS NOT NULL")->fetchColumn();
    ver('CL-07', $baja === 'acepta' && $alta === 'acepta' && $fuera && $dentro, 'down() deshace la migración por completo y up() se puede volver a ejecutar');
} finally {
    $pdo->exec("SET search_path TO public; DROP SCHEMA $esquema CASCADE");
}
echo $fallas === 0 ? "\nResultado: todo lo que afirma el acta se cumple.\n" : "\nResultado: $fallas afirmaciones del acta no se cumplen.\n";
exit($fallas ? 1 : 0);
