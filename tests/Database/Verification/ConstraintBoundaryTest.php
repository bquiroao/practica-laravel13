<?php

namespace Tests\Database\Verification;

use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

// Semana 15 (Verificación BD): caja blanca. Valores límite de los CHECK de HOSPITAL y defectos abiertos de la auditoría de la semana 7.
#[Group('database-integrity')]
class ConstraintBoundaryTest extends TestCase
{
    private const USER = '00000000-0000-0000-0000-000000000001';
    private const EPISODE = '00000000-0000-0000-0000-000000000011';
    private const RECORD = '00000000-0000-0000-0000-000000000013';

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertPostgreSqlIntegrityPrerequisites();
        Artisan::call('migrate:fresh', ['--database' => 'hospital', '--path' => database_path('migrations/hospital'), '--realpath' => true, '--force' => true]);
        $db = DB::connection('hospital');
        $db->insert("INSERT INTO local_user_refs (local_user_ref_id, central_user_id, membership_id, display_name, role_code, synced_at) VALUES ('".self::USER."', gen_random_uuid(), gen_random_uuid(), 'Dr. Ada', 'physician', now())");
        $db->insert("INSERT INTO doctor_profiles (doctor_profile_id, local_user_ref_id, license_number, valid_from) VALUES ('00000000-0000-0000-0000-000000000002', '".self::USER."', 'MED-V15', current_date - 1)");
        $db->insert("INSERT INTO specialties (specialty_id, code, name) VALUES ('00000000-0000-0000-0000-000000000003', 'MED-INT', 'Medicina interna')");
        $db->insert("INSERT INTO doctor_specialties (doctor_profile_id, specialty_id, is_primary, valid_from) VALUES ('00000000-0000-0000-0000-000000000002', '00000000-0000-0000-0000-000000000003', true, current_date - 1)");
        $db->insert("INSERT INTO local_patients (local_patient_id, global_id, code, given_name, family_name, birth_date, sex) VALUES ('00000000-0000-0000-0000-000000000010', gen_random_uuid(), 'P-V15', 'Pat', 'Patient', '2000-01-01', 'female')");
        $db->insert("INSERT INTO care_episodes (care_episode_id, local_patient_id, type, started_at, reason) VALUES ('".self::EPISODE."', '00000000-0000-0000-0000-000000000010', 'inpatient', now() - interval '1 day', 'Ingreso')");
        $db->insert("INSERT INTO care_assignments (care_episode_id, doctor_profile_id, specialty_id, assigned_by_user_ref_id, scope, reason, source, valid_from) VALUES ('".self::EPISODE."', '00000000-0000-0000-0000-000000000002', '00000000-0000-0000-0000-000000000003', '".self::USER."', '{\"notes\":true}'::jsonb, 'Ingreso', 'manual', now() - interval '1 day')");
        $db->insert("INSERT INTO medical_records (medical_record_id, local_patient_id, record_number, opened_at) VALUES ('".self::RECORD."', '00000000-0000-0000-0000-000000000010', 'MR-V15', now() - interval '1 day')");
    }

    public function test_each_vital_sign_range_accepts_its_limits_and_rejects_the_next_value(): void
    {
        foreach ([
            'temperature' => [[25.1, 45], [25, 45.1]], 'heart_rate' => [[1, 300], [0, 301]], 'respiratory_rate' => [[1, 100], [0, 101]],
            'systolic_bp' => [[1, 300], [0, 301]], 'diastolic_bp' => [[1, 200], [0, 201]], 'oxygen_saturation' => [[0, 100], [-0.1, 100.1]],
            'weight' => [[0.1, 1000], [0, 1000.1]], 'height' => [[0.1, 300], [0, 300.1]], 'glucose' => [[0.1, 2000], [0, 2000.1]],
        ] as $column => [$accepted, $rejected]) {
            foreach ($accepted as $value) {
                $this->insertVital([$column => $value]);
            }
            foreach ($rejected as $value) {
                $this->assertRejected("vital_signs_{$column}_check", fn () => $this->insertVital([$column => $value]));
            }
        }
        $this->insertVital(['systolic_bp' => 120, 'diastolic_bp' => 120]);
        $this->assertRejected('vital_signs_blood_pressure_check', fn () => $this->insertVital(['systolic_bp' => 119, 'diastolic_bp' => 120]));
        $this->assertSame(19, (int) DB::connection('hospital')->scalar('SELECT count(*) FROM vital_signs'));
    }

    public function test_time_and_text_limits_accept_equal_instants_and_reject_blank_or_reversed_values(): void
    {
        $db = DB::connection('hospital');
        $db->insert("INSERT INTO vital_signs (medical_record_id, care_episode_id, recorded_by_user_ref_id, temperature, measured_at, recorded_at) VALUES (?, ?, ?, 37, date_trunc('second', now()) - interval '1 hour', date_trunc('second', now()) - interval '1 hour')", [self::RECORD, self::EPISODE, self::USER]);
        $this->assertRejected('vital_signs_recorded_at_check', fn () => $db->insert("INSERT INTO vital_signs (medical_record_id, care_episode_id, recorded_by_user_ref_id, temperature, measured_at, recorded_at) VALUES (?, ?, ?, 37, date_trunc('second', now()) - interval '59 minutes 59 seconds', date_trunc('second', now()) - interval '1 hour')", [self::RECORD, self::EPISODE, self::USER]));
        $allergy = fn (string $name, string $status, ?string $resolved, ?string $reaction = null) => $db->insert("INSERT INTO allergies (local_patient_id, verified_by_user_ref_id, allergen_name, reaction, allergy_type, severity, status, onset_date, resolved_at) VALUES ('00000000-0000-0000-0000-000000000010', ?, ?, ?, 'allergy', 'mild', ?, '2026-01-10', ?)", [self::USER, $name, $reaction, $status, $resolved]);
        $allergy('Látex', 'resolved', '2026-01-10');
        $this->assertRejected('allergies_status_date_check', fn () => $allergy('Polen', 'resolved', '2026-01-09'));
        $this->assertRejected('allergies_allergen_check', fn () => $allergy('   ', 'active', null));
        $this->assertRejected('allergies_reaction_check', fn () => $allergy('Maní', 'active', null, ' '));
    }

    public function test_open_defect_au01_closed_episode_without_end_date(): void
    {
        $this->assertWriteRejectedOrDefectOpen('AU-01', 'un episodio «closed» sin ended_at se guarda (el CHECK compara con NULL)', fn () => DB::connection('hospital')->update("UPDATE care_episodes SET status = 'closed' WHERE care_episode_id = ?", [self::EPISODE]));
    }

    public function test_open_defect_au02_blank_patient_code_and_names(): void
    {
        $this->assertWriteRejectedOrDefectOpen('AU-02', 'un paciente con código y nombres en blanco se guarda', fn () => DB::connection('hospital')->insert("INSERT INTO local_patients (global_id, code, given_name, family_name, birth_date, sex) VALUES (gen_random_uuid(), ' ', ' ', ' ', '2000-01-01', 'male')"));
    }

    public function test_open_defect_au05_bed_with_open_stay_stays_available(): void
    {
        $db = DB::connection('hospital');
        $db->insert("INSERT INTO wards (ward_id, code, name, location) VALUES ('00000000-0000-0000-0000-000000000014', 'W-V15', 'Medicina', 'Nivel 1')");
        $db->insert("INSERT INTO beds (bed_id, ward_id, code, type) VALUES ('00000000-0000-0000-0000-000000000015', '00000000-0000-0000-0000-000000000014', 'B-V15', 'standard')");
        $db->insert("INSERT INTO bed_stays (care_episode_id, bed_id, assigned_by_user_ref_id, started_at, reason) VALUES (?, '00000000-0000-0000-0000-000000000015', ?, now(), 'Ingreso')", [self::EPISODE, self::USER]);
        $status = $db->scalar("SELECT status FROM beds WHERE bed_id = '00000000-0000-0000-0000-000000000015'");
        if ($status !== 'occupied') {
            $this->markTestIncomplete("Defecto abierto AU-05: la cama con una estancia abierta sigue «{$status}»");
        }
        $this->assertSame('occupied', $status);
    }

    private function insertVital(array $values): void
    {
        $columns = implode(', ', array_keys($values));
        $marks = implode(', ', array_fill(0, count($values), '?'));
        DB::connection('hospital')->insert("INSERT INTO vital_signs (medical_record_id, care_episode_id, recorded_by_user_ref_id, {$columns}, measured_at) VALUES (?, ?, ?, {$marks}, now())", [self::RECORD, self::EPISODE, self::USER, ...array_values($values)]);
    }

    private function assertWriteRejectedOrDefectOpen(string $defect, string $what, Closure $write): void
    {
        $db = DB::connection('hospital');
        $db->beginTransaction();
        try {
            $write();
        } catch (QueryException $exception) {
            $db->rollBack();
            $this->assertStringContainsString('check', $exception->getMessage());

            return;
        }
        $db->rollBack();
        $this->markTestIncomplete("Defecto abierto {$defect}: {$what}");
    }

    private function assertRejected(string $expected, Closure $statement): void
    {
        try {
            $statement();
            $this->fail("PostgreSQL debía rechazar la escritura con {$expected}.");
        } catch (QueryException $exception) {
            $this->assertStringContainsString($expected, $exception->getMessage());
        }
    }
}
