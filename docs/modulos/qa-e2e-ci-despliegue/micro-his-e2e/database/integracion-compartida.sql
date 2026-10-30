-- Semana 4 · Integración con un repositorio de datos COMPARTIDO del HIS (esquema «compartido», de la plataforma central).
--
-- Qué se comparte y qué no:
--   · El módulo E2E es DUEÑO de su esquema «e2e»; nadie más escribe ni lee ahí.
--   · Publica en «compartido.calidad_versiones» un hecho por cada evaluación del gate: versión, digest, decisión y causas.
--     Sin evidencias ni resultados por escenario: solo lo que otro módulo necesita para decidir (¿se puede desplegar este digest?).
--   · Lo publica un trigger en la MISMA transacción que la evaluación: no hay evaluación sin hecho publicado ni al revés.
--   · Los consumidores (QA y regresión, Despliegue, Gobernanza) leen con el rol «his_lector_calidad»: SOLO SELECT, y
--     SOLO las filas de su hospital (seguridad por filas con el tenant de la sesión: SET his.tenant = '…').
-- Se aplica después de schema.sql. Idempotente para entornos de prueba.

DROP SCHEMA IF EXISTS compartido CASCADE;
CREATE SCHEMA compartido;

DO $$ BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'his_lector_calidad') THEN
        CREATE ROLE his_lector_calidad NOLOGIN;
    END IF;
END $$;

CREATE TABLE compartido.calidad_versiones (
    id             bigserial   PRIMARY KEY,
    tenant         text        NOT NULL,
    modulo_origen  text        NOT NULL DEFAULT 'qa-e2e',
    version        text        NOT NULL,
    digest         text        NOT NULL CHECK (digest ~ '^sha256:[0-9a-f]{64}$'),
    decision       text        NOT NULL CHECK (decision IN ('APROBADO', 'RECHAZADO')),
    causas         jsonb       NOT NULL,
    politica       text        NOT NULL,
    evaluada_en    timestamptz NOT NULL,
    publicada_en   timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX calidad_versiones_por_digest ON compartido.calidad_versiones (tenant, digest, id DESC);

-- Los hechos publicados no se corrigen: una evaluación nueva es otro hecho.
CREATE TRIGGER calidad_versiones_solo_agregar BEFORE UPDATE OR DELETE ON compartido.calidad_versiones
    FOR EACH ROW EXECUTE FUNCTION e2e.solo_agregar();

-- Seguridad por filas: cada consumidor ve solo su hospital. Sin tenant en la sesión, no ve nada.
ALTER TABLE compartido.calidad_versiones ENABLE ROW LEVEL SECURITY;
CREATE POLICY calidad_por_tenant ON compartido.calidad_versiones FOR SELECT TO his_lector_calidad
    USING (tenant = current_setting('his.tenant', true));

GRANT USAGE ON SCHEMA compartido TO his_lector_calidad;
GRANT SELECT ON compartido.calidad_versiones TO his_lector_calidad;
REVOKE ALL ON SCHEMA e2e FROM his_lector_calidad;

-- La publicación: SECURITY DEFINER para que el módulo escriba en compartido sin dar permisos de escritura a nadie más;
-- con search_path fijo para que nadie pueda colar una función homónima.
CREATE FUNCTION e2e.publicar_evaluacion() RETURNS trigger LANGUAGE plpgsql SECURITY DEFINER SET search_path = pg_catalog, e2e AS $$
BEGIN
    INSERT INTO compartido.calidad_versiones (tenant, version, digest, decision, causas, politica, evaluada_en)
    VALUES (NEW.tenant, NEW.version, NEW.digest, NEW.decision, NEW.causas, NEW.politica, NEW.evaluada_en);
    RETURN NEW;
END $$;
CREATE TRIGGER evaluaciones_publicar AFTER INSERT ON e2e.evaluaciones FOR EACH ROW EXECUTE FUNCTION e2e.publicar_evaluacion();
