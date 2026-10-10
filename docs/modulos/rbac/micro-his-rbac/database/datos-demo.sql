-- Datos ficticios de demostración (mismos escenarios que las semanas 1 y 2).

INSERT INTO hospitales (hospital_id, nombre) VALUES
    ('hosp-oriente', 'Hospital Regional de Oriente'),
    ('hosp-norte', 'Hospital del Norte');

INSERT INTO usuarios (usuario_id, nombre, estado) VALUES
    ('admin-01', 'Administrador Oriente', 'active'),
    ('admin-02', 'Administrador Norte', 'active'),
    ('ana', 'Ana López', 'active'),
    ('marta', 'Marta Pérez', 'active'),
    ('pedro', 'Pedro Gómez', 'active'),
    ('lucia', 'Lucía Ramírez', 'active'),
    ('nuevo-01', 'Usuario Nuevo', 'active'),
    ('jorge', 'Jorge Castillo', 'inactive');

INSERT INTO roles (codigo, nombre) VALUES
    ('admin_hospital', 'Administrador de hospital'),
    ('medico', 'Médico'),
    ('enfermeria', 'Enfermería'),
    ('laboratorio', 'Laboratorio');

INSERT INTO permisos (codigo, descripcion) VALUES
    ('roles.asignar', 'Asignar roles a usuarios del hospital'),
    ('auditoria.consultar', 'Consultar eventos de auditoría'),
    ('prescripciones.crear', 'Crear prescripciones'),
    ('signos_vitales.registrar', 'Registrar signos vitales'),
    ('expediente.leer', 'Leer expediente clínico'),
    ('resultados.registrar', 'Registrar resultados de laboratorio');

INSERT INTO rol_permiso (rol_codigo, permiso_codigo) VALUES
    ('admin_hospital', 'roles.asignar'),
    ('admin_hospital', 'auditoria.consultar'),
    ('medico', 'prescripciones.crear'),
    ('medico', 'signos_vitales.registrar'),
    ('medico', 'expediente.leer'),
    ('enfermeria', 'signos_vitales.registrar'),
    ('enfermeria', 'expediente.leer'),
    ('laboratorio', 'resultados.registrar');

INSERT INTO membresias (usuario_id, hospital_id, rol_codigo, estado, valido_desde, valido_hasta) VALUES
    ('admin-01', 'hosp-oriente', 'admin_hospital', 'active', '2026-01-01T00:00:00-06:00', NULL),
    ('admin-02', 'hosp-norte', 'admin_hospital', 'active', '2026-01-01T00:00:00-06:00', NULL),
    ('ana', 'hosp-oriente', 'enfermeria', 'active', '2026-01-01T00:00:00-06:00', NULL),
    ('marta', 'hosp-oriente', 'medico', 'active', '2026-02-01T00:00:00-06:00', NULL),
    ('pedro', 'hosp-oriente', 'medico', 'active', '2026-01-01T00:00:00-06:00', '2026-06-30T00:00:00-06:00'),
    ('lucia', 'hosp-oriente', 'enfermeria', 'revoked', '2026-01-01T00:00:00-06:00', NULL);
