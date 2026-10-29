<?php
declare(strict_types=1);

/*
 * Esquema mínimo para el Micro-HIS. allergies replica las columnas y restricciones de la migración
 * real database/migrations/hospital/2026_08_11_000401_create_allergy_and_vital_sign_tables.php.
 * local_patients y local_user_refs solo llevan las columnas que este módulo usa.
 * allergy_alert_acknowledgements NO existe en HOSPITAL: es propuesta de este módulo (semana 4).
 */
return [
    'CREATE EXTENSION IF NOT EXISTS pgcrypto',
    'DROP TABLE IF EXISTS allergy_alert_acknowledgements, allergies, local_patients, local_user_refs CASCADE',
    'CREATE TABLE local_user_refs (local_user_ref_id uuid PRIMARY KEY)',
    "CREATE TABLE local_patients (
        local_patient_id uuid PRIMARY KEY DEFAULT gen_random_uuid(), global_id uuid NOT NULL UNIQUE, code text NOT NULL UNIQUE,
        given_name text NOT NULL, family_name text NOT NULL, birth_date date NOT NULL,
        sex text NOT NULL CHECK (sex IN ('female', 'male', 'other', 'unknown')),
        status text NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'inactive', 'deceased')))",
    "CREATE TABLE allergies (
        allergy_id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
        local_patient_id uuid NOT NULL REFERENCES local_patients(local_patient_id) ON DELETE RESTRICT,
        verified_by_user_ref_id uuid NOT NULL REFERENCES local_user_refs(local_user_ref_id) ON DELETE RESTRICT,
        allergen_code text, allergen_name text,
        reaction text CONSTRAINT allergies_reaction_check CHECK (reaction IS NULL OR btrim(reaction) <> ''),
        allergy_type text NOT NULL CONSTRAINT allergies_type_check CHECK (allergy_type IN ('allergy', 'intolerance', 'unknown')),
        severity text NOT NULL CONSTRAINT allergies_severity_check CHECK (severity IN ('mild', 'moderate', 'severe', 'life_threatening', 'unknown')),
        status text NOT NULL DEFAULT 'active' CONSTRAINT allergies_status_check CHECK (status IN ('active', 'inactive', 'resolved')),
        onset_date date, resolved_at date, recorded_at timestamptz NOT NULL DEFAULT now(),
        created_at timestamptz NOT NULL DEFAULT now(), updated_at timestamptz NOT NULL DEFAULT now(),
        CONSTRAINT allergies_allergen_check CHECK (COALESCE(NULLIF(btrim(allergen_code), ''), NULLIF(btrim(allergen_name), '')) IS NOT NULL),
        CONSTRAINT allergies_status_date_check CHECK ((status = 'active' AND resolved_at IS NULL) OR (status IN ('inactive', 'resolved')
            AND resolved_at IS NOT NULL AND (onset_date IS NULL OR resolved_at >= onset_date))))",
    'CREATE INDEX allergies_local_patient_id_index ON allergies (local_patient_id)',
    "CREATE UNIQUE INDEX allergies_active_equivalent_unique ON allergies (local_patient_id, allergy_type,
        lower(COALESCE(NULLIF(btrim(allergen_code), ''), NULLIF(btrim(allergen_name), '')))) WHERE status = 'active'",
    // Réplica del comportamiento del trigger de retención real (su cuerpo no se copió).
    "CREATE OR REPLACE FUNCTION allergies_sin_borrado() RETURNS trigger LANGUAGE plpgsql AS
        \$\$ BEGIN RAISE EXCEPTION 'allergies_delete_protection'; END \$\$",
    'CREATE TRIGGER allergies_retention BEFORE DELETE ON allergies FOR EACH ROW EXECUTE FUNCTION allergies_sin_borrado()',
    // PROPUESTA (semana 4): reconocimiento por usuario y por huella de la alerta (RN-09).
    "CREATE TABLE allergy_alert_acknowledgements (
        acknowledgement_id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
        local_patient_id uuid NOT NULL REFERENCES local_patients(local_patient_id) ON DELETE RESTRICT,
        acknowledged_by_user_ref_id uuid NOT NULL REFERENCES local_user_refs(local_user_ref_id) ON DELETE RESTRICT,
        alert_level text NOT NULL CHECK (alert_level IN ('ALTA', 'CRITICA')),
        alert_fingerprint text NOT NULL, acknowledged_at timestamptz NOT NULL DEFAULT now(),
        CONSTRAINT allergy_alert_ack_unique UNIQUE (local_patient_id, acknowledged_by_user_ref_id, alert_fingerprint))",
];
