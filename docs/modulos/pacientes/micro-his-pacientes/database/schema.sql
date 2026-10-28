-- Micro-HIS Pacientes: esquema PostgreSQL 15+.
-- Basado en local_patients (database/migrations/hospital) con lo que el diseño exige además:
-- versión para la concurrencia optimista (R-06) y el documento protegido como en mpi_identifiers (R-04).
-- En el micro-HIS los hospitales comparten una base y se distinguen por hospital_id; en el HIS cada
-- hospital tiene su propia base y el índice de duplicados vive en la base central (MPI).

CREATE SEQUENCE pacientes_codigo_seq;

CREATE TABLE pacientes (
    paciente_id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    hospital_id text NOT NULL CHECK (btrim(hospital_id) <> ''),
    code text NOT NULL DEFAULT ('P-' || lpad(nextval('pacientes_codigo_seq')::text, 6, '0')),
    given_name text NOT NULL CHECK (btrim(given_name) <> ''),
    family_name text NOT NULL CHECK (btrim(family_name) <> ''),
    nombre_normalizado text NOT NULL,
    apellidos_normalizados text NOT NULL,
    birth_date date NOT NULL CHECK (birth_date <= current_date),
    sex text NOT NULL CHECK (sex IN ('female', 'male', 'other', 'unknown')),
    contact text,
    status text NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'inactive', 'deceased')),
    -- Documento: nunca en claro. Hash HMAC para buscar y para la unicidad; cifrado para leerlo.
    documento_tipo text CHECK (documento_tipo IN ('DPI', 'PASAPORTE')),
    documento_hash text CHECK (documento_hash ~ '^[0-9a-f]{64}$'),
    documento_cifrado text,
    documento_enmascarado text,
    version integer NOT NULL DEFAULT 1 CHECK (version >= 1),
    -- Semana 6 (R-11): identidad protegida; quien no tiene pacientes.leer_protegido no ve su demografía.
    identidad_protegida boolean NOT NULL DEFAULT false,
    created_at timestamptz NOT NULL DEFAULT now(),
    updated_at timestamptz NOT NULL DEFAULT now(),
    CONSTRAINT pacientes_code_unique UNIQUE (code),
    -- O el documento está completo, o no hay documento (identidad provisional).
    CONSTRAINT pacientes_documento_completo CHECK (
        (documento_tipo IS NULL AND documento_hash IS NULL AND documento_cifrado IS NULL AND documento_enmascarado IS NULL)
        OR (documento_tipo IS NOT NULL AND documento_hash IS NOT NULL AND documento_cifrado IS NOT NULL AND documento_enmascarado IS NOT NULL)
    )
);

-- R-02 en la base: última defensa si dos registros simultáneos pasan la verificación previa.
CREATE UNIQUE INDEX pacientes_documento_unico ON pacientes (documento_hash) WHERE documento_hash IS NOT NULL;
-- R-03: coincidencia probable por nombre normalizado y fecha.
CREATE INDEX pacientes_coincidencia ON pacientes (nombre_normalizado, birth_date);
-- R-10: búsqueda dentro del hospital, ordenada por apellidos.
CREATE INDEX pacientes_hospital_apellidos ON pacientes (hospital_id, apellidos_normalizados, nombre_normalizado);

CREATE TABLE eventos_auditoria (
    evento_id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    accion text NOT NULL CHECK (accion IN ('PACIENTE_REGISTRADO', 'PACIENTE_BUSCADO', 'PACIENTE_CONSULTADO', 'PACIENTE_ACTUALIZADO')),
    actor_id text NOT NULL,
    hospital_id text NOT NULL,
    paciente_codigo text,
    detalle jsonb NOT NULL CHECK (jsonb_typeof(detalle) = 'object'),
    registrado_en timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX eventos_auditoria_paciente ON eventos_auditoria (paciente_codigo, registrado_en);

-- Semana 5: Idempotency-Key de POST /api/v1/pacientes. Se escribe en la misma transacción que el paciente.
-- Una tarea de mantenimiento puede borrar claves con más de 24 h (no es evidencia clínica).
CREATE TABLE idempotencia_registros (
    hospital_id text NOT NULL,
    actor_id text NOT NULL,
    clave text NOT NULL CHECK (clave ~ '^[A-Za-z0-9-]{8,64}$'),
    huella text NOT NULL CHECK (huella ~ '^[0-9a-f]{64}$'),
    respuesta jsonb NOT NULL CHECK (jsonb_typeof(respuesta) = 'object'),
    creado_en timestamptz NOT NULL DEFAULT now(),
    PRIMARY KEY (hospital_id, actor_id, clave)
);

-- Bitácora append-only (R-09): ni UPDATE ni DELETE.
CREATE FUNCTION eventos_auditoria_inmutable() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    RAISE EXCEPTION 'eventos_auditoria es append-only' USING ERRCODE = '42501';
END;
$$;

CREATE TRIGGER eventos_auditoria_sin_cambios
    BEFORE UPDATE OR DELETE ON eventos_auditoria
    FOR EACH ROW EXECUTE FUNCTION eventos_auditoria_inmutable();
