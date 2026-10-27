-- Micro-HIS Prescripciones electrónicas con validación de alergias — esquema PostgreSQL (base de un hospital).
-- Se puede ejecutar varias veces: recrea el esquema «prescripciones» desde cero.
--
-- Origen de cada tabla:
--   allergies           → mismas columnas y CHECK que la migración real del HIS
--                         (database/migrations/hospital/2026_08_11_000401_create_allergy_and_vital_sign_tables.php),
--                         sin la FK a local_user_refs (otro módulo).
--   local_patients      → reducida a lo que este módulo necesita (en el HIS la crea el módulo de Pacientes).
--   medications, allergen_groups, prescriptions, prescription_items → PROPUESTA para la tarea 3.5 pendiente
--                         del plan del esquema («000500_create_medication_tables»).
--   prescription_audit_outbox → bandeja local de trazas hacia el módulo Auditoría (patrón outbox).

DROP SCHEMA IF EXISTS prescripciones CASCADE;
CREATE SCHEMA prescripciones;
SET search_path TO prescripciones;

CREATE TABLE local_patients (
    local_patient_id text PRIMARY KEY,
    hospital         text NOT NULL
);

CREATE TABLE allergies (
    allergy_id       bigserial PRIMARY KEY,
    local_patient_id text NOT NULL REFERENCES local_patients (local_patient_id) ON DELETE RESTRICT,
    allergen_code    text,
    allergen_name    text,
    reaction         text CONSTRAINT allergies_reaction_check CHECK (reaction IS NULL OR btrim(reaction) <> ''),
    allergy_type     text NOT NULL CONSTRAINT allergies_type_check CHECK (allergy_type IN ('allergy', 'intolerance', 'unknown')),
    severity         text NOT NULL CONSTRAINT allergies_severity_check
                         CHECK (severity IN ('mild', 'moderate', 'severe', 'life_threatening', 'unknown')),
    status           text NOT NULL DEFAULT 'active' CONSTRAINT allergies_status_check CHECK (status IN ('active', 'inactive', 'resolved')),
    recorded_at      timestamptz NOT NULL DEFAULT now(),
    CONSTRAINT allergies_allergen_check CHECK (COALESCE(NULLIF(btrim(allergen_code), ''), NULLIF(btrim(allergen_name), '')) IS NOT NULL)
);
CREATE INDEX allergies_local_patient_id_index ON allergies (local_patient_id);
CREATE UNIQUE INDEX allergies_active_equivalent_unique ON allergies
    (local_patient_id, allergy_type, lower(COALESCE(NULLIF(btrim(allergen_code), ''), NULLIF(btrim(allergen_name), ''))))
    WHERE status = 'active';

CREATE TABLE medications (
    medication_code        text PRIMARY KEY,
    name                   text NOT NULL,
    active_ingredient_code text NOT NULL,
    active_ingredient      text NOT NULL,
    pharmacological_group  text NOT NULL,
    unit                   text NOT NULL,
    max_daily_dose         numeric(12, 3) NOT NULL CHECK (max_daily_dose > 0),
    routes                 text[] NOT NULL CHECK (cardinality(routes) > 0),
    active                 boolean NOT NULL DEFAULT true
);

-- Grupo farmacológico de un alérgeno, por código ATC o por nombre en minúsculas (reactividad cruzada, R-05).
CREATE TABLE allergen_groups (
    allergen_key          text PRIMARY KEY CHECK (allergen_key = lower(btrim(allergen_key))),
    pharmacological_group text NOT NULL
);

CREATE TABLE prescriptions (
    prescription_id              bigserial PRIMARY KEY,
    hospital                     text NOT NULL,
    local_patient_id             text NOT NULL REFERENCES local_patients (local_patient_id) ON DELETE RESTRICT,
    prescriber                   text NOT NULL,
    idempotency_key              text NOT NULL CHECK (length(idempotency_key) BETWEEN 1 AND 128),
    content_hash                 char(64) NOT NULL,
    status                       text NOT NULL DEFAULT 'EMITIDA' CHECK (status IN ('EMITIDA')),
    allergy_check_result         text NOT NULL CHECK (allergy_check_result IN ('SIN_HALLAZGOS', 'ADVERTENCIA')),
    allergy_findings             jsonb NOT NULL DEFAULT '[]',
    allergens_checked            jsonb NOT NULL DEFAULT '[]',
    override_justification       text,
    no_known_allergies_confirmed boolean NOT NULL DEFAULT false,
    issued_at                    timestamptz NOT NULL,
    CONSTRAINT prescriptions_idempotency_unique UNIQUE (hospital, idempotency_key),
    -- R-05: una advertencia solo se emite con justificación de al menos 20 caracteres.
    CONSTRAINT prescriptions_override_check CHECK (
        (allergy_check_result = 'ADVERTENCIA' AND char_length(btrim(override_justification)) >= 20)
        OR (allergy_check_result = 'SIN_HALLAZGOS' AND override_justification IS NULL))
);
CREATE INDEX prescriptions_patient_index ON prescriptions (hospital, local_patient_id);

CREATE TABLE prescription_items (
    prescription_id bigint NOT NULL REFERENCES prescriptions (prescription_id) ON DELETE RESTRICT,
    line_no         integer NOT NULL CHECK (line_no > 0),
    medication_code text NOT NULL REFERENCES medications (medication_code) ON DELETE RESTRICT,
    dose            numeric(12, 3) NOT NULL CHECK (dose > 0),
    unit            text NOT NULL,
    frequency_hours integer NOT NULL CHECK (frequency_hours IN (4, 6, 8, 12, 24)),
    route           text NOT NULL,
    duration_days   integer NOT NULL CHECK (duration_days BETWEEN 1 AND 90),
    PRIMARY KEY (prescription_id, line_no)
);

CREATE TABLE prescription_audit_outbox (
    outbox_id   bigserial PRIMARY KEY,
    action      text NOT NULL CHECK (action IN ('PRESCRIPCION_EMITIDA', 'PRESCRIPCION_BLOQUEADA', 'VERIFICACION_NO_DISPONIBLE')),
    hospital    text NOT NULL,
    actor       text NOT NULL,
    resource    text NOT NULL,
    result      text NOT NULL CHECK (result IN ('exito', 'denegado', 'error')),
    occurred_at timestamptz NOT NULL DEFAULT now(),
    sent_at     timestamptz
);

-- R-10: una prescripción emitida (y sus ítems) no se modifica ni se borra, ni siquiera con SQL directo.
CREATE FUNCTION rechazar_cambio_prescripcion() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    RAISE EXCEPTION 'prescripcion_inmutable: % sobre % no permitido', TG_OP, TG_TABLE_NAME USING ERRCODE = 'P0001';
END;
$$;
CREATE TRIGGER prescriptions_inmutable BEFORE UPDATE OR DELETE ON prescriptions
    FOR EACH ROW EXECUTE FUNCTION rechazar_cambio_prescripcion();
CREATE TRIGGER prescription_items_inmutable BEFORE UPDATE OR DELETE ON prescription_items
    FOR EACH ROW EXECUTE FUNCTION rechazar_cambio_prescripcion();
