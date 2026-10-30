<?php
declare(strict_types=1);

// Semana 9 — Lista de calidad de los datos de prueba de la base HOSPITAL: completitud (COM), consistencia (CON) y realismo (REA).
//   Estático (siempre): el seeder de develop y la propuesta cubren las tablas de las migraciones.
//   PostgreSQL (si SHI_PG_DSN está definido): esquema temporal con las migraciones, se carga el seeder y se corren las consultas de la lista.
// Uso desde la raíz del repo: php docs/modulos/verificacion-bd-postgresql/semana-09/verificar-datos-demo.php [otro-seeder.sql]
$raiz = dirname(__DIR__, 4); $fallas = 0;
$semilla = $argv[1] ?? __DIR__ . '/propuesta/datos-demo-hospital.sql';
function ver(string $id, bool $ok, string $que): void { $GLOBALS['fallas'] += $ok ? 0 : 1; printf("%-7s %-5s %s\n", $id, $ok ? 'PASA' : 'FALLA', $que); }

// ---- Estático ----
$migraciones = glob("$raiz/database/migrations/hospital/*.php") ?: [];
$texto = implode("\n", array_map('file_get_contents', $migraciones));
preg_match_all('/CREATE TABLE (?:IF NOT EXISTS )?(\w+)/', $texto, $m); $tablas = array_values(array_unique($m[1]));
$sql = (string) @file_get_contents($semilla);
$sin = array_filter($tablas, fn ($t) => !preg_match("/INSERT INTO $t\b/", $sql));
ver('EST-01', $tablas !== [] && $sin === [], sprintf('El seeder propuesto inserta datos en las %d tablas de HOSPITAL (sin datos: %s)', count($tablas), implode(', ', $sin) ?: 'ninguna'));
$actual = $raiz . '/database/seeders/DatabaseSeeder.php';
if (is_file($actual)) {
    $c = (string) file_get_contents($actual);
    printf("%-7s %-5s %s\n", 'EST-02', 'INFO', 'DatabaseSeeder.php de develop: ' . (preg_match('/factory\(|::create\(|insert\(|INSERT INTO|\$this->call\(/i', $c) ? 'tiene datos' : 'no inserta ningún dato (solo un comentario)') . ', ' . count(file($actual)) . ' líneas');
}

