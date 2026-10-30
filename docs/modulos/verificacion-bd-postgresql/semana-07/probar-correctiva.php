<?php
declare(strict_types=1);

// Semana 7 — Prueba la migración correctiva en PostgreSQL: ANTES (huecos), DESPUÉS de aplicarla (corregidos) y otra vez ANTES tras el rollback.
// BORRA y recrea la base HOSPITAL de .env, por eso exige una base que termine en _test y el argumento --si-borrar-base-de-prueba.
// Uso desde la raíz del repo (con composer install): php docs/modulos/verificacion-bd-postgresql/semana-07/probar-correctiva.php --si-borrar-base-de-prueba
use Illuminate\Support\Facades\{Artisan, DB};

$raiz = dirname(__DIR__, 4);
require "$raiz/vendor/autoload.php";
(require "$raiz/bootstrap/app.php")->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$base = (string) config('database.connections.hospital.database');
if (!in_array('--si-borrar-base-de-prueba', $argv, true) || !str_ends_with($base, '_test')) { exit("No se ejecutó: hace falta una base HOSPITAL *_test y --si-borrar-base-de-prueba (base actual: $base).\n"); }
$fallas = 0;
function ver(string $id, bool $ok, string $que): void { $GLOBALS['fallas'] += $ok ? 0 : 1; printf("%-6s %-5s %s\n", $id, $ok ? 'PASA' : 'FALLA', $que); }
$migrar = fn (string $orden, array $extra = []) => Artisan::call($orden, ['--database' => 'hospital', '--realpath' => true, '--force' => true] + $extra);
$migrar('migrate:fresh', ['--path' => database_path('migrations/hospital')]);
$db = DB::connection('hospital');
$db->unprepared("INSERT INTO local_user_refs (local_user_ref_id, central_user_id, membership_id, display_name, role_code, synced_at) VALUES ('00000000-0000-0000-0000-000000000001', gen_random_uuid(), gen_random_uuid(), 'Enf. Ana', 'nurse', now());
    INSERT INTO local_patients (local_patient_id, global_id, code, given_name, family_name, birth_date, sex) VALUES ('00000000-0000-0000-0000-000000000010', gen_random_uuid(), 'P-1', 'Ana', 'López', '2000-01-01', 'female');
    INSERT INTO care_episodes (care_episode_id, local_patient_id, type, started_at, reason) VALUES ('00000000-0000-0000-0000-000000000015', '00000000-0000-0000-0000-000000000010', 'inpatient', now() - interval '2 days', 'Ingreso');
    INSERT INTO wards (ward_id, code, name, location) VALUES ('00000000-0000-0000-0000-000000000014', 'MED', 'Medicina', 'Nivel 1');
    INSERT INTO beds (bed_id, ward_id, code, type) VALUES ('00000000-0000-0000-0000-000000000016', '00000000-0000-0000-0000-000000000014', 'A1', 'standard')");
// Cada intento va en una transacción que se deshace: la base queda igual para el siguiente.
$intento = function (string $sql, ?string $consulta = null) use ($db) {
    $db->beginTransaction();
    try { $db->unprepared($sql); $r = $consulta === null ? 'aceptado' : $db->scalar($consulta); } catch (Throwable $e) { $r = 'rechazado'; }
    $db->rollBack();
    return $r;
};
$huecos = fn () => [
    $intento("UPDATE care_episodes SET status = 'closed' WHERE care_episode_id = '00000000-0000-0000-0000-000000000015'"),
    $intento("INSERT INTO local_patients (global_id, code, given_name, family_name, birth_date, sex) VALUES (gen_random_uuid(), ' ', '', '', '2000-01-01', 'male')"),
    $intento("INSERT INTO bed_stays (care_episode_id, bed_id, assigned_by_user_ref_id, started_at, reason) VALUES ('00000000-0000-0000-0000-000000000015', '00000000-0000-0000-0000-000000000016', '00000000-0000-0000-0000-000000000001', now() - interval '1 day', 'Ingreso')",
             "SELECT status FROM beds WHERE bed_id = '00000000-0000-0000-0000-000000000016'")];
$texto = fn (array $h) => "episodio cerrado sin fecha de fin: $h[0] · paciente con código y nombre vacíos: $h[1] · cama con estancia abierta: $h[2]";
$antes = $huecos();
ver('COR-01', $antes === ['aceptado', 'aceptado', 'available'], 'ANTES (migraciones de develop): ' . $texto($antes));
$migrar('migrate', ['--path' => __DIR__ . '/propuesta']);
$despues = $huecos();
ver('COR-02', $despues === ['rechazado', 'rechazado', 'occupied'], 'DESPUÉS de la migración correctiva: ' . $texto($despues));
$migrar('migrate:rollback', ['--path' => __DIR__ . '/propuesta', '--step' => 1]);
$deshecha = $huecos();
ver('COR-03', $deshecha === $antes && (int) $db->scalar("SELECT count(*) FROM pg_trigger WHERE tgname = 'bed_stays_occupy_bed'") === 0, 'Rollback: ' . $texto($deshecha) . ' (igual que antes, sin el trigger)');
echo $fallas === 0 ? "\nResultado: la migración corrige los 3 huecos y su rollback los deja como estaban.\n" : "\nResultado: $fallas comprobaciones fallan.\n";
exit($fallas ? 1 : 0);
