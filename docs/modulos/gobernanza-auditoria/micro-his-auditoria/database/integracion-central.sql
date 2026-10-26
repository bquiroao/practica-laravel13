-- Semana 4 · Cómo se integra audit_logs en el REPOSITORIO DE DATOS COMPARTIDO del HIS (la base central).
-- Se aplica DESPUÉS de las migraciones centrales del proyecto (networks, hospitals, central_users,
-- sync_nodes, central_inbox_messages). Es la versión «central» de schema.sql: mismas reglas,
-- tipos y referencias del esquema real.

-- 1. La tabla, con los tipos del repositorio compartido.
CREATE TABLE audit_logs (
    id              bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    emisor          text         NOT NULL CHECK (btrim(emisor) <> ''),
    event_id        uuid         NOT NULL,                                   -- el mismo uuid del evento en central_inbox_messages
    hospital_id     uuid         REFERENCES hospitals (hospital_id) ON DELETE RESTRICT,   -- NULL = nivel red
    usuario_id      uuid         NOT NULL,                                   -- central_users.user_id, SIN llave foránea (ver 3)
    modulo          text         NOT NULL CHECK (btrim(modulo) <> ''),
    accion          text         NOT NULL CHECK (btrim(accion) <> ''),
    ocurrido_en     timestamptz  NOT NULL,
    registrado_en   timestamptz  NOT NULL DEFAULT now(),
    resultado       text         NOT NULL CHECK (resultado IN ('exito', 'denegado', 'error')),
    recurso         text,
    correlation_id  uuid,
    detalle         jsonb        NOT NULL DEFAULT '{}'::jsonb CHECK (jsonb_typeof(detalle) = 'object'),
    secuencia       bigint       NOT NULL CHECK (secuencia > 0),
    sello           char(64)     NOT NULL,
    corrige_a       bigint       REFERENCES audit_logs (id) ON DELETE RESTRICT,   -- semana 6: corrección del movimiento id
    motivo_correccion text,
    CONSTRAINT audit_logs_correccion_completa CHECK ((corrige_a IS NULL) = (motivo_correccion IS NULL)),
    CONSTRAINT audit_logs_evento_unico UNIQUE (emisor, event_id)
);
CREATE UNIQUE INDEX audit_logs_cadena_unica ON audit_logs (COALESCE(hospital_id::text, '*'), secuencia);
-- Semana 6: un movimiento se corrige a lo sumo una vez.
CREATE UNIQUE INDEX audit_logs_una_correccion ON audit_logs (corrige_a) WHERE corrige_a IS NOT NULL;
CREATE INDEX audit_logs_hospital_fecha ON audit_logs (hospital_id, ocurrido_en DESC, id DESC);
CREATE INDEX audit_logs_usuario_fecha  ON audit_logs (usuario_id, ocurrido_en DESC);
CREATE INDEX audit_logs_modulo_fecha   ON audit_logs (modulo, ocurrido_en DESC);

-- 2. Solo inserción: el mismo patrón que cross_hospital_access_audits en este mismo repositorio.
CREATE FUNCTION audit_logs_solo_insercion() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    RAISE EXCEPTION 'audit_logs es de solo inserción (%)', TG_OP USING ERRCODE = 'insufficient_privilege';
END;
$$;
CREATE TRIGGER audit_logs_sin_update_delete BEFORE UPDATE OR DELETE ON audit_logs FOR EACH ROW EXECUTE FUNCTION audit_logs_solo_insercion();
CREATE TRIGGER audit_logs_sin_truncate BEFORE TRUNCATE ON audit_logs FOR EACH STATEMENT EXECUTE FUNCTION audit_logs_solo_insercion();

-- 3. ¿Por qué usuario_id NO tiene llave foránea a central_users y hospital_id SÍ la tiene?
--    La evidencia no puede rechazarse por un problema de sincronización: si el usuario todavía no llegó
--    a central_users (o se dio de baja), el movimiento igual debe quedar. hospitals, en cambio, es un
--    catálogo estable de la red: un hospital_id desconocido sí es un error del emisor.

-- 4. Propiedad de los datos: SOLO el módulo de auditoría escribe; los demás módulos no tocan la tabla.
--    (En el micro-HIS no se crean roles; aquí se documenta la configuración de producción.)
REVOKE ALL ON audit_logs FROM PUBLIC;
-- GRANT INSERT, SELECT ON audit_logs TO app_auditoria;          -- el servicio de auditoría
-- GRANT USAGE ON SEQUENCE audit_logs_id_seq TO app_auditoria;
-- Ningún GRANT para app_pacientes, app_rbac, …: escriben a través del caso de uso (API o bandeja).

-- 5. Entrada por la bandeja compartida: los nodos de hospital ya envían eventos a central_inbox_messages
--    con (source_node_id, event_id) único. Un consumidor del módulo de auditoría toma los de tipo
--    «auditoria.movimiento» y los registra con RegistrarMovimiento (mismas reglas, sello e idempotencia).
CREATE VIEW auditoria_bandeja_pendiente AS
    SELECT m.message_id, m.event_id, n.hospital_id, m.payload_minimal, m.attempts
    FROM central_inbox_messages m
    JOIN sync_nodes n ON n.node_id = m.source_node_id
    WHERE m.event_type = 'auditoria.movimiento' AND m.status = 'pending' AND m.available_at <= now();

-- El consumidor, por lote y dentro de una transacción (varios consumidores no se pisan):
--   SELECT … FROM central_inbox_messages WHERE message_id IN (SELECT message_id FROM auditoria_bandeja_pendiente LIMIT 100)
--   FOR UPDATE SKIP LOCKED;
--   → RegistrarMovimiento (event_id = el del mensaje; hospital = el del nodo de origen, NO el que diga el payload)
--   → UPDATE central_inbox_messages SET status = 'processed', processed_at = now() WHERE message_id = …;
