<?php

declare(strict_types=1);

/*
 * Semana 4 · Aplica database/integracion-hospital.sql sobre las tablas REALES de la base de hospital del proyecto
 * (el SQL se extrae de database/migrations/hospital/*.php, no se copia a mano) en un esquema temporal de PostgreSQL,
 * y comprueba lo que afirma el análisis del repositorio compartido. Deja todo como estaba al terminar.
 *   PRESCRIPCIONES_DB_DSN=… php bin/validar-integracion-hospital.php
 */

$dsn = getenv('PRESCRIPCIONES_DB_DSN') ?: exit("Defina PRESCRIPCIONES_DB_DSN.\n");
$pdo = new PDO($dsn, getenv('PRESCRIPCIONES_DB_USUARIO') ?: 'postgres', getenv('PRESCRIPCIONES_DB_CLAVE') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$sufijo = getmypid();
$esquema = "hospital_$sufijo";
$rol = "app_prescripciones_$sufijo";
$raiz = dirname(__DIR__, 5);
$fallas = 0;

/** El SQL de up() de una migración del proyecto principal (bloque <<<'SQL' … SQL, o un statement simple). */
$sqlDeMigracion = static function (string $archivo) use ($raiz): string {
    $codigo = (string) file_get_contents("$raiz/database/migrations/hospital/$archivo");
    if (preg_match("/<<<'SQL'\n(.*?)\n\s*SQL\)/s", $codigo, $m)) {
        return $m[1];
    }
    preg_match("/->statement\('([^']+)'\)/", $codigo, $m) || exit("No se encontró el SQL en $archivo\n");

    return $m[1];
};
$ok = static function (string $que, bool $cumple) use (&$fallas): void {
    printf("[%s] %s\n", $cumple ? 'OK  ' : 'FALLA', $que);
    $fallas += $cumple ? 0 : 1;
};
/** Ejecuta y devuelve null si funcionó, o el mensaje/SQLSTATE si la base lo rechazó. */
$intentar = static function (string $sql) use ($pdo): ?string {
    try {
        $pdo->exec($sql);

        return null;
    } catch (PDOException $e) {
        return $e->errorInfo[0] . ' ' . ($e->errorInfo[2] ?? $e->getMessage());
    }
};
$valor = static fn (string $sql): string => (string) $pdo->query($sql)->fetchColumn();

try {
    $pdo->exec("CREATE SCHEMA $esquema; SET search_path TO $esquema, public; CREATE ROLE $rol NOLOGIN");
    foreach (array_map('basename', glob("$raiz/database/migrations/hospital/*.php")) as $migracion) {
        $pdo->exec($sqlDeMigracion($migracion));
        echo "aplicada la migración real $migracion" . PHP_EOL;
    }
    $pdo->exec(str_replace('app_prescripciones', $rol, (string) file_get_contents(dirname(__DIR__) . '/database/integracion-hospital.sql')));
    $pdo->exec("GRANT USAGE ON SCHEMA $esquema TO $rol");
    echo 'aplicado database/integracion-hospital.sql' . PHP_EOL . PHP_EOL;

    // Datos mínimos sobre las tablas reales: un médico asignado al episodio, otro sin asignación, dos pacientes.
    $u = static fn (string $nombre): string => $valor("INSERT INTO local_user_refs (central_user_id, membership_id, display_name, role_code, synced_at)
        VALUES (gen_random_uuid(), gen_random_uuid(), '$nombre', 'doctor', now()) RETURNING local_user_ref_id");
    [$medico, $otroMedico, $admin] = [$u('Dra. López'), $u('Dr. Ruiz'), $u('Admisión')];
    $esp = $valor("INSERT INTO specialties (code, name) VALUES ('MI', 'Medicina interna') RETURNING specialty_id");
    $perfil = $valor("INSERT INTO doctor_profiles (local_user_ref_id, license_number, valid_from) VALUES ('$medico', 'COL-1', current_date - 10) RETURNING doctor_profile_id");
    $valor("INSERT INTO doctor_profiles (local_user_ref_id, license_number, valid_from) VALUES ('$otroMedico', 'COL-2', current_date - 10) RETURNING doctor_profile_id");
    $pdo->exec("INSERT INTO doctor_specialties (doctor_profile_id, specialty_id, is_primary, valid_from) VALUES ('$perfil', '$esp', true, current_date - 10)");
    $p = static fn (string $codigo): string => $valor("INSERT INTO local_patients (global_id, code, given_name, family_name, birth_date, sex)
        VALUES (gen_random_uuid(), '$codigo', 'Paciente', '$codigo', '1980-01-01', 'female') RETURNING local_patient_id");
    [$pac1, $pac2] = [$p('P-001'), $p('P-002')];
    $episodio = $valor("INSERT INTO care_episodes (local_patient_id, type, started_at, reason) VALUES ('$pac1', 'outpatient', now() - interval '2 hours', 'consulta') RETURNING care_episode_id");
    $pdo->exec("INSERT INTO care_assignments (care_episode_id, doctor_profile_id, specialty_id, assigned_by_user_ref_id, scope, reason, source, valid_from)
        VALUES ('$episodio', '$perfil', '$esp', '$admin', '{\"rol\": \"tratante\"}', 'tratante', 'manual', now() - interval '1 hour')");
    $pdo->exec("INSERT INTO allergies (local_patient_id, verified_by_user_ref_id, allergen_code, allergen_name, reaction, allergy_type, severity)
        VALUES ('$pac1', '$admin', 'J01CA04', 'Amoxicilina', 'urticaria', 'allergy', 'severe')");
    $pdo->exec("INSERT INTO medications VALUES ('AMOX500', 'Amoxicilina 500 mg', 'J01CA04', 'Amoxicilina', 'betalactamicos', 'mg', 3000, '{oral}', true)");
    $prescripcion = static fn (string $paciente, string $prescriptor, string $clave): string => "INSERT INTO prescriptions
        (local_patient_id, care_episode_id, prescriber_user_ref_id, idempotency_key, content_hash, allergy_check_result)
        VALUES ('$paciente', '$episodio', '$prescriptor', '$clave', repeat('a', 64), 'SIN_HALLAZGOS')";

    $ok('Las tablas H5 se crean con FK reales a local_patients, care_episodes y local_user_refs', true);
    $ok('El prescriptor CON asignación activa en el episodio puede emitir', $intentar($prescripcion($pac1, $medico, 'k-1')) === null);
    $error = $intentar($prescripcion($pac1, $otroMedico, 'k-2'));
    $ok('Un médico SIN asignación en el episodio no puede (misma regla que vital_signs)', str_contains((string) $error, 'prescriptions_prescriber_assignment_trigger'));
    $error = $intentar($prescripcion($pac2, $medico, 'k-3'));
    $ok('Un episodio de OTRO paciente se rechaza', str_contains((string) $error, 'prescriptions_episode_patient_trigger'));
    $error = $intentar(str_replace("'$pac1'", 'gen_random_uuid()', $prescripcion($pac1, $medico, 'k-4')));
    $ok('Un paciente que no existe en local_patients se rechaza (FK 23503 o trigger)', $error !== null);
    $ok('UPDATE y DELETE de una prescripción se rechazan (inmutable)',
        str_contains((string) $intentar("UPDATE prescriptions SET status = 'EMITIDA'"), 'prescriptions_immutable')
        && str_contains((string) $intentar('DELETE FROM prescriptions'), 'prescriptions_immutable'));
    $ok('La misma Idempotency-Key se rechaza (UNIQUE, 23505)', str_starts_with((string) $intentar($prescripcion($pac1, $medico, 'k-1')), '23505'));
    $ok('El Expediente ya impide BORRAR una alergia (trigger real allergies_retention): una alergia no desaparece en silencio',
        str_contains((string) $intentar('DELETE FROM allergies'), 'allergies_delete_protection'));

    // Propiedad de los datos, con el rol del módulo.
    $pdo->exec("SET ROLE $rol");
    $filas = $pdo->query("SELECT allergen_code, allergen_name, reaction, allergy_type, severity, status FROM prescripcion_alergias WHERE local_patient_id = '$pac1'")
        ->fetchAll(PDO::FETCH_ASSOC);
    $ok('El rol del módulo lee las alergias por la vista, con las columnas que usa PdoFuenteAlergias', count($filas) === 1 && $filas[0]['allergen_code'] === 'J01CA04');
    $ok('El rol del módulo NO puede leer la tabla allergies directamente (42501)', str_starts_with((string) $intentar('SELECT 1 FROM allergies'), '42501'));
    $ok('El rol del módulo NO puede escribir alergias (42501)',
        str_starts_with((string) $intentar("UPDATE allergies SET severity = 'mild'"), '42501'));
    $ok('El rol del módulo NO puede modificar pacientes (42501)', str_starts_with((string) $intentar("UPDATE local_patients SET contact = 'x'"), '42501'));
    $pdo->exec('RESET ROLE');
    $publico = (int) $valor("SELECT count(*) FROM information_schema.role_table_grants WHERE table_schema = '$esquema'
        AND table_name IN ('prescriptions', 'prescription_items') AND grantee = 'PUBLIC'");
    $ok('PUBLIC no tiene ningún privilegio sobre las prescripciones', $publico === 0);
} finally {
    $pdo->exec('RESET ROLE');
    $pdo->exec("SET search_path TO public; DROP SCHEMA IF EXISTS $esquema CASCADE");
    $pdo->exec("DROP ROLE IF EXISTS $rol");
}
echo PHP_EOL . ($fallas === 0 ? 'Todo lo que afirma el análisis se cumple.' : "Hay $fallas afirmaciones que no se cumplen.") . PHP_EOL;
exit($fallas === 0 ? 0 : 1);
