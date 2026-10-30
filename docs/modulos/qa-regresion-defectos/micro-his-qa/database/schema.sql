-- Micro-HIS QA funcional, pruebas manuales y matriz de regresión: esquema propio «qa» (PostgreSQL 14+).
-- Las reglas que no pueden depender de que la aplicación «se porte bien» también están aquí:
--   versión aprobada inmutable (R-02), intentos e historial solo se agregan (R-11), clave de idempotencia única (R-12),
--   aprobador ≠ autor (R-02), NO_APLICA con motivo y FALLA con defecto (R-06, R-10), CRÍTICA con pasos y evidencia (R-07).
-- Idempotente: se puede volver a ejecutar (DROP SCHEMA ... CASCADE al inicio, solo para el entorno de prueba).

DROP SCHEMA IF EXISTS qa CASCADE;
CREATE SCHEMA qa;
SET search_path TO qa;

-- ---------- Casos de prueba versionados ----------
CREATE TABLE casos_prueba (
    tenant          text        NOT NULL,
    caso_id         text        NOT NULL CHECK (caso_id ~ '^TC-[0-9]{3,6}$'),
    version         integer     NOT NULL CHECK (version >= 1),
    autor           text        NOT NULL,
    titulo          text        NOT NULL CHECK (length(btrim(titulo)) BETWEEN 1 AND 200),
    precondiciones  text        NOT NULL DEFAULT '',
    pasos           jsonb       NOT NULL CHECK (jsonb_typeof(pasos) = 'array' AND jsonb_array_length(pasos) >= 1),
    prioridad       text        NOT NULL CHECK (prioridad IN ('ALTA', 'MEDIA', 'BAJA')),
    estado          text        NOT NULL DEFAULT 'BORRADOR' CHECK (estado IN ('BORRADOR', 'APROBADO')),
    aprobador       text,
    creado_en       timestamptz NOT NULL DEFAULT now(),
    PRIMARY KEY (tenant, caso_id, version),
    CONSTRAINT casos_aprobacion_coherente CHECK ((estado = 'APROBADO') = (aprobador IS NOT NULL)),
    CONSTRAINT casos_aprobador_no_autor CHECK (aprobador IS NULL OR aprobador <> autor)
);

CREATE TABLE caso_requisitos (
    tenant     text    NOT NULL,
    caso_id    text    NOT NULL,
    version    integer NOT NULL,
    requisito  text    NOT NULL CHECK (requisito ~ '^RF-[0-9]{2,3}$'),
    PRIMARY KEY (tenant, caso_id, version, requisito),
    FOREIGN KEY (tenant, caso_id, version) REFERENCES casos_prueba (tenant, caso_id, version)
);
CREATE INDEX caso_requisitos_por_requisito ON caso_requisitos (tenant, requisito);

-- Una versión solo cambia mientras es BORRADOR, y solo para aprobarse. Nunca se borra.
CREATE FUNCTION casos_proteger_version() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    IF TG_OP = 'DELETE' THEN
        RAISE EXCEPTION 'Un caso de prueba no se borra' USING ERRCODE = 'P0001';
    END IF;
    IF OLD.estado <> 'BORRADOR'
       OR (NEW.tenant, NEW.caso_id, NEW.version, NEW.autor, NEW.titulo, NEW.precondiciones, NEW.pasos, NEW.prioridad, NEW.creado_en)
          IS DISTINCT FROM (OLD.tenant, OLD.caso_id, OLD.version, OLD.autor, OLD.titulo, OLD.precondiciones, OLD.pasos, OLD.prioridad, OLD.creado_en) THEN
        RAISE EXCEPTION 'Una versión aprobada no cambia: se crea otra versión' USING ERRCODE = 'P0001';
    END IF;
    RETURN NEW;
END $$;
CREATE TRIGGER casos_version_inmutable BEFORE UPDATE OR DELETE ON casos_prueba FOR EACH ROW EXECUTE FUNCTION casos_proteger_version();

-- ---------- Matriz de regresión: funcionalidad → casos (críticos o no) ----------
CREATE TABLE matriz_regresion (
    tenant         text    NOT NULL,
    funcionalidad  text    NOT NULL CHECK (funcionalidad ~ '^[a-z][a-z0-9_-]{1,40}$'),
    caso_id        text    NOT NULL,
    critico        boolean NOT NULL DEFAULT false,
    PRIMARY KEY (tenant, funcionalidad, caso_id)
);

