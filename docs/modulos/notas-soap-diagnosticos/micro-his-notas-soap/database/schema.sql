-- Micro-HIS Notas SOAP y diagnósticos: esquema PostgreSQL 15+.
-- Versión reducida de las tablas del HIS (migración hospital 000400). En el HIS los id son uuid;
-- aquí son text para que los datos de demostración sean legibles.

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
    note_type          text NOT NULL CHECK (btrim(note_type) <> ''),
    content            text NOT NULL CHECK (btrim(content) <> ''),
    status             text NOT NULL DEFAULT 'draft' CHECK (status IN ('draft', 'signed')),
    signed_at          timestamptz,
    version_no         integer NOT NULL CHECK (version_no > 0),
    created_at         timestamptz NOT NULL DEFAULT now(),
    CONSTRAINT clinical_notes_signature_check CHECK (
        (status = 'draft' AND signed_at IS NULL) OR (status = 'signed' AND signed_at IS NOT NULL)
    )
);

CREATE TABLE diagnoses (
    diagnosis_id       text PRIMARY KEY,
    care_episode_id    text NOT NULL REFERENCES care_episodes (care_episode_id),
    clinical_note_id   text REFERENCES clinical_notes (clinical_note_id),
    author_user_ref_id text NOT NULL,
    code               text NOT NULL CHECK (btrim(code) <> ''),
    description        text NOT NULL CHECK (btrim(description) <> ''),
    type               text NOT NULL CHECK (type IN ('primary', 'secondary', 'differential')),
    status             text NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'resolved', 'entered_in_error')),
    onset_date         date NOT NULL
);
CREATE INDEX diagnoses_clinical_note_id_index ON diagnoses (clinical_note_id);

CREATE TABLE audit_events (
    event_id   bigserial PRIMARY KEY,
    event      text NOT NULL,
    data       jsonb NOT NULL,
    created_at timestamptz NOT NULL DEFAULT now()
);

-- Bandeja de salida (patrón outbox) hacia el repositorio compartido CENTRAL (semana 4).
-- Se escribe en la MISMA transacción que la firma; un publicador aparte la envía a central_inbox_messages
-- y llena published_at. payload_minimal lleva solo identificadores, nunca el texto de la nota.
CREATE TABLE outbox_events (
    event_id        text PRIMARY KEY,
    event_type      text NOT NULL CHECK (btrim(event_type) <> ''),
    aggregate_id    text NOT NULL,
    payload_minimal jsonb NOT NULL CHECK (jsonb_typeof(payload_minimal) = 'object'),
    occurred_at     timestamptz NOT NULL,
    published_at    timestamptz,
    attempts        integer NOT NULL DEFAULT 0 CHECK (attempts >= 0)
);
CREATE INDEX outbox_events_pendientes_index ON outbox_events (occurred_at) WHERE published_at IS NULL;

-- Idempotencia del registro (semana 5): una Idempotency-Key por hospital y usuario apunta a la nota que creó.
-- Se escribe en la misma transacción que la nota; la llave primaria impide duplicados aunque lleguen dos a la vez.
CREATE TABLE idempotency_keys (
    hospital_id      text NOT NULL,
    user_ref_id      text NOT NULL,
    idempotency_key  text NOT NULL CHECK (idempotency_key ~ '^[A-Za-z0-9-]{8,64}$'),
    request_hash     text NOT NULL CHECK (length(request_hash) = 64),
    clinical_note_id text NOT NULL REFERENCES clinical_notes (clinical_note_id),
    created_at       timestamptz NOT NULL,
    PRIMARY KEY (hospital_id, user_ref_id, idempotency_key)
);

-- R-07: una nota firmada no cambia. R-08: notas, diagnósticos y eventos no se borran.
CREATE FUNCTION notas_soap_proteger() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    IF TG_OP = 'DELETE' THEN
        RAISE EXCEPTION 'clinical_record_delete_protection';
    END IF;
    IF TG_TABLE_NAME = 'clinical_notes' AND OLD.status = 'signed' THEN
        RAISE EXCEPTION 'clinical_notes_signed_immutable';
    END IF;
    IF TG_TABLE_NAME = 'audit_events' THEN
        RAISE EXCEPTION 'audit_events_append_only';
    END IF;
    RETURN NEW;
END;
$$;

CREATE TRIGGER clinical_notes_proteger BEFORE UPDATE OR DELETE ON clinical_notes FOR EACH ROW EXECUTE FUNCTION notas_soap_proteger();
CREATE TRIGGER diagnoses_proteger BEFORE DELETE ON diagnoses FOR EACH ROW EXECUTE FUNCTION notas_soap_proteger();
CREATE TRIGGER audit_events_proteger BEFORE UPDATE OR DELETE ON audit_events FOR EACH ROW EXECUTE FUNCTION notas_soap_proteger();
