-- Copia del esquema de database/schema.sql adaptada a SQLite, solo para las pruebas de integración.
-- Mantiene las mismas restricciones: CHECK de estados y firma, llaves foráneas e inmutabilidad.
PRAGMA foreign_keys = ON;

CREATE TABLE medical_records (
    medical_record_id text PRIMARY KEY,
    local_patient_id  text NOT NULL,
    status            text NOT NULL DEFAULT 'open' CHECK (status IN ('open', 'closed'))
);
CREATE TABLE care_episodes (
    care_episode_id  text PRIMARY KEY,
    local_patient_id text NOT NULL
);
CREATE TABLE care_assignments (
    care_episode_id    text NOT NULL REFERENCES care_episodes (care_episode_id),
    doctor_user_ref_id text NOT NULL,
    status             text NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'ended')),
    PRIMARY KEY (care_episode_id, doctor_user_ref_id)
);
CREATE TABLE clinical_notes (
    clinical_note_id   text PRIMARY KEY,
    medical_record_id  text NOT NULL REFERENCES medical_records (medical_record_id),
    care_episode_id    text NOT NULL REFERENCES care_episodes (care_episode_id),
    author_user_ref_id text NOT NULL,
    note_type          text NOT NULL CHECK (trim(note_type) <> ''),
    content            text NOT NULL CHECK (trim(content) <> ''),
    status             text NOT NULL DEFAULT 'draft' CHECK (status IN ('draft', 'signed')),
    signed_at          text,
    version_no         integer NOT NULL CHECK (version_no > 0),
    created_at         text NOT NULL,
    CHECK ((status = 'draft' AND signed_at IS NULL) OR (status = 'signed' AND signed_at IS NOT NULL))
);
CREATE TABLE diagnoses (
    diagnosis_id       text PRIMARY KEY,
    care_episode_id    text NOT NULL REFERENCES care_episodes (care_episode_id),
    clinical_note_id   text REFERENCES clinical_notes (clinical_note_id),
    author_user_ref_id text NOT NULL,
    code               text NOT NULL CHECK (trim(code) <> ''),
    description        text NOT NULL CHECK (trim(description) <> ''),
    type               text NOT NULL CHECK (type IN ('primary', 'secondary', 'differential')),
    status             text NOT NULL DEFAULT 'active',
    onset_date         text NOT NULL
);
CREATE TABLE audit_events (
    event_id   integer PRIMARY KEY AUTOINCREMENT,
    event      text NOT NULL,
    data       text NOT NULL,
    created_at text NOT NULL
);

CREATE TABLE outbox_events (
    event_id        text PRIMARY KEY,
    event_type      text NOT NULL CHECK (trim(event_type) <> ''),
    aggregate_id    text NOT NULL,
    payload_minimal text NOT NULL,
    occurred_at     text NOT NULL,
    published_at    text,
    attempts        integer NOT NULL DEFAULT 0 CHECK (attempts >= 0)
);

CREATE TRIGGER clinical_notes_firmada BEFORE UPDATE ON clinical_notes WHEN OLD.status = 'signed'
BEGIN SELECT RAISE(ABORT, 'clinical_notes_signed_immutable'); END;
CREATE TRIGGER clinical_notes_borrar BEFORE DELETE ON clinical_notes
BEGIN SELECT RAISE(ABORT, 'clinical_record_delete_protection'); END;
CREATE TRIGGER diagnoses_borrar BEFORE DELETE ON diagnoses
BEGIN SELECT RAISE(ABORT, 'clinical_record_delete_protection'); END;
CREATE TRIGGER audit_events_solo_agregar BEFORE UPDATE ON audit_events
BEGIN SELECT RAISE(ABORT, 'audit_events_append_only'); END;
