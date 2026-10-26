-- Micro-HIS Gobernanza y auditoría · esquema de la bitácora (PostgreSQL 15+)
-- En el HIS, audit_logs vive en la base CENTRAL: hospital_id y usuario_id corresponden a
-- hospitals.hospital_id y central_users.user_id. Aquí son texto para que el micro-HIS sea independiente.

CREATE TABLE audit_logs (
    id              bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    emisor          varchar(64)  NOT NULL,                 -- cuenta de servicio del módulo que registra
    event_id        varchar(64)  NOT NULL,                 -- id del emisor: idempotencia (R-04)
    hospital_id     varchar(64),                           -- tenant; NULL = nivel red
    usuario_id      varchar(64)  NOT NULL,
    modulo          varchar(32)  NOT NULL,
    accion          varchar(64)  NOT NULL,
    ocurrido_en     timestamptz  NOT NULL,
    registrado_en   timestamptz  NOT NULL DEFAULT now(),
    resultado       varchar(8)   NOT NULL CHECK (resultado IN ('exito', 'denegado', 'error')),
    recurso         varchar(128),
    correlation_id  varchar(64),
    detalle         jsonb        NOT NULL DEFAULT '{}'::jsonb CHECK (jsonb_typeof(detalle) = 'object'),
    secuencia       bigint       NOT NULL CHECK (secuencia > 0),
    sello           char(64)     NOT NULL,
    corrige_a       bigint       REFERENCES audit_logs (id) ON DELETE RESTRICT,   -- semana 6: corrección del movimiento id
    motivo_correccion varchar(500),
    CONSTRAINT audit_logs_correccion_completa CHECK ((corrige_a IS NULL) = (motivo_correccion IS NULL)),                 -- sha256(sello anterior ‖ secuencia ‖ contenido) (R-03)
    CONSTRAINT audit_logs_evento_unico UNIQUE (emisor, event_id)
);

-- Una sola fila por posición de cada cadena: última defensa si el candado de la aplicación fallara.
CREATE UNIQUE INDEX audit_logs_cadena_unica ON audit_logs (COALESCE(hospital_id, '*'), secuencia);

-- Semana 6: un movimiento se corrige a lo sumo una vez (para volver a corregir se corrige la corrección).
CREATE UNIQUE INDEX audit_logs_una_correccion ON audit_logs (corrige_a) WHERE corrige_a IS NOT NULL;

-- Consultas filtradas (R-06, R-09): siempre por rango de fechas, del más reciente al más antiguo.
CREATE INDEX audit_logs_hospital_fecha ON audit_logs (hospital_id, ocurrido_en DESC, id DESC);
CREATE INDEX audit_logs_usuario_fecha  ON audit_logs (usuario_id, ocurrido_en DESC);
CREATE INDEX audit_logs_modulo_fecha   ON audit_logs (modulo, ocurrido_en DESC);

-- Solo inserción (R-02): mismo patrón que cross_hospital_access_audits en la base central del HIS.
CREATE FUNCTION audit_logs_solo_insercion() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    RAISE EXCEPTION 'audit_logs es de solo inserción (%)', TG_OP USING ERRCODE = 'insufficient_privilege';
END;
$$;

CREATE TRIGGER audit_logs_sin_update_delete BEFORE UPDATE OR DELETE ON audit_logs
    FOR EACH ROW EXECUTE FUNCTION audit_logs_solo_insercion();
CREATE TRIGGER audit_logs_sin_truncate BEFORE TRUNCATE ON audit_logs
    FOR EACH STATEMENT EXECUTE FUNCTION audit_logs_solo_insercion();

-- En producción, además: el rol de la aplicación solo tiene INSERT y SELECT.
-- REVOKE UPDATE, DELETE, TRUNCATE ON audit_logs FROM app_auditoria;
