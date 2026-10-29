-- Micro-HIS QA E2E, CI y guía de despliegue: esquema propio «e2e» (PostgreSQL 14+).
-- Las reglas que no pueden depender de que la aplicación «se porte bien» también están aquí:
--   datos sintéticos (R-01), plan congelado (R-02), digest de la ejecución = digest de la versión (R-03),
--   resultados, evidencias, evaluaciones y traza solo se agregan (R-04, R-08), clave de idempotencia única (R-12).
-- Idempotente: DROP SCHEMA ... CASCADE al inicio (solo para entornos de prueba y demostración).

DROP SCHEMA IF EXISTS e2e CASCADE;
CREATE SCHEMA e2e;
SET search_path TO e2e;

CREATE FUNCTION solo_agregar() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    RAISE EXCEPTION 'la tabla % solo admite INSERT (R-04, R-08)', TG_TABLE_NAME USING ERRCODE = 'P0001';
END $$;

-- ---------- Escenarios E2E ----------
CREATE TABLE escenarios (
    tenant        text    NOT NULL,
    escenario_id  text    NOT NULL CHECK (escenario_id ~ '^E2E-[0-9]{3,5}$'),
    titulo        text    NOT NULL CHECK (length(btrim(titulo)) BETWEEN 1 AND 200),
    modulo        text    NOT NULL CHECK (modulo ~ '^[a-z][a-z0-9-]{1,40}$'),
    critico       boolean NOT NULL,
    requisitos    jsonb   NOT NULL CHECK (jsonb_typeof(requisitos) = 'array' AND jsonb_array_length(requisitos) >= 1),
    datos         text    NOT NULL CHECK (datos ~ '^SINT-[A-Z0-9-]{2,40}$'),          -- R-01
    autor         text    NOT NULL,
    creado_en     timestamptz NOT NULL DEFAULT now(),
    PRIMARY KEY (tenant, escenario_id)
);

-- ---------- Versiones ----------
CREATE TABLE versiones (
    tenant          text    NOT NULL,
    numero          text    NOT NULL CHECK (numero ~ '^[0-9]+\.[0-9]+\.[0-9]+(-[0-9A-Za-z.]+)?$'),
    commit_sha      text    NOT NULL CHECK (commit_sha ~ '^[0-9a-f]{7,40}$'),
    digest          text    NOT NULL CHECK (digest ~ '^sha256:[0-9a-f]{64}$'),
    modulos         jsonb   NOT NULL CHECK (jsonb_typeof(modulos) = 'array'),
    registrada_por  text    NOT NULL,
    estado          text    NOT NULL DEFAULT 'CANDIDATA' CHECK (estado IN ('CANDIDATA', 'APROBADA', 'RECHAZADA')),
    plan            jsonb   CHECK (plan IS NULL OR jsonb_typeof(plan) = 'object'),
    plan_congelado  boolean NOT NULL DEFAULT false,
    creada_en       timestamptz NOT NULL DEFAULT now(),
    PRIMARY KEY (tenant, numero),
    UNIQUE (tenant, digest),
    CONSTRAINT versiones_congelado_con_plan CHECK (NOT plan_congelado OR plan IS NOT NULL)
);

-- Número, commit y digest no cambian; el plan congelado tampoco (R-02, R-03). Una versión no se borra.
CREATE FUNCTION versiones_proteger() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    IF TG_OP = 'DELETE' THEN
        RAISE EXCEPTION 'una versión no se borra' USING ERRCODE = 'P0001';
    END IF;
    IF NEW.numero <> OLD.numero OR NEW.commit_sha <> OLD.commit_sha OR NEW.digest <> OLD.digest THEN
        RAISE EXCEPTION 'el número, el commit y el digest de una versión no cambian (R-03)' USING ERRCODE = 'P0001';
    END IF;
    IF OLD.plan_congelado AND (NEW.plan IS DISTINCT FROM OLD.plan OR NOT NEW.plan_congelado) THEN
        RAISE EXCEPTION 'el plan congelado no cambia (R-02)' USING ERRCODE = 'P0001';
    END IF;
    RETURN NEW;
END $$;
CREATE TRIGGER versiones_proteger BEFORE UPDATE OR DELETE ON versiones FOR EACH ROW EXECUTE FUNCTION versiones_proteger();

-- ---------- Ejecuciones ----------
CREATE TABLE ejecuciones (
    id            bigserial PRIMARY KEY,
    tenant        text      NOT NULL,
    version       text      NOT NULL,
    digest        text      NOT NULL,
    entorno       text      NOT NULL CHECK (entorno ~ '^[a-z][a-z0-9-]{1,30}$'),
    plan          jsonb     NOT NULL CHECK (jsonb_typeof(plan) = 'object'),
    estado        text      NOT NULL DEFAULT 'EN_CURSO' CHECK (estado IN ('EN_CURSO', 'COMPLETADA', 'ABORTADA')),
    iniciada_en   timestamptz NOT NULL,
    terminada_en  timestamptz,
    motivo        text,
    UNIQUE (tenant, id),
    FOREIGN KEY (tenant, version) REFERENCES versiones (tenant, numero),
    CONSTRAINT ejecuciones_termino_coherente CHECK ((estado = 'EN_CURSO') = (terminada_en IS NULL)),
    CONSTRAINT ejecuciones_aborto_con_motivo CHECK (estado <> 'ABORTADA' OR length(btrim(coalesce(motivo, ''))) >= 1)
);