-- ---------- Campañas ----------
CREATE TABLE campanas (
    id               bigserial    PRIMARY KEY,
    tenant           text         NOT NULL,
    nombre           text         NOT NULL CHECK (length(btrim(nombre)) >= 1),
    funcionalidades  text[]       NOT NULL CHECK (cardinality(funcionalidades) >= 1),
    umbral           numeric(5,4) NOT NULL CHECK (umbral > 0 AND umbral <= 1),
    estado           text         NOT NULL DEFAULT 'PLANIFICADA' CHECK (estado IN ('PLANIFICADA', 'EN_CURSO', 'CERRADA')),
    build            text         CHECK (build IS NULL OR build ~ '^[0-9]+\.[0-9]+\.[0-9]+(\+[0-9a-f]{7,40})?$'),
    decision         text         CHECK (decision IS NULL OR decision IN ('GO', 'NO_GO')),
    tasa_aprobados   numeric(5,4),
    causas           jsonb        NOT NULL DEFAULT '[]',
    creada_por       text         NOT NULL,
    creada_en        timestamptz  NOT NULL DEFAULT now(),
    UNIQUE (tenant, id),
    CONSTRAINT campanas_build_al_iniciar CHECK (estado = 'PLANIFICADA' OR build IS NOT NULL),
    CONSTRAINT campanas_decision_al_cerrar CHECK ((estado = 'CERRADA') = (decision IS NOT NULL))
);

-- Alcance congelado: caso + versión. Se escribe al planificar y no cambia.
CREATE TABLE campana_alcance (
    tenant      text    NOT NULL,
    campana_id  bigint  NOT NULL,
    caso_id     text    NOT NULL,
    version     integer NOT NULL,
    PRIMARY KEY (campana_id, caso_id),
    FOREIGN KEY (tenant, campana_id) REFERENCES campanas (tenant, id),
    FOREIGN KEY (tenant, caso_id, version) REFERENCES casos_prueba (tenant, caso_id, version)
);

-- ---------- Defectos ----------
CREATE TABLE defectos (
    id                   bigserial   PRIMARY KEY,
    tenant               text        NOT NULL,
    campana_id           bigint      NOT NULL,
    caso_id              text        NOT NULL,
    titulo               text        NOT NULL CHECK (length(btrim(titulo)) >= 1),
    pasos                jsonb       NOT NULL DEFAULT '[]' CHECK (jsonb_typeof(pasos) = 'array'),
    severidad_propuesta  text        NOT NULL CHECK (severidad_propuesta IN ('CRITICA', 'MAYOR', 'MENOR', 'TRIVIAL')),
    con_evidencia        boolean     NOT NULL,
    reportado_por        text        NOT NULL,
    estado               text        NOT NULL DEFAULT 'NUEVO' CHECK (estado IN ('NUEVO', 'ABIERTO', 'CORREGIDO', 'CERRADO', 'REABIERTO')),
    severidad            text        CHECK (severidad IS NULL OR severidad IN ('CRITICA', 'MAYOR', 'MENOR', 'TRIVIAL')),
    prioridad            text        CHECK (prioridad IS NULL OR prioridad IN ('ALTA', 'MEDIA', 'BAJA')),
    creado_en            timestamptz NOT NULL DEFAULT now(),
    UNIQUE (tenant, id),
    FOREIGN KEY (tenant, campana_id) REFERENCES campanas (tenant, id),
    CONSTRAINT defectos_clasificado_fuera_de_nuevo CHECK (estado = 'NUEVO' OR (severidad IS NOT NULL AND prioridad IS NOT NULL)),
    CONSTRAINT defectos_critico_completo CHECK (severidad IS DISTINCT FROM 'CRITICA' OR (con_evidencia AND jsonb_array_length(pasos) >= 1))
);
CREATE INDEX defectos_por_caso ON defectos (tenant, caso_id) WHERE estado <> 'CERRADO';

CREATE TABLE defecto_historial (
    id             bigserial   PRIMARY KEY,
    tenant         text        NOT NULL,
    defecto_id     bigint      NOT NULL,
    evento         text        NOT NULL,
    registrado_en  timestamptz NOT NULL DEFAULT now(),
    FOREIGN KEY (tenant, defecto_id) REFERENCES defectos (tenant, id)
);

