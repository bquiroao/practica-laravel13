-- Semana 4 · Cómo se integraría el módulo QA en el repositorio de datos COMPARTIDO del HIS: la base CENTRAL de la red
-- (database/migrations/central: networks, hospitals, central_users, hospital_memberships).
-- Se aplica DESPUÉS de las migraciones reales C0 y C1; bin/validar-integracion-central.php lo comprueba.
--
-- Diferencias con el Micro-HIS (schema.sql):
--   * el tenant deja de ser texto: es hospital_id con FK a hospitals;
--   * autor, aprobador, líder y tester dejan de ser texto: son user_id con FK a central_users;
--   * quién puede hacer qué lo decide la MEMBRESÍA del HIS (hospital_memberships activa y vigente, con su role_code),
--     comprobada por un trigger: la misma regla que el resto del HIS, no una lista propia del módulo;
--   * el módulo lee usuarios por una VISTA sin correo ni subject (datos personales) y no tiene privilegios sobre las
--     tablas de identidad.
-- «app_qa» es el rol de base de datos del módulo (el validador lo reemplaza por un rol temporal).

CREATE TABLE qa_casos (
    hospital_id  uuid    NOT NULL REFERENCES hospitals (hospital_id) ON DELETE RESTRICT,
    caso_id      text    NOT NULL CHECK (caso_id ~ '^TC-[0-9]{3,6}$'),
    version      integer NOT NULL CHECK (version >= 1),
    autor_id     uuid    NOT NULL REFERENCES central_users (user_id) ON DELETE RESTRICT,
    aprobador_id uuid             REFERENCES central_users (user_id) ON DELETE RESTRICT,
    titulo       text    NOT NULL,
    estado       text    NOT NULL DEFAULT 'BORRADOR' CHECK (estado IN ('BORRADOR', 'APROBADO')),
    PRIMARY KEY (hospital_id, caso_id, version),
    CONSTRAINT qa_casos_aprobador_no_autor CHECK (aprobador_id IS NULL OR aprobador_id <> autor_id)
);

CREATE TABLE qa_campanas (
    campana_id   uuid        PRIMARY KEY DEFAULT gen_random_uuid(),
    hospital_id  uuid        NOT NULL REFERENCES hospitals (hospital_id) ON DELETE RESTRICT,
    nombre       text        NOT NULL,
    lider_id     uuid        NOT NULL REFERENCES central_users (user_id) ON DELETE RESTRICT,
    estado       text        NOT NULL DEFAULT 'PLANIFICADA' CHECK (estado IN ('PLANIFICADA', 'EN_CURSO', 'CERRADA')),
    creada_en    timestamptz NOT NULL DEFAULT now(),
    UNIQUE (hospital_id, campana_id)
);

CREATE TABLE qa_intentos (
    intento_id       uuid        PRIMARY KEY DEFAULT gen_random_uuid(),
    hospital_id      uuid        NOT NULL,
    campana_id       uuid        NOT NULL,
    caso_id          text        NOT NULL,
    numero           integer     NOT NULL CHECK (numero >= 1),
    resultado        text        NOT NULL CHECK (resultado IN ('PASA', 'FALLA', 'BLOQUEADO', 'NO_APLICA')),
    tester_id        uuid        NOT NULL REFERENCES central_users (user_id) ON DELETE RESTRICT,
    idempotency_key  text        NOT NULL,
    registrado_en    timestamptz NOT NULL DEFAULT now(),
    FOREIGN KEY (hospital_id, campana_id) REFERENCES qa_campanas (hospital_id, campana_id),
    CONSTRAINT qa_intentos_clave_unica UNIQUE (hospital_id, idempotency_key),
    CONSTRAINT qa_intentos_numero_unico UNIQUE (campana_id, caso_id, numero)
);

-- ¿El usuario tiene, AHORA, una membresía activa en ese hospital con alguno de esos roles? Es la regla de identidad del HIS.
-- SECURITY DEFINER: el rol del módulo no puede leer hospital_memberships, pero sí preguntar esto (y nada más).
CREATE FUNCTION qa_miembro_activo(p_usuario uuid, p_hospital uuid, p_roles text[]) RETURNS boolean
LANGUAGE sql STABLE SECURITY DEFINER SET search_path FROM CURRENT AS $$
    SELECT EXISTS (
        SELECT 1 FROM hospital_memberships m
        JOIN central_users u ON u.user_id = m.user_id
        WHERE m.user_id = p_usuario AND m.hospital_id = p_hospital AND m.role_code = ANY (p_roles)
          AND m.status = 'active' AND u.status = 'active'
          AND m.valid_from <= now() AND (m.valid_to IS NULL OR m.valid_to > now())
    )
$$;

CREATE FUNCTION qa_exigir_membresia() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    -- IF anidados: PL/pgSQL no corta un AND, y NEW.tester_id no existe en las otras tablas.
    IF TG_TABLE_NAME = 'qa_intentos' THEN
        IF NOT qa_miembro_activo(NEW.tester_id, NEW.hospital_id, ARRAY['qa_tester']) THEN
            RAISE EXCEPTION 'El tester no tiene una membresía activa qa_tester en el hospital' USING ERRCODE = '42501';
        END IF;
    ELSIF TG_TABLE_NAME = 'qa_campanas' THEN
        IF NOT qa_miembro_activo(NEW.lider_id, NEW.hospital_id, ARRAY['qa_lead']) THEN
            RAISE EXCEPTION 'Quien planifica no es qa_lead activo en el hospital' USING ERRCODE = '42501';
        END IF;
    ELSIF NEW.aprobador_id IS NOT NULL THEN
        IF NOT qa_miembro_activo(NEW.aprobador_id, NEW.hospital_id, ARRAY['qa_lead']) THEN
            RAISE EXCEPTION 'El aprobador no es qa_lead activo en el hospital' USING ERRCODE = '42501';
        END IF;
    END IF;
    RETURN NEW;
END $$;
CREATE TRIGGER qa_intentos_membresia BEFORE INSERT ON qa_intentos FOR EACH ROW EXECUTE FUNCTION qa_exigir_membresia();
CREATE TRIGGER qa_campanas_membresia BEFORE INSERT ON qa_campanas FOR EACH ROW EXECUTE FUNCTION qa_exigir_membresia();
CREATE TRIGGER qa_casos_membresia BEFORE INSERT OR UPDATE ON qa_casos FOR EACH ROW EXECUTE FUNCTION qa_exigir_membresia();

-- Intentos de solo agregar (R-11), igual que en el Micro-HIS.
CREATE FUNCTION qa_solo_agregar() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    RAISE EXCEPTION '% es de solo agregar', TG_TABLE_NAME USING ERRCODE = 'P0001';
END $$;
CREATE TRIGGER qa_intentos_solo_agregar BEFORE UPDATE OR DELETE ON qa_intentos FOR EACH ROW EXECUTE FUNCTION qa_solo_agregar();

-- Lo único que el módulo necesita de las personas: id, nombre visible y estado. Sin correo ni subject.
CREATE VIEW qa_usuarios WITH (security_barrier) AS
    SELECT user_id, display_name, status FROM central_users;

-- Privilegios: el módulo escribe SOLO sus tablas y lee lo ajeno por la vista. PUBLIC no recibe nada.
REVOKE ALL ON qa_casos, qa_campanas, qa_intentos FROM PUBLIC;
GRANT SELECT, INSERT, UPDATE ON qa_casos, qa_campanas TO app_qa;
GRANT SELECT, INSERT ON qa_intentos TO app_qa;
GRANT SELECT ON qa_usuarios TO app_qa;
