-- Micro-HIS RBAC — esquema PostgreSQL 15+
-- Modela de forma reducida hospital_memberships (CENTRAL) y la bitácora local de accesos.
-- No fija un esquema: se crea en el search_path actual.

CREATE TABLE hospitales (
    hospital_id text PRIMARY KEY,
    nombre      text NOT NULL
);

CREATE TABLE usuarios (
    usuario_id text PRIMARY KEY,
    nombre     text NOT NULL,
    estado     text NOT NULL DEFAULT 'active' CHECK (estado IN ('active', 'inactive'))
);

CREATE TABLE roles (
    codigo text PRIMARY KEY,
    nombre text NOT NULL
);

CREATE TABLE permisos (
    codigo      text PRIMARY KEY,
    descripcion text NOT NULL
);

CREATE TABLE rol_permiso (
    rol_codigo     text NOT NULL REFERENCES roles (codigo) ON DELETE RESTRICT,
    permiso_codigo text NOT NULL REFERENCES permisos (codigo) ON DELETE RESTRICT,
    PRIMARY KEY (rol_codigo, permiso_codigo)
);

-- R-02: estado + vigencia; R-03: índice único parcial de membresía activa abierta.
CREATE TABLE membresias (
    membresia_id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    usuario_id   text NOT NULL REFERENCES usuarios (usuario_id) ON DELETE RESTRICT,
    hospital_id  text NOT NULL REFERENCES hospitales (hospital_id) ON DELETE RESTRICT,
    rol_codigo   text NOT NULL REFERENCES roles (codigo) ON DELETE RESTRICT,
    estado       text NOT NULL DEFAULT 'active' CHECK (estado IN ('active', 'inactive', 'revoked')),
    valido_desde timestamptz NOT NULL,
    valido_hasta timestamptz,
    creado_en    timestamptz NOT NULL DEFAULT now(),
    CONSTRAINT membresias_vigencia_valida CHECK (valido_hasta IS NULL OR valido_hasta > valido_desde)
);
CREATE INDEX membresias_usuario_hospital_idx ON membresias (usuario_id, hospital_id);
CREATE UNIQUE INDEX membresias_activa_abierta_unica
    ON membresias (usuario_id, hospital_id, rol_codigo)
    WHERE estado = 'active' AND valido_hasta IS NULL;

-- R-06: bitácora append-only. actor_id y hospital_id no tienen FK a propósito:
-- un intento de acceso de un usuario inexistente también debe quedar registrado.
CREATE TABLE eventos_auditoria (
    evento_id      bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    correlation_id text NOT NULL UNIQUE,
    tipo           text NOT NULL CHECK (tipo IN ('ROL_ASIGNADO', 'ROL_REVOCADO', 'ACCESO_DENEGADO')),
    actor_id       text NOT NULL,
    hospital_id    text NOT NULL,
    datos          jsonb NOT NULL CHECK (jsonb_typeof(datos) = 'object'),
    ocurrido_en    timestamptz NOT NULL
);
CREATE INDEX eventos_auditoria_actor_idx ON eventos_auditoria (actor_id);
CREATE INDEX eventos_auditoria_hospital_fecha_idx ON eventos_auditoria (hospital_id, ocurrido_en);

CREATE FUNCTION impedir_mutacion_auditoria() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    RAISE EXCEPTION 'eventos_auditoria es append-only';
END;
$$;

CREATE TRIGGER eventos_auditoria_inmutable
    BEFORE UPDATE OR DELETE ON eventos_auditoria
    FOR EACH ROW EXECUTE FUNCTION impedir_mutacion_auditoria();