-- ---------- Intentos (inmutables) y sus evidencias ----------
CREATE TABLE intentos (
    id                bigserial   PRIMARY KEY,
    tenant            text        NOT NULL,
    campana_id        bigint      NOT NULL,
    caso_id           text        NOT NULL,
    numero            integer     NOT NULL CHECK (numero >= 1),
    resultado         text        NOT NULL CHECK (resultado IN ('PASA', 'FALLA', 'BLOQUEADO', 'NO_APLICA')),
    tester            text        NOT NULL,
    defecto_id        bigint,
    verifica_defecto  bigint,
    motivo            text,
    idempotency_key   text        NOT NULL CHECK (idempotency_key ~ '^[A-Za-z0-9-]{8,64}$'),
    huella_solicitud  char(64)    NOT NULL,
    registrado_en     timestamptz NOT NULL DEFAULT now(),
    CONSTRAINT intentos_clave_unica UNIQUE (tenant, idempotency_key),
    CONSTRAINT intentos_numero_unico UNIQUE (campana_id, caso_id, numero),
    FOREIGN KEY (campana_id, caso_id) REFERENCES campana_alcance (campana_id, caso_id),
    FOREIGN KEY (tenant, defecto_id) REFERENCES defectos (tenant, id),
    FOREIGN KEY (tenant, verifica_defecto) REFERENCES defectos (tenant, id),
    CONSTRAINT intentos_no_aplica_con_motivo CHECK (resultado <> 'NO_APLICA' OR length(btrim(coalesce(motivo, ''))) >= 1),
    CONSTRAINT intentos_falla_con_defecto CHECK (resultado <> 'FALLA' OR defecto_id IS NOT NULL OR verifica_defecto IS NOT NULL)
);

CREATE TABLE evidencias (
    id             bigserial PRIMARY KEY,
    intento_id     bigint    NOT NULL REFERENCES intentos (id),
    tipo           text      NOT NULL CHECK (tipo IN ('captura', 'registro', 'junit')),
    huella         char(64)  NOT NULL CHECK (huella ~ '^[0-9a-f]{64}$'),
    tamano         integer   NOT NULL CHECK (tamano BETWEEN 1 AND 5242880),
    clasificacion  text      NOT NULL CHECK (clasificacion IN ('GENERAL', 'DATOS_CLINICOS')),
    ubicacion      text      NOT NULL
);

CREATE TABLE referencias_externas (
    id           bigserial PRIMARY KEY,
    intento_id   bigint    NOT NULL REFERENCES intentos (id),
    url          text      NOT NULL CHECK (url ~ '^https://\S+$'),
    descripcion  text      NOT NULL DEFAULT ''
);

-- Lo registrado no se cambia ni se borra: un error se corrige con OTRO intento (R-11).
CREATE FUNCTION solo_agregar() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    RAISE EXCEPTION '% es de solo agregar', TG_TABLE_NAME USING ERRCODE = 'P0001';
END $$;
CREATE TRIGGER intentos_solo_agregar BEFORE UPDATE OR DELETE ON intentos FOR EACH ROW EXECUTE FUNCTION solo_agregar();
CREATE TRIGGER evidencias_solo_agregar BEFORE UPDATE OR DELETE ON evidencias FOR EACH ROW EXECUTE FUNCTION solo_agregar();
CREATE TRIGGER referencias_solo_agregar BEFORE UPDATE OR DELETE ON referencias_externas FOR EACH ROW EXECUTE FUNCTION solo_agregar();
CREATE TRIGGER historial_solo_agregar BEFORE UPDATE OR DELETE ON defecto_historial FOR EACH ROW EXECUTE FUNCTION solo_agregar();

-- ---------- Bandeja de auditoría (outbox): se escribe en la MISMA transacción ----------
CREATE TABLE auditoria_bandeja (
    id             bigserial   PRIMARY KEY,
    accion         text        NOT NULL,
    tenant         text        NOT NULL,
    actor          text        NOT NULL,
    recurso        text        NOT NULL,
    registrado_en  timestamptz NOT NULL DEFAULT now(),
    enviado_en     timestamptz
);
