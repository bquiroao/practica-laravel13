<?php

// Semana 7 (Verificación BD) — Migración correctiva PROPUESTA para HOSPITAL. No está en database/migrations: el equipo decide si la adopta.
// AU-01 cierre sin fecha de fin · AU-02 textos vacíos · AU-03 cama ocupada con estancia abierta (RD-25). down() deja todo como estaba.
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    protected $connection = 'hospital';

    /** tabla => columnas de texto obligatorias que hoy aceptan '' (AU-02) */
    private const SIN_VACIOS = ['local_patients' => ['code', 'given_name', 'family_name'], 'medical_records' => ['record_number'], 'doctor_profiles' => ['license_number'],
        'specialties' => ['code', 'name'], 'wards' => ['code', 'name'], 'beds' => ['code', 'type'], 'local_user_refs' => ['display_name', 'role_code']];

    public function up(): void
    {
        $db = DB::connection($this->connection);
        $db->unprepared(<<<'SQL'
            ALTER TABLE care_episodes DROP CONSTRAINT care_episodes_status_period_check, ADD CONSTRAINT care_episodes_status_period_check
                CHECK ((status = 'open' AND ended_at IS NULL) OR (status IN ('closed', 'cancelled') AND ended_at IS NOT NULL AND ended_at > started_at));
            ALTER TABLE care_assignments DROP CONSTRAINT care_assignments_period_check, ADD CONSTRAINT care_assignments_period_check
                CHECK (status NOT IN ('active', 'closed', 'revoked') OR (status = 'active' AND valid_to IS NULL) OR (status IN ('closed', 'revoked') AND valid_to IS NOT NULL AND valid_to > valid_from));
            CREATE OR REPLACE FUNCTION occupy_bed_on_open_stay() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF NEW.ended_at IS NULL THEN UPDATE beds SET status = 'occupied', updated_at = now() WHERE bed_id = NEW.bed_id AND status <> 'occupied'; END IF;
                RETURN NULL;
            END; $$;
            CREATE TRIGGER bed_stays_occupy_bed AFTER INSERT OR UPDATE OF ended_at, bed_id ON bed_stays FOR EACH ROW EXECUTE FUNCTION occupy_bed_on_open_stay();
            SQL);
        foreach (self::SIN_VACIOS as $tabla => $columnas) {
            foreach ($columnas as $c) { $db->unprepared("ALTER TABLE $tabla ADD CONSTRAINT {$tabla}_{$c}_not_blank_check CHECK (btrim($c) <> '')"); }
        }
    }

    public function down(): void
    {
        $db = DB::connection($this->connection);
        foreach (self::SIN_VACIOS as $tabla => $columnas) {
            foreach ($columnas as $c) { $db->unprepared("ALTER TABLE $tabla DROP CONSTRAINT {$tabla}_{$c}_not_blank_check"); }
        }
        $db->unprepared(<<<'SQL'
            DROP TRIGGER bed_stays_occupy_bed ON bed_stays; DROP FUNCTION occupy_bed_on_open_stay();
            ALTER TABLE care_assignments DROP CONSTRAINT care_assignments_period_check, ADD CONSTRAINT care_assignments_period_check
                CHECK (status NOT IN ('active', 'closed', 'revoked') OR (status = 'active' AND valid_to IS NULL) OR (status IN ('closed', 'revoked') AND valid_to > valid_from));
            ALTER TABLE care_episodes DROP CONSTRAINT care_episodes_status_period_check, ADD CONSTRAINT care_episodes_status_period_check
                CHECK ((status = 'open' AND ended_at IS NULL) OR (status IN ('closed', 'cancelled') AND ended_at > started_at));
            SQL);
    }
};
