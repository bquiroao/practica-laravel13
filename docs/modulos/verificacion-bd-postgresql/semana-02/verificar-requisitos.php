<?php
declare(strict_types=1);

// Semana 2 — Verifica los requisitos de datos del documento en dos niveles:
//   1) estático (siempre): cada mecanismo «Cubierto» existe en database/migrations/hospital;
//   2) ejecución (si SHI_PG_DSN está definido): carga las migraciones en un esquema temporal e intenta romper cada regla.
// Uso desde la raíz del repo: php docs/modulos/verificacion-bd-postgresql/semana-02/verificar-requisitos.php
$dir = dirname(__DIR__, 4) . '/database/migrations/hospital';
$archivos = glob("$dir/*.php") ?: [];
sort($archivos);
$migraciones = implode("\n", array_map('file_get_contents', $archivos));
if ($migraciones === '') { fwrite(STDERR, "No encontré migraciones en $dir\n"); exit(2); }
$documento = (string) file_get_contents(__DIR__ . '/semana-02-requisitos-de-datos.md');
$fallas = 0;

// ---- 1) Estático ----
preg_match_all('/^\| (RD-\d+) \|.*\| `([^`]+)` \|.*\| (Cubierto) \|$/mu', $documento, $filas, PREG_SET_ORDER);
$ok = 0;
foreach ($filas as [, $id, $mecanismo]) {
    if (preg_match('/^(\w+)\.(\w+) → (\w+)$/u', $mecanismo, $fk)) {
        $existe = preg_match('/CREATE TABLE ' . $fk[1] . ' \((?:(?!\);).)*\b' . $fk[2] . ' uuid[^,]*REFERENCES ' . $fk[3] . '\(/s', $migraciones) === 1;
    } elseif (preg_match('/^(\w+)\.(\w+) NOT NULL$/', $mecanismo, $nn)) {
        $existe = preg_match('/CREATE TABLE ' . $nn[1] . ' \((?:(?!\);).)*\b' . $nn[2] . ' \w+ NOT NULL/s', $migraciones) === 1;
    } else {
        $existe = str_contains($migraciones, $mecanismo);
    }
    $ok += $existe ? 1 : 0;
    if (!$existe) { $fallas++; echo "  FALLA estática: $id «{$mecanismo}» no está en las migraciones\n"; }
}
printf("Estático: %d/%d mecanismos encontrados en las migraciones\n", $ok, count($filas));