// ---- PostgreSQL ----
$dsn = getenv('SHI_PG_DSN');
if (!$dsn || !extension_loaded('pdo_pgsql')) { echo "Ejecución: OMITIDA (requiere la extensión pdo_pgsql y SHI_PG_DSN, SHI_PG_USER, SHI_PG_PASS)\n"; exit($fallas ? 1 : 0); }
$pdo = new PDO($dsn, getenv('SHI_PG_USER') ?: null, getenv('SHI_PG_PASS') ?: null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$esquema = 'verif_s9_' . getmypid();
$pdo->exec("CREATE SCHEMA $esquema; SET search_path TO $esquema, public");
$n = fn (string $q): int => (int) $pdo->query($q)->fetchColumn();
try {
    foreach ($migraciones as $a) {
        preg_match_all("/->statement\('([^']*)'\)|<<<'SQL'\R(.*?)\R\s*SQL\);/s", (string) file_get_contents($a), $s, PREG_SET_ORDER);
        foreach ($s as $x) { $pdo->exec(($x[1] ?? '') !== '' ? $x[1] : $x[2]); }
    }
    try { $pdo->exec($sql); $cargo = true; } catch (\PDOException $e) { $cargo = false; $pdo->exec('ROLLBACK'); echo '        la base rechazó el seeder: ' . strtok($e->getMessage(), "\n") . "\n"; }
    ver('CAR-01', $cargo, 'El seeder se carga completo sobre las migraciones reales (la base acepta todos los datos)');
    if ($cargo) {
        // Completitud
        $vacias = array_filter($tablas, fn ($t) => $n("SELECT count(*) FROM $t") === 0);
        ver('COM-01', $vacias === [], 'Ninguna de las ' . count($tablas) . ' tablas queda vacía' . ($vacias ? ': ' . implode(', ', $vacias) : ''));
        $cob = fn (string $q, int $esperado) => $n("SELECT count(*) FROM ($q) x") === $esperado;
        ver('COM-02', $cob('SELECT DISTINCT type FROM care_episodes', 3) && $cob('SELECT DISTINCT status FROM care_episodes WHERE status IN (\'open\',\'closed\')', 2) && $cob('SELECT DISTINCT status FROM beds', 4)
            && $cob('SELECT DISTINCT status FROM appointments', 4) && $cob('SELECT DISTINCT status FROM clinical_notes', 2) && $cob('SELECT DISTINCT status FROM vital_signs WHERE status IN (\'draft\',\'validated\')', 2) && $cob('SELECT DISTINCT status FROM allergies WHERE status IN (\'active\',\'resolved\')', 2),
            'Están representados los 3 tipos de episodio, abierto/cerrado, los 4 estados de cama y de cita, nota borrador/firmada, signos borrador/validado y alergia activa/resuelta');
        ver('COM-03', $n('SELECT count(*) FROM local_patients p WHERE NOT EXISTS (SELECT 1 FROM medical_records r WHERE r.local_patient_id = p.local_patient_id)') === 0
            && $n('SELECT count(*) FROM wards w WHERE NOT EXISTS (SELECT 1 FROM beds b WHERE b.ward_id = w.ward_id)') === 0
            && $n("SELECT count(*) FROM care_episodes e WHERE status = 'open' AND NOT EXISTS (SELECT 1 FROM care_assignments a WHERE a.care_episode_id = e.care_episode_id AND a.status = 'active')") === 0,
            'Todo paciente tiene expediente, toda sala tiene camas y todo episodio abierto tiene médico asignado');
        ver('COM-04', $n("SELECT count(*) FROM local_patients WHERE birth_date > current_date - 365 * 5") >= 1 && $n("SELECT count(*) FROM local_patients WHERE birth_date < current_date - 365 * 65") >= 1
            && $n('SELECT count(*) FROM local_patients p WHERE NOT EXISTS (SELECT 1 FROM care_episodes e WHERE e.local_patient_id = p.local_patient_id)') >= 1,
            'Hay un niño menor de 5 años, un adulto mayor de 65 y un paciente sin episodio (solo con cita), para probar filtros y pantallas vacías');
        // Consistencia (lo que las llaves y los CHECK no obligan)
        ver('CON-01', $n("SELECT count(*) FROM beds b WHERE (b.status = 'occupied') <> EXISTS (SELECT 1 FROM bed_stays s WHERE s.bed_id = b.bed_id AND s.ended_at IS NULL)") === 0, 'Una cama figura «occupied» si y solo si tiene una estancia abierta');
        ver('CON-02', $n("SELECT count(*) FROM care_episodes e WHERE e.status = 'closed' AND (EXISTS (SELECT 1 FROM bed_stays s WHERE s.care_episode_id = e.care_episode_id AND s.ended_at IS NULL) OR EXISTS (SELECT 1 FROM care_assignments a WHERE a.care_episode_id = e.care_episode_id AND a.status = 'active'))") === 0, 'Un episodio cerrado no deja cama ocupada ni asignación activa');
        ver('CON-03', $n("SELECT count(*) FROM vital_signs v JOIN care_episodes e USING (care_episode_id) WHERE v.measured_at < e.started_at OR v.measured_at > COALESCE(e.ended_at, now())") === 0
            && $n("SELECT count(*) FROM diagnoses d JOIN care_episodes e USING (care_episode_id) WHERE d.onset_date < e.started_at::date") === 0, 'Signos y diagnósticos caen dentro del período de su episodio');
        ver('CON-04', $n("SELECT count(*) FROM appointments WHERE status IN ('scheduled','confirmed') AND starts_at < now()") === 0 && $n("SELECT count(*) FROM local_patients p JOIN care_episodes e USING (local_patient_id) WHERE e.started_at::date < p.birth_date") === 0, 'Ninguna cita pendiente está en el pasado y nadie tiene atención antes de nacer');
        ver('CON-05', $n("SELECT count(*) FROM local_patients WHERE code !~ '^P-[0-9]{6}$'") + $n("SELECT count(*) FROM medical_records WHERE record_number !~ '^EXP-[0-9]{4}-[0-9]{4}$'") + $n("SELECT count(*) FROM doctor_profiles WHERE license_number !~ '^[0-9]{5}$'") === 0, 'Códigos de paciente, número de expediente y colegiado siguen un mismo formato');
        // Realismo
        ver('REA-01', $n("SELECT count(*) FROM local_patients WHERE given_name || ' ' || family_name ~* '(prueba|test|asdf|lorem|xxx|dummy|paciente [0-9]|fulano|zutano|juan perez)' OR given_name !~ '^[[:upper:]][[:alpha:]]+( [[:upper:]][[:alpha:]]+)*$' OR family_name !~ '^[[:upper:]][[:alpha:]]+ [[:upper:]][[:alpha:]]+$'") === 0, 'Nombres de persona con dos apellidos, sin textos de relleno («Prueba», «Paciente 1», «asdf»)');
        ver('REA-02', $n("SELECT count(*) FROM vital_signs v JOIN medical_records r USING (medical_record_id) JOIN local_patients p USING (local_patient_id) WHERE temperature NOT BETWEEN 34 AND 42 OR heart_rate NOT BETWEEN 35 AND 200 OR respiratory_rate NOT BETWEEN 8 AND 60 OR systolic_bp NOT BETWEEN 70 AND 230 OR diastolic_bp NOT BETWEEN 40 AND 140 OR oxygen_saturation NOT BETWEEN 70 AND 100 OR glucose NOT BETWEEN 40 AND 600 OR (weight IS NOT NULL AND weight NOT BETWEEN 2 AND 250) OR (height IS NOT NULL AND height NOT BETWEEN 45 AND 210)") === 0, 'Los signos están en rangos que ocurren en personas vivas (más estrechos que los CHECK de la base)');
        ver('REA-03', $n("SELECT count(*) FROM vital_signs v JOIN medical_records r USING (medical_record_id) JOIN local_patients p USING (local_patient_id) WHERE weight IS NOT NULL AND height IS NOT NULL AND (weight / ((height / 100) ^ 2) NOT BETWEEN 12 AND 55 OR (age(p.birth_date) < interval '12 years' AND weight > 60))") === 0, 'Peso y talla son coherentes entre sí (IMC entre 12 y 55) y con la edad (un niño no pesa más de 60 kg)');
        ver('REA-04', $n("SELECT count(*) FROM diagnoses WHERE code !~ '^[A-TV-Z][0-9]{2}(\\.[0-9A-Z]{1,4})?\$' OR length(description) < 10 OR lower(description) = lower(code)") === 0, 'Diagnósticos con código CIE-10 de forma válida y descripción real');
        ver('REA-05', $n("SELECT count(*) FROM vital_signs WHERE temperature >= 38") >= 1 && $n("SELECT count(*) FROM vital_signs WHERE systolic_bp >= 140") >= 1 && $n("SELECT count(*) FROM vital_signs WHERE oxygen_saturation < 94") >= 1 && $n("SELECT count(*) FROM vital_signs WHERE heart_rate > 100") >= 1 && $n("SELECT count(*) FROM vital_signs WHERE temperature < 38 AND COALESCE(systolic_bp, 0) < 140 AND oxygen_saturation >= 94") >= 3,
            'Hay tomas normales y también fiebre, hipertensión, SpO2 baja y taquicardia para probar los umbrales y las alertas');
        ver('REA-06', $n("SELECT count(*) FROM care_episodes WHERE started_at < now() - interval '90 days' OR started_at > now()") + $n("SELECT count(*) FROM appointments WHERE starts_at > now() + interval '30 days'") === 0, 'Las fechas son de «este mes»: episodios de los últimos 90 días y citas en los próximos 30');
        // Límites: lo que la base NO impide (por eso el seeder y la API deben cuidar el realismo)
        $id = fn (string $k) => "md5('$k')::uuid";
        $toma = fn (string $quien, string $extra) => "INSERT INTO vital_signs (medical_record_id, care_episode_id, recorded_by_user_ref_id, $extra, measured_at, recorded_at) VALUES ({$id('exp:1')}, {$id('epi:1')}, {$id($quien)}, %s, now() - interval '1 hour', now())";
        $intento = function (string $q) use ($pdo): string { try { $pdo->exec('BEGIN'); $pdo->exec($q); return 'acepta'; } catch (\PDOException $e) { return $e->getMessage(); } finally { $pdo->exec('ROLLBACK'); } };
        ver('LIM-01', $intento(sprintf($toma('usr:lucia', 'heart_rate, temperature, oxygen_saturation'), '299, 44.9, 5')) === 'acepta', 'Límite conocido: la base acepta FC 299, 44.9 °C y SpO2 5 % (sus CHECK son físicos, no clínicos); el realismo lo cuidan el seeder y la API');
        ver('LIM-02', str_contains($intento(sprintf($toma('usr:rosa', 'heart_rate'), '80')), 'vital_signs_author_assignment_trigger'), 'Límite conocido: una enfermera no puede registrar signos, la base exige un médico con asignación activa en el episodio');
    }
} finally {
    try { $pdo->exec('ROLLBACK'); } catch (\PDOException) { }
    $pdo->exec("SET search_path TO public; DROP SCHEMA $esquema CASCADE");
}
echo $fallas === 0 ? "\nResultado: la lista de calidad se cumple.\n" : "\nResultado: $fallas verificaciones fallaron.\n";
exit($fallas ? 1 : 0);
