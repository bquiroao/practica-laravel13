-- Esquema simplificado con los MISMOS nombres de la base HOSPITAL del SHI.
-- Claves UUID, fechas timestamptz y exclusión GiST, como en el diseño del repositorio.
CREATE EXTENSION IF NOT EXISTS btree_gist;

DROP TABLE IF EXISTS appointment, bed_stay, bed, ward, local_patient CASCADE;

CREATE TABLE ward (
    id         uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    code       text NOT NULL UNIQUE,
    name       text NOT NULL,
    created_at timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE bed (
    id         uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    ward_id    uuid NOT NULL REFERENCES ward (id),
    code       text NOT NULL UNIQUE,
    enabled    boolean NOT NULL DEFAULT true,
    created_at timestamptz NOT NULL DEFAULT now()
);

-- El paciente solo se identifica por su código (RN-09): sin nombres ni datos clínicos.
CREATE TABLE local_patient (
    id         uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    code       text NOT NULL UNIQUE CHECK (code ~ '^PAC-[0-9]{5}$'),
    created_at timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE bed_stay (
    id               uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    bed_id           uuid NOT NULL REFERENCES bed (id),
    local_patient_id uuid NOT NULL REFERENCES local_patient (id),
    started_at       timestamptz NOT NULL,
    ended_at         timestamptz,
    CHECK (ended_at IS NULL OR ended_at > started_at),
    -- Una cama no puede tener dos estancias superpuestas.
    EXCLUDE USING gist (bed_id WITH =, tstzrange(started_at, ended_at, '[)') WITH &&)
);

CREATE TABLE appointment (
    id               uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    doctor_code      text NOT NULL,
    local_patient_id uuid NOT NULL REFERENCES local_patient (id),
    scheduled_at     timestamptz NOT NULL,
    status           text NOT NULL CHECK (status IN ('scheduled', 'attended', 'cancelled', 'no_show'))
);

CREATE INDEX bed_stay_periodo_idx ON bed_stay (started_at, ended_at);
CREATE INDEX appointment_fecha_idx ON appointment (scheduled_at);
