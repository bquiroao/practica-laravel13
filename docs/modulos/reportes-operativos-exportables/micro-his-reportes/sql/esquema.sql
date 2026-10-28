-- Esquema mínimo del Micro-HIS (SQLite). Las «vistas» fuente son tablas de prueba con la forma que publicarían los otros módulos.
CREATE TABLE vw_censo_servicio (hospital_id TEXT NOT NULL, paciente TEXT NOT NULL, documento TEXT NOT NULL, servicio TEXT NOT NULL, cama TEXT NOT NULL,
                                fecha TEXT NOT NULL, registrado_en TEXT NOT NULL);
CREATE TABLE vw_egresos (hospital_id TEXT NOT NULL, paciente TEXT NOT NULL, documento TEXT NOT NULL, tipo_alta TEXT NOT NULL, fecha TEXT NOT NULL, registrado_en TEXT NOT NULL);
CREATE TABLE exportaciones_reporte (id TEXT PRIMARY KEY, codigo TEXT NOT NULL, filtros TEXT NOT NULL, formato TEXT NOT NULL, corte TEXT NOT NULL,
                                    filas INTEGER NOT NULL, hash TEXT NOT NULL CHECK (length(hash) = 64), usuario_id TEXT NOT NULL, hospital_id TEXT NOT NULL,
                                    datos_personales INTEGER NOT NULL, creado_en TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);

-- Datos ficticios: dos hospitales (nadie ve datos de otro) y filas desordenadas a propósito.
INSERT INTO vw_censo_servicio VALUES ('hosp-1', 'Luis Pérez', '2222', 'cirugia', 'B2', '2026-10-21', '2026-10-21 09:30:00'),
  ('hosp-1', 'Ana López', '1111', 'medicina', 'A1', '2026-10-20', '2026-10-20 08:00:00'), ('hosp-2', 'Otra Persona', '9999', 'medicina', 'C3', '2026-10-21', '2026-10-21 10:00:00');
INSERT INTO vw_egresos VALUES ('hosp-1', 'Rosa Díaz', '3333', 'medica', '2026-10-22', '2026-10-22 11:00:00');