-- R-03 en la base: la ejecución corre sobre el digest de SU versión. Y el estado solo sale de EN_CURSO, una vez.
CREATE FUNCTION ejecuciones_proteger() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    IF TG_OP = 'DELETE' THEN
        RAISE EXCEPTION 'una ejecución no se borra' USING ERRCODE = 'P0001';
    END IF;
    IF TG_OP = 'INSERT' THEN
        IF NEW.digest IS DISTINCT FROM (SELECT v.digest FROM versiones v WHERE v.tenant = NEW.tenant AND v.numero = NEW.version) THEN
            RAISE EXCEPTION 'la ejecución tiene otro digest que su versión (R-03)' USING ERRCODE = 'P0001';
        END IF;
        RETURN NEW;
    END IF;
    IF OLD.estado <> 'EN_CURSO' OR NEW.digest <> OLD.digest OR NEW.plan <> OLD.plan OR NEW.version <> OLD.version THEN
        RAISE EXCEPTION 'una ejecución terminada no cambia, ni su digest ni su plan' USING ERRCODE = 'P0001';
    END IF;
    RETURN NEW;
END $$;
CREATE TRIGGER ejecuciones_proteger BEFORE INSERT OR UPDATE OR DELETE ON ejecuciones FOR EACH ROW EXECUTE FUNCTION ejecuciones_proteger();

-- ---------- Resultados y evidencias (solo se agregan) ----------
CREATE TABLE resultados (
    id            bigserial PRIMARY KEY,
    tenant        text      NOT NULL,
    ejecucion_id  bigint    NOT NULL,
    escenario_id  text      NOT NULL,
    critico       boolean   NOT NULL,
    resultado     text      NOT NULL CHECK (resultado IN ('PASA', 'FALLA', 'INESTABLE', 'OMITIDO')),
    intentos      integer   NOT NULL CHECK (intentos BETWEEN 0 AND 5),
    clave         text      NOT NULL,
    huella        text      NOT NULL,
    reportado_en  timestamptz NOT NULL,
    FOREIGN KEY (tenant, ejecucion_id) REFERENCES ejecuciones (tenant, id),
    UNIQUE (ejecucion_id, escenario_id),
    UNIQUE (tenant, clave),                                                           -- R-12
    CONSTRAINT resultados_inestable_coherente CHECK (resultado <> 'INESTABLE' OR intentos > 1),
    CONSTRAINT resultados_pasa_al_primero CHECK (resultado <> 'PASA' OR intentos = 1)  -- R-06
);
CREATE TRIGGER resultados_solo_agregar BEFORE UPDATE OR DELETE ON resultados FOR EACH ROW EXECUTE FUNCTION solo_agregar();

CREATE TABLE evidencias (
    id            bigserial PRIMARY KEY,
    resultado_id  bigint  NOT NULL REFERENCES resultados (id),
    tipo          text    NOT NULL CHECK (tipo IN ('traza', 'captura', 'video', 'log', 'junit')),
    sha256        text    NOT NULL CHECK (sha256 ~ '^[0-9a-f]{64}$'),
    tamano        bigint  NOT NULL CHECK (tamano BETWEEN 1 AND 20971520),
    ubicacion     text    NOT NULL
);
CREATE TRIGGER evidencias_solo_agregar BEFORE UPDATE OR DELETE ON evidencias FOR EACH ROW EXECUTE FUNCTION solo_agregar();

-- ---------- Evaluaciones del gate (solo se agregan) ----------
CREATE TABLE evaluaciones (
    id            bigserial PRIMARY KEY,
    tenant        text      NOT NULL,
    version       text      NOT NULL,
    ejecucion_id  bigint    NOT NULL,
    decision      text      NOT NULL CHECK (decision IN ('APROBADO', 'RECHAZADO')),
    reglas        jsonb     NOT NULL,
    causas        jsonb     NOT NULL,
    digest        text      NOT NULL,
    politica      text      NOT NULL,
    evaluada_en   timestamptz NOT NULL,
    evaluada_por  text      NOT NULL,
    FOREIGN KEY (tenant, version) REFERENCES versiones (tenant, numero),
    FOREIGN KEY (tenant, ejecucion_id) REFERENCES ejecuciones (tenant, id),
    CONSTRAINT evaluaciones_aprobado_sin_causas CHECK ((decision = 'APROBADO') = (jsonb_array_length(causas) = 0))
);
CREATE TRIGGER evaluaciones_solo_agregar BEFORE UPDATE OR DELETE ON evaluaciones FOR EACH ROW EXECUTE FUNCTION solo_agregar();

-- ---------- Traza (R-14) ----------
CREATE TABLE auditoria (
    id        bigserial PRIMARY KEY,
    tenant    text NOT NULL,
    usuario   text NOT NULL,
    accion    text NOT NULL,
    recurso   text NOT NULL,
    en        timestamptz NOT NULL DEFAULT now()
);
CREATE TRIGGER auditoria_solo_agregar BEFORE UPDATE OR DELETE ON auditoria FOR EACH ROW EXECUTE FUNCTION solo_agregar();
