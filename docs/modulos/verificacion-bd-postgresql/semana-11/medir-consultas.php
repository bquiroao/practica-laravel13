<?php
declare(strict_types=1);

// Semana 11 — EXPLAIN ANALYZE de 4 consultas frecuentes antes y después de la migración de índices, y rollback.
// BORRA y recrea la base HOSPITAL de .env: exige una base *_test y --si-borrar-base-de-prueba. Carga datos de prueba (unos segundos).
// Uso desde la raíz del repo (con composer install): php docs/modulos/verificacion-bd-postgresql/semana-11/medir-consultas.php --si-borrar-base-de-prueba [--mostrar-planes]
use Illuminate\Support\Facades\{Artisan, DB};

$raiz = dirname(__DIR__, 4);
require "$raiz/vendor/autoload.php";
(require "$raiz/bootstrap/app.php")->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$base = (string) config('database.connections.hospital.database');
if (!in_array('--si-borrar-base-de-prueba', $argv, true) || !str_ends_with($base, '_test')) { exit("No se ejecutó: hace falta una base HOSPITAL *_test y --si-borrar-base-de-prueba (base actual: $base).\n"); }
$fallas = 0;
function ver(string $id, bool $ok, string $que): void { $GLOBALS['fallas'] += $ok ? 0 : 1; printf("%-6s %-5s %s\n", $id, $ok ? 'PASA' : 'FALLA', $que); }
$migrar = fn (string $orden, string $ruta, array $extra = []) => Artisan::call($orden, ['--database' => 'hospital', '--path' => $ruta, '--realpath' => true, '--force' => true] + $extra);
$migrar('migrate:fresh', database_path('migrations/hospital'));
$db = DB::connection('hospital');
// Datos de prueba: 100 000 pacientes (2 % pendientes de vincular), 300 000 tomas en 1 000 episodios, 60 000 citas de 40 médicos.
// Los triggers de integridad se apagan solo durante la carga (session_replication_role), como en una restauración.
$db->unprepared("SET session_replication_role = replica;
  INSERT INTO local_user_refs (local_user_ref_id, central_user_id, membership_id, display_name, role_code, synced_at) SELECT ('00000000-0000-0000-0000-' || lpad(i::text, 12, '0'))::uuid, gen_random_uuid(), gen_random_uuid(), 'Usuario ' || i, 'physician', now() FROM generate_series(1, 40) i;
  INSERT INTO specialties (specialty_id, code, name) VALUES ('00000000-0000-0000-0001-000000000001', 'MI', 'Medicina interna');
  INSERT INTO doctor_profiles (doctor_profile_id, local_user_ref_id, license_number, valid_from) SELECT ('00000000-0000-0000-0002-' || lpad(i::text, 12, '0'))::uuid, ('00000000-0000-0000-0000-' || lpad(i::text, 12, '0'))::uuid, 'COL-' || i, '2020-01-01' FROM generate_series(1, 40) i;
  INSERT INTO local_patients (local_patient_id, global_id, code, given_name, family_name, birth_date, sex, mpi_link_status, created_at)
    SELECT ('00000000-0000-0000-0003-' || lpad(i::text, 12, '0'))::uuid, gen_random_uuid(), 'P-' || i, (ARRAY['Ana','Luis','María','José','Carmen'])[1 + i % 5],
           (ARRAY['López','Pérez','García','Morales','Hernández','Ramírez','Castillo','Orellana'])[1 + i % 8] || ' ' || i, date '1950-01-01' + i % 25000, 'female',
           CASE WHEN i % 50 = 0 THEN 'pending' ELSE 'linked' END, now() - (i || ' minutes')::interval FROM generate_series(1, 100000) i;
  INSERT INTO medical_records (medical_record_id, local_patient_id, record_number) SELECT ('00000000-0000-0000-0004-' || lpad(i::text, 12, '0'))::uuid, ('00000000-0000-0000-0003-' || lpad(i::text, 12, '0'))::uuid, 'EXP-' || i FROM generate_series(1, 1000) i;
  INSERT INTO care_episodes (care_episode_id, local_patient_id, type, started_at, reason) SELECT ('00000000-0000-0000-0005-' || lpad(i::text, 12, '0'))::uuid, ('00000000-0000-0000-0003-' || lpad(i::text, 12, '0'))::uuid, 'inpatient', now() - interval '30 days', 'Ingreso' FROM generate_series(1, 1000) i;
  INSERT INTO vital_signs (medical_record_id, care_episode_id, recorded_by_user_ref_id, measured_at, heart_rate, oxygen_saturation)
    SELECT ('00000000-0000-0000-0004-' || lpad((1 + i % 1000)::text, 12, '0'))::uuid, ('00000000-0000-0000-0005-' || lpad((1 + i % 1000)::text, 12, '0'))::uuid, '00000000-0000-0000-0000-000000000001', now() - (i || ' minutes')::interval, 60 + i % 40, 90 + i % 10 FROM generate_series(1, 300000) i;
  INSERT INTO appointments (local_patient_id, doctor_profile_id, specialty_id, starts_at, ends_at, reason, status)
    SELECT ('00000000-0000-0000-0003-' || lpad((1 + i % 100000)::text, 12, '0'))::uuid, ('00000000-0000-0000-0002-' || lpad((1 + i % 40)::text, 12, '0'))::uuid, '00000000-0000-0000-0001-000000000001',
           timestamptz '2026-01-01 07:00+00' + ((i / 40) * 30 || ' minutes')::interval, timestamptz '2026-01-01 07:30+00' + ((i / 40) * 30 || ' minutes')::interval, 'Control', 'completed' FROM generate_series(0, 59999) i;
  SET session_replication_role = origin; ANALYZE");
$consultas = [
    'C1' => ["Buscar paciente por apellido", "SELECT local_patient_id, code, given_name, family_name FROM local_patients WHERE lower(family_name) LIKE 'orellana 77%' ORDER BY lower(family_name), given_name LIMIT 20"],
    'C2' => ["Pendientes de vincular con CENTRAL", "SELECT local_patient_id, code FROM local_patients WHERE mpi_link_status = 'pending' ORDER BY created_at, local_patient_id LIMIT 100"],
    'C3' => ["Últimas 10 tomas de un episodio", "SELECT vital_sign_id, measured_at, heart_rate, oxygen_saturation FROM vital_signs WHERE care_episode_id = '00000000-0000-0000-0005-000000000500' ORDER BY measured_at DESC, vital_sign_id DESC LIMIT 10"],
    'C4' => ["Agenda de un médico en un día", "SELECT appointment_id, starts_at, local_patient_id FROM appointments WHERE doctor_profile_id = '00000000-0000-0000-0002-000000000007' AND starts_at >= '2026-01-10' AND starts_at < '2026-01-11' ORDER BY starts_at"],
];
$medir = function (string $sql) use ($db): array {
    $t = []; $plan = '';
    for ($i = 0; $i < 7; $i++) { $plan = implode("\n", array_map(fn ($r) => ((array) $r)['QUERY PLAN'], $db->select("EXPLAIN (ANALYZE, BUFFERS) $sql"))); preg_match('/Execution Time: ([\d.]+)/', $plan, $m); $t[] = (float) $m[1]; }
    if (in_array('--mostrar-planes', $GLOBALS['argv'], true)) { echo "  $sql\n" . preg_replace('/^/m', '    ', $plan) . "\n"; }
    sort($t); preg_match_all('/(Seq Scan|Index Only Scan|Bitmap Index Scan|Bitmap Heap Scan|Index Scan|Sort)( using \w+| on \w+)?/', $plan, $n);
    return [$t[3], implode(' + ', array_unique(array_map('trim', $n[0])))];
};
$antes = array_map(fn ($c) => $medir($c[1]), $consultas);
$migrar('migrate', __DIR__ . '/propuesta'); $db->unprepared('ANALYZE');
$despues = array_map(fn ($c) => $medir($c[1]), $consultas);
foreach ($consultas as $id => [$nombre]) {
    ver("IDX-$id", $despues[$id][0] < $antes[$id][0] && str_contains($despues[$id][1], 'Index'), sprintf('%s: %.2f ms → %.2f ms (%.0f veces). Antes: %s. Después: %s', $nombre, $antes[$id][0], $despues[$id][0], $antes[$id][0] / max($despues[$id][0], 0.001), $antes[$id][1], $despues[$id][1]));
}
$migrar('migrate:rollback', __DIR__ . '/propuesta', ['--step' => 1]);
$quedan = (int) $db->scalar("SELECT count(*) FROM pg_indexes WHERE indexname IN ('local_patients_family_name_lower_pattern_index', 'local_patients_mpi_pending_index', 'vital_signs_episode_measured_index', 'appointments_doctor_starts_index')");
ver('IDX-RB', $quedan === 0, "Rollback de la migración: quedan $quedan de los 4 índices");
echo $fallas === 0 ? "\nResultado: los 4 índices bajan el tiempo de su consulta y el rollback los quita.\n" : "\nResultado: $fallas comprobaciones fallan.\n";
exit($fallas ? 1 : 0);