// ---- 2) Ejecución en PostgreSQL ----
$dsn = getenv('SHI_PG_DSN');
if (!$dsn || !extension_loaded('pdo_pgsql')) { echo "Ejecución: OMITIDA (requiere la extensión pdo_pgsql y SHI_PG_DSN, SHI_PG_USER, SHI_PG_PASS)\n"; exit($fallas ? 1 : 0); }
$pdo = new PDO($dsn, getenv('SHI_PG_USER') ?: null, getenv('SHI_PG_PASS') ?: null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$esquema = 'verif_s2_' . getmypid();
$pdo->exec("CREATE SCHEMA $esquema; SET search_path TO $esquema, public");
try {
    foreach ($archivos as $archivo) {   // mismo SQL que ejecuta Laravel: statement('...') y unprepared(<<<'SQL' ... SQL)
        preg_match_all("/->statement\('([^']*)'\)|<<<'SQL'\R(.*?)\R\s*SQL\);/s", (string) file_get_contents($archivo), $sql, PREG_SET_ORDER);
        foreach ($sql as $s) { $pdo->exec(($s[1] ?? '') !== '' ? $s[1] : $s[2]); }
    }
    $u = fn (string $n) => "'00000000-0000-0000-0000-0000000000$n'";
    $pdo->exec("INSERT INTO local_user_refs (local_user_ref_id, central_user_id, membership_id, display_name, role_code, synced_at) VALUES
        ({$u('a1')}, gen_random_uuid(), gen_random_uuid(), 'Dra. Ana Gómez', 'doctor', now()), ({$u('a2')}, gen_random_uuid(), gen_random_uuid(), 'Enf. Luis Paz', 'nurse', now());
      INSERT INTO specialties (specialty_id, code, name) VALUES ({$u('5a')}, 'MI', 'Medicina interna'), ({$u('5b')}, 'PED', 'Pediatría');
      INSERT INTO doctor_profiles (doctor_profile_id, local_user_ref_id, license_number, valid_from) VALUES ({$u('d1')}, {$u('a1')}, '12345', '2026-01-01');
      INSERT INTO doctor_specialties (doctor_profile_id, specialty_id, is_primary, valid_from) VALUES ({$u('d1')}, {$u('5a')}, true, '2026-01-01');
      INSERT INTO local_patients (local_patient_id, global_id, code, given_name, family_name, birth_date, sex) VALUES
        ({$u('1a')}, gen_random_uuid(), 'P-001', 'Rosa', 'Díaz', '1970-03-02', 'female'), ({$u('1b')}, gen_random_uuid(), 'P-002', 'Juan', 'Cruz', '2015-07-09', 'male');
      INSERT INTO care_episodes (care_episode_id, local_patient_id, type, started_at, reason) VALUES
        ({$u('e1')}, {$u('1a')}, 'inpatient', now() - interval '2 days', 'Neumonía'), ({$u('e2')}, {$u('1b')}, 'inpatient', now() - interval '3 hours', 'Fiebre');
      INSERT INTO medical_records (medical_record_id, local_patient_id, record_number) VALUES ({$u('7a')}, {$u('1a')}, 'EXP-001'), ({$u('7b')}, {$u('1b')}, 'EXP-002');
      INSERT INTO care_assignments (care_episode_id, doctor_profile_id, specialty_id, assigned_by_user_ref_id, scope, reason, source, valid_from)
        VALUES ({$u('e1')}, {$u('d1')}, {$u('5a')}, {$u('a2')}, '{\"rol\": \"tratante\"}', 'Ingreso', 'episode', now() - interval '1 day');
      INSERT INTO wards (ward_id, code, name, location) VALUES ({$u('9a')}, 'MED', 'Medicina', 'Nivel 2');
      INSERT INTO beds (bed_id, ward_id, code, type, status) VALUES ({$u('b1')}, {$u('9a')}, 'A1', 'general', 'occupied'), ({$u('b2')}, {$u('9a')}, 'A2', 'general', 'available');
      INSERT INTO bed_stays (bed_stay_id, care_episode_id, bed_id, assigned_by_user_ref_id, started_at, reason) VALUES ({$u('c1')}, {$u('e1')}, {$u('b1')}, {$u('a2')}, now() - interval '1 day', 'Ingreso');
      INSERT INTO appointments (appointment_id, local_patient_id, doctor_profile_id, specialty_id, starts_at, ends_at, reason) VALUES ({$u('f1')}, {$u('1b')}, {$u('d1')}, {$u('5a')}, '2026-12-01 08:00+00', '2026-12-01 08:30+00', 'Control');
      INSERT INTO clinical_notes (clinical_note_id, medical_record_id, care_episode_id, author_user_ref_id, note_type, content, version_no) VALUES ({$u('3a')}, {$u('7a')}, {$u('e1')}, {$u('a1')}, 'evolucion', 'Paciente estable', 1);");
    $cita = fn (string $p, string $s, string $ini, string $fin, string $motivo = 'Control') => "INSERT INTO appointments (local_patient_id, doctor_profile_id, specialty_id, starts_at, ends_at, reason) VALUES ($p, {$u('d1')}, $s, '2026-12-01 $ini+00', '2026-12-01 $fin+00', '$motivo')";
    $nota = fn (string $r, string $e, string $a, int $v, string $extra = "NULL, NULL") => "INSERT INTO clinical_notes (medical_record_id, care_episode_id, author_user_ref_id, note_type, content, version_no, supersedes_id, correction_reason) VALUES ($r, $e, $a, 'evolucion', 'Texto', $v, $extra)";
    $paciente = fn (string $codigo, string $nombre, string $nacio) => "INSERT INTO local_patients (global_id, code, given_name, family_name, birth_date, sex) VALUES (gen_random_uuid(), '$codigo', $nombre, 'Ruiz', $nacio, 'female')";
    $nada = fn (string $condicion, string $si) => "DO \$\$ BEGIN IF $condicion THEN RAISE EXCEPTION '$si'; END IF; END \$\$";
    // ID => [SQL que intenta romper la regla, SQLSTATE esperado, texto esperado en el error]. «sin error» marca las brechas.
    $criterios = [
        'RD-01' => ["INSERT INTO appointments (local_patient_id, doctor_profile_id, specialty_id, starts_at, ends_at, reason) VALUES (gen_random_uuid(), {$u('d1')}, {$u('5a')}, now(), now() + interval '1 hour', 'X')", '23503', 'appointments_local_patient_id_fkey'],
        'RD-02' => ["INSERT INTO beds (ward_id, code, type, status) VALUES ({$u('9a')}, 'A9', 'general', 'sucia')", '23514', 'beds_status_check'],
        'RD-03' => ["UPDATE care_episodes SET status = 'closed' WHERE care_episode_id = {$u('e1')}", 'brecha', null],   // el CHECK da NULL y pasa
        'RD-04' => [$cita($u('1a'), $u('5b'), '10:00', '10:30'), 'P0001', 'appointments_specialty_trigger'],
        'RD-05' => [$nota($u('7b'), $u('e1'), $u('a1'), 1), 'P0001', 'clinical_notes_record_episode_patient_trigger'],
        'RD-06' => [$paciente('P-001', "'Eva'", "'1990-01-01'"), '23505', 'local_patients_code_unique'],
        'RD-07' => ["INSERT INTO medical_records (local_patient_id, record_number) VALUES ({$u('1a')}, 'EXP-009')", '23505', 'medical_records_local_patient_id_unique'],
        'RD-08' => ["INSERT INTO doctor_profiles (local_user_ref_id, license_number, valid_from) VALUES ({$u('a2')}, '12345', '2026-01-01')", '23505', 'doctor_profiles_license_number_unique'],
        'RD-09' => [$cita($u('1a'), $u('5a'), '08:15', '08:45'), '23P01', 'appointments_doctor_active_period_exclusion'],
        'RD-10' => ["INSERT INTO bed_stays (care_episode_id, bed_id, assigned_by_user_ref_id, started_at, reason) VALUES ({$u('e2')}, {$u('b1')}, {$u('a2')}, now(), 'Traslado')", '23P01', 'bed_stays_bed_active_period_exclusion'],
        'RD-11' => ["INSERT INTO doctor_specialties (doctor_profile_id, specialty_id, is_primary, valid_from) VALUES ({$u('d1')}, {$u('5b')}, true, '2026-06-01')", '23P01', 'doctor_specialties_primary_period_exclusion'],
        'RD-12' => [$paciente('P-010', 'NULL', "'1990-01-01'"), '23502', 'given_name'],
        'RD-13' => [$cita($u('1a'), $u('5a'), '11:00', '11:30', '   '), '23514', 'appointments_reason_check'],
        'RD-14' => [$nota($u('7a'), $u('e1'), $u('a1'), 2, "{$u('3a')}, NULL"), '23514', 'clinical_notes_correction_check'],
        'RD-15' => [$paciente('P-011', "'Eva'", "current_date + 1"), '23514', 'local_patients_birth_date_check'],
        'RD-16' => ["INSERT INTO bed_stays (care_episode_id, bed_id, assigned_by_user_ref_id, started_at, reason) VALUES ({$u('e2')}, {$u('b2')}, NULL, now(), 'Ingreso')", '23502', 'assigned_by_user_ref_id'],
        'RD-17' => [$nota($u('7a'), $u('e1'), $u('a2'), 1), 'P0001', 'clinical_notes_author_assignment_trigger'],
        'RD-18' => [$nota($u('7a'), $u('e1'), $u('a1'), 3), 'P0001', 'clinical_notes_version_coherence_trigger'],
        'RD-19' => [$nada("EXISTS (SELECT 1 FROM information_schema.tables t WHERE t.table_schema = current_schema() AND NOT EXISTS (SELECT 1 FROM information_schema.columns c WHERE c.table_schema = t.table_schema AND c.table_name = t.table_name AND c.column_name = 'created_at'))", 'falta_created_at'), null, null],
        'RD-20' => ["DELETE FROM appointments WHERE appointment_id = {$u('f1')}", 'P0001', 'appointments_delete_protection'],
        'RD-21' => ["DELETE FROM medical_records WHERE medical_record_id = {$u('7b')}", 'P0001', 'clinical_notes_delete_protection'],
        'RD-22' => ["UPDATE clinical_notes SET status = 'signed', signed_at = now() WHERE clinical_note_id = {$u('3a')}; UPDATE clinical_notes SET content = 'Cambiado' WHERE clinical_note_id = {$u('3a')}", 'P0001', 'clinical_notes_signed_immutable'],
        'RD-23' => ["UPDATE bed_stays SET ended_at = now() WHERE bed_stay_id = {$u('c1')}; UPDATE bed_stays SET reason = 'Otro' WHERE bed_stay_id = {$u('c1')}", 'P0001', 'bed_stays_finalized_immutable'],
        'RD-24' => ["DELETE FROM local_patients WHERE local_patient_id = {$u('1a')}", '23503', 'violates foreign key'],
        'RD-25' => ["INSERT INTO bed_stays (care_episode_id, bed_id, assigned_by_user_ref_id, started_at, reason) VALUES ({$u('e2')}, {$u('b2')}, {$u('a2')}, now(), 'Ingreso'); "
                    . $nada("(SELECT status FROM beds WHERE bed_id = {$u('b2')}) <> 'available'", 'cama_sincronizada'), 'brecha', null],
        'RD-26' => [$nada("EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = current_schema() AND table_name LIKE '%audit%')", 'hay_auditoria'), 'brecha', null],
    ];
    [$cumplen, $brechas] = [0, 0];
    $pdo->beginTransaction();
    foreach ($criterios as $id => [$sql, $estado, $texto]) {
        $pdo->exec('SAVEPOINT criterio');
        try { $pdo->exec($sql); $obtenido = ['sin error', '']; } catch (PDOException $e) { $obtenido = [$e->errorInfo[0] ?? $e->getCode(), $e->getMessage()]; }
        $pdo->exec('ROLLBACK TO SAVEPOINT criterio');
        $bien = ($estado === null || $estado === 'brecha') ? $obtenido[0] === 'sin error' : ($obtenido[0] === $estado && str_contains($obtenido[1], (string) $texto));
        if ($estado === 'brecha') { $brechas += $bien ? 1 : 0; } else { $cumplen += $bien ? 1 : 0; }
        $fallas += $bien ? 0 : 1;
        printf("  %-6s %-6s %s\n", $id, $bien ? ($estado === 'brecha' ? 'BRECHA' : 'PASA') : 'FALLA', $estado === 'brecha' ? 'confirmada: la base hoy lo permite' : "esperado " . ($estado ?? "sin error") . " · obtenido {$obtenido[0]}");
    }
    $pdo->rollBack();
    $nBrechas = count(array_filter($criterios, fn ($c) => $c[1] === 'brecha'));
    printf("Ejecución: %d/%d criterios cumplidos · %d/%d brechas confirmadas\n", $cumplen, count($criterios) - $nBrechas, $brechas, $nBrechas);
} finally {
    $pdo->exec("SET search_path TO public; DROP SCHEMA $esquema CASCADE");
}
echo $fallas === 0 ? "Resultado: requisitos verificados\n" : "Resultado: $fallas diferencias\n";
exit($fallas ? 1 : 0);
