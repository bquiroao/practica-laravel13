-- Esquema mínimo del Micro-HIS (SQLite). beds, bed_stays y care_episodes usan los nombres y columnas reales del grupo
-- (módulo Verificación BD, semana 1); ordenes_movimiento es propia (RF-01). Sin columna de hospital: cada hospital tiene su base.
CREATE TABLE local_patients (local_patient_id TEXT PRIMARY KEY, given_name TEXT NOT NULL, family_name TEXT NOT NULL);
CREATE TABLE care_episodes (care_episode_id TEXT PRIMARY KEY, local_patient_id TEXT NOT NULL REFERENCES local_patients(local_patient_id),
                            type TEXT NOT NULL, status TEXT NOT NULL CHECK (status IN ('open', 'closed')), started_at TEXT NOT NULL, ended_at TEXT);
CREATE TABLE wards (ward_id TEXT PRIMARY KEY, code TEXT NOT NULL, name TEXT NOT NULL);
CREATE TABLE beds (bed_id TEXT PRIMARY KEY, ward_id TEXT NOT NULL REFERENCES wards(ward_id), code TEXT NOT NULL,
                   type TEXT NOT NULL CHECK (type IN ('general', 'uci')),
                   status TEXT NOT NULL CHECK (status IN ('available', 'occupied', 'maintenance', 'inactive')));
-- Estancia abierta = ended_at NULL. En PostgreSQL el grupo impide cruces con exclusión GiST.
CREATE TABLE bed_stays (bed_stay_id TEXT PRIMARY KEY, care_episode_id TEXT NOT NULL REFERENCES care_episodes(care_episode_id),
                        bed_id TEXT NOT NULL REFERENCES beds(bed_id), started_at TEXT NOT NULL, ended_at TEXT, reason TEXT);
CREATE TABLE ordenes_movimiento (orden_id TEXT PRIMARY KEY, care_episode_id TEXT NOT NULL REFERENCES care_episodes(care_episode_id),
                                 tipo TEXT NOT NULL CHECK (tipo IN ('traslado', 'alta')),
                                 estado TEXT NOT NULL CHECK (estado IN ('vigente', 'aplicada', 'cancelada')),
                                 requisito_cama TEXT, tipo_alta TEXT, motivo TEXT);

-- Datos de demostración. epi-9 no tiene estancia (prueba sin_cama); epi-2 solo tiene orden de alta (prueba orden_requerida).
INSERT INTO wards VALUES ('ward-gen', 'MED', 'Medicina general'), ('ward-uci', 'UCI', 'Cuidados intensivos');
INSERT INTO beds VALUES ('bed-a1', 'ward-gen', 'A1', 'general', 'occupied'), ('bed-a2', 'ward-gen', 'A2', 'general', 'available'),
  ('bed-a3', 'ward-gen', 'A3', 'general', 'occupied'), ('bed-a4', 'ward-gen', 'A4', 'general', 'occupied'),
  ('bed-a5', 'ward-gen', 'A5', 'general', 'occupied'), ('bed-u1', 'ward-uci', 'U1', 'uci', 'available'), ('bed-m1', 'ward-gen', 'M1', 'general', 'maintenance');
INSERT INTO local_patients VALUES ('pac-1', 'Ana', 'López'), ('pac-2', 'Carlos', 'Pérez'), ('pac-3', 'Elena', 'Ruiz'), ('pac-4', 'Mario', 'Soto'), ('pac-9', 'Sin', 'Cama');
INSERT INTO care_episodes VALUES ('epi-1', 'pac-1', 'hospitalizacion', 'open', '2026-10-20 08:00', NULL),
  ('epi-2', 'pac-2', 'hospitalizacion', 'open', '2026-10-21 08:00', NULL), ('epi-3', 'pac-3', 'hospitalizacion', 'open', '2026-10-22 08:00', NULL),
  ('epi-4', 'pac-4', 'hospitalizacion', 'open', '2026-10-22 08:00', NULL), ('epi-9', 'pac-9', 'hospitalizacion', 'open', '2026-10-23 08:00', NULL),
  ('epi-cerrado', 'pac-1', 'hospitalizacion', 'closed', '2026-09-01 08:00', '2026-09-10 08:00');
INSERT INTO bed_stays VALUES ('stay-1', 'epi-1', 'bed-a1', '2026-10-20 08:00', NULL, NULL), ('stay-2', 'epi-2', 'bed-a3', '2026-10-21 08:00', NULL, NULL),
  ('stay-3', 'epi-3', 'bed-a4', '2026-10-22 08:00', NULL, NULL), ('stay-4', 'epi-4', 'bed-a5', '2026-10-22 08:00', NULL, NULL);
INSERT INTO ordenes_movimiento VALUES ('ord-1', 'epi-1', 'traslado', 'vigente', 'cualquiera', NULL, NULL),
  ('ord-2', 'epi-2', 'alta', 'vigente', NULL, 'medica', 'Mejoría clínica'), ('ord-3', 'epi-3', 'traslado', 'vigente', 'uci', NULL, NULL),
  ('ord-4', 'epi-4', 'alta', 'vigente', NULL, 'medica', ''), ('ord-5', 'epi-9', 'traslado', 'vigente', 'cualquiera', NULL, NULL);
