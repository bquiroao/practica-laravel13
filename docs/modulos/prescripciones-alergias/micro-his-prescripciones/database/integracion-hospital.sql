-- Semana 4 · Integración del módulo Prescripciones con el REPOSITORIO DE DATOS COMPARTIDO del HIS: la base de cada
-- hospital (database/migrations/hospital/*). Propuesta para el slice H5 («medication, prescription») del plan del esquema.
-- Se aplica DESPUÉS de las migraciones reales 000000..000401. bin/validar-integracion-hospital.php lo comprueba.
--
-- Diferencias con el esquema del Micro-HIS (schema.sql):
--   · ids uuid (convención del HIS) en lugar de bigserial y text;
--   · sin columna «hospital»: cada hospital tiene SU base (aislamiento físico, design.md «Topology»);
--   · FK reales a local_patients, care_episodes y local_user_refs;
--   · el prescriptor debe tener una asignación activa en el episodio: la MISMA regla que el HIS ya aplica a vital_signs;
--   · las alergias se leen por una VISTA: Prescripciones no recibe ningún privilegio sobre la tabla allergies (es del Expediente).

CREATE TABLE medications (
    medication_code        text PRIMARY KEY,
    name                   text NOT NULL CHECK (btrim(name) <> ''),
    active_ingredient_code text NOT NULL,
    active_ingredient      text NOT NULL,
    pharmacological_group  text NOT NULL,
    unit                   text NOT NULL,
    max_daily_dose         numeric(12, 3) NOT NULL CHECK (max_daily_dose > 0),
    routes                 text[] NOT NULL CHECK (cardinality(routes) > 0),
    active                 boolean NOT NULL DEFAULT true
);

CREATE TABLE allergen_groups (
    allergen_key          text PRIMARY KEY CHECK (allergen_key = lower(btrim(allergen_key))),
    pharmacological_group text NOT NULL
);

CREATE TABLE prescriptions (
    prescription_id              uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    local_patient_id             uuid NOT NULL REFERENCES local_patients (local_patient_id) ON DELETE RESTRICT,
    care_episode_id              uuid NOT NULL REFERENCES care_episodes (care_episode_id) ON DELETE RESTRICT,
    prescriber_user_ref_id       uuid NOT NULL REFERENCES local_user_refs (local_user_ref_id) ON DELETE RESTRICT,
    idempotency_key              text NOT NULL CHECK (length(idempotency_key) BETWEEN 1 AND 128),
    content_hash                 char(64) NOT NULL,
    status                       text NOT NULL DEFAULT 'EMITIDA' CHECK (status IN ('EMITIDA')),
    allergy_check_result         text NOT NULL CHECK (allergy_check_result IN ('SIN_HALLAZGOS', 'ADVERTENCIA')),
    allergy_findings             jsonb NOT NULL DEFAULT '[]' CHECK (jsonb_typeof(allergy_findings) = 'array'),
    allergens_checked            jsonb NOT NULL DEFAULT '[]' CHECK (jsonb_typeof(allergens_checked) = 'array'),
    override_justification       text,
    no_known_allergies_confirmed boolean NOT NULL DEFAULT false,
    issued_at                    timestamptz NOT NULL DEFAULT now(),
    CONSTRAINT prescriptions_idempotency_unique UNIQUE (idempotency_key),
    CONSTRAINT prescriptions_override_check CHECK (
        (allergy_check_result = 'ADVERTENCIA' AND char_length(btrim(override_justification)) >= 20)
        OR (allergy_check_result = 'SIN_HALLAZGOS' AND override_justification IS NULL))
);
CREATE INDEX prescriptions_local_patient_id_index ON prescriptions (local_patient_id);
CREATE INDEX prescriptions_care_episode_id_index ON prescriptions (care_episode_id);
CREATE INDEX prescriptions_prescriber_user_ref_id_index ON prescriptions (prescriber_user_ref_id);

CREATE TABLE prescription_items (
    prescription_id uuid NOT NULL REFERENCES prescriptions (prescription_id) ON DELETE RESTRICT,
    line_no         integer NOT NULL CHECK (line_no > 0),
    medication_code text NOT NULL REFERENCES medications (medication_code) ON DELETE RESTRICT,
    dose            numeric(12, 3) NOT NULL CHECK (dose > 0),
    unit            text NOT NULL,
    frequency_hours integer NOT NULL CHECK (frequency_hours IN (4, 6, 8, 12, 24)),
    route           text NOT NULL,
    duration_days   integer NOT NULL CHECK (duration_days BETWEEN 1 AND 90),
    PRIMARY KEY (prescription_id, line_no)
);

-- Integridad con las tablas compartidas, al estilo de enforce_vital_sign_integrity() del HIS.
CREATE OR REPLACE FUNCTION enforce_prescription_integrity() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    IF TG_OP IN ('UPDATE', 'DELETE') THEN
        RAISE EXCEPTION 'prescriptions_immutable';
    END IF;
    IF NOT EXISTS (SELECT 1 FROM care_episodes e WHERE e.care_episode_id = NEW.care_episode_id AND e.local_patient_id = NEW.local_patient_id
                   AND e.status = 'open') THEN
        RAISE EXCEPTION 'prescriptions_episode_patient_trigger';
    END IF;
    IF NOT EXISTS (SELECT 1 FROM doctor_profiles p JOIN care_assignments a ON a.doctor_profile_id = p.doctor_profile_id
                   WHERE p.local_user_ref_id = NEW.prescriber_user_ref_id AND p.status = 'active' AND a.care_episode_id = NEW.care_episode_id
                     AND a.status = 'active' AND a.valid_from <= now() AND a.valid_to IS NULL) THEN
        RAISE EXCEPTION 'prescriptions_prescriber_assignment_trigger';
    END IF;
    RETURN NEW;
END; $$;
CREATE TRIGGER prescriptions_integrity BEFORE INSERT OR UPDATE OR DELETE ON prescriptions
    FOR EACH ROW EXECUTE FUNCTION enforce_prescription_integrity();

CREATE OR REPLACE FUNCTION preserve_prescription_item() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN RAISE EXCEPTION 'prescription_items_immutable'; END; $$;
CREATE TRIGGER prescription_items_retention BEFORE UPDATE OR DELETE ON prescription_items
    FOR EACH ROW EXECUTE FUNCTION preserve_prescription_item();

-- Lectura de alergias: una vista con SOLO las columnas que necesita PdoFuenteAlergias. La tabla sigue siendo del Expediente.
CREATE VIEW prescripcion_alergias AS
    SELECT local_patient_id, allergen_code, allergen_name, reaction, allergy_type, severity, status, allergy_id
    FROM allergies;

-- Propiedad de los datos: el rol del módulo escribe solo SUS tablas y lee lo compartido por vistas o en solo lectura.
-- (El validador reemplaza «app_prescripciones» por un rol temporal NOLOGIN.)
REVOKE ALL ON prescriptions, prescription_items, medications, allergen_groups FROM PUBLIC;
GRANT SELECT, INSERT ON prescriptions, prescription_items TO app_prescripciones;
GRANT SELECT ON medications, allergen_groups, prescripcion_alergias, local_patients, care_episodes TO app_prescripciones;
